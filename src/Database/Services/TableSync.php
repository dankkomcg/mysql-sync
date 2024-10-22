<?php

namespace Dankkomcg\MySQL\Sync\Database\Services;

use Dankkomcg\Logger\Traits\Loggable;
use Dankkomcg\MySQL\Sync\Database\Models\Table;
use Dankkomcg\MySQL\Sync\Exceptions\TableSyncException;
use PDO;
use PDOException;

/**
 * Class which makes the table synchronization for SQL engines
 */
class TableSync {

    use Loggable;

    private PDO $sourcePdo;
    private PDO $targetPdo;
    private int $chunkSize;
    private int $maxRecordsPerTable;
    private array $extractedIds;
    private string $syncDirection;

    private const QUERY_TABLE_COLUMNS_INFORMATION_SCHEMA =
        "SELECT COLUMN_NAME 
        FROM 
            INFORMATION_SCHEMA.COLUMNS 
        WHERE 
            TABLE_SCHEMA = :schema AND TABLE_NAME = :table
        "
    ;

    private const QUERY_FOREIGN_KEYS_INFORMATION_SCHEMA =
        "
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            TABLE_SCHEMA = :schema
            AND TABLE_NAME = :table
            AND REFERENCED_TABLE_NAME IS NOT NULL
            "
    ;

    public function __construct(PDO $sourcePdo, PDO $targetPdo) {
        $this->sourcePdo          = $sourcePdo;
        $this->targetPdo          = $targetPdo;
        $this->extractedIds       = [];
    }

    /**
     * @param array $tables <Table> $tables
     * @param string $sourceSchema
     * @param string $targetSchema
     * @return void
     */
    public function syncSchemaTables(array $tables, string $sourceSchema, string $targetSchema) {

        try {

            $this->createTransactionStatement($tables, $sourceSchema, $targetSchema);
            $this->logger()->success("Tables are synchronized");

        } catch (\Exception $e) {

            $this->logger()->error(
                "Synchronization error: " . $e->getMessage()
            );
        }
    }

    /**
     * @param array $tables <Table> $tables
     * @param string $sourceSchema
     * @param string $targetSchema
     * @return void
     * @throws TableSyncException
     */
    private function createTransactionStatement(array $tables, string $sourceSchema, string $targetSchema): void {

        if (empty($tables)) {
            throw new TableSyncException("No tables to synchronize");
        }

        try {

            $this->targetPdo->beginTransaction();

            /** @var Table $table */
            foreach ($tables as $table) {
                $this->prepareTableAndCopy($table->getName(), $sourceSchema, $targetSchema);
            }

            // Commit when table is finished to prevent large commit
            $this->targetPdo->commit();

        } catch (\Exception $e) {

            $this->targetPdo->rollBack();

            throw new TableSyncException(
                sprintf(
                    "Synchronization error, rollback: %s", $e->getMessage()
                )
            );

        }

    }

    private function prepareTableAndCopy(string $table, string $sourceSchema, string $targetSchema): void {

        $this->logger()->info(
            sprintf(
                "Copying data from table %s...", $table
            )
        );

        $columnsInfo = $this->getColumnsInfo($table, $sourceSchema);
        $primaryKeys = $this->getPrimaryKeys($columnsInfo);
        $foreignKeys = $this->getForeignKeys($table, $sourceSchema);
        $orderColumn = $this->getOrderColumn($columnsInfo, $primaryKeys);
        $totalRows = $this->getTotalRows($table, $sourceSchema);

        if (isset($this->maxRecordsPerTable)) {
            $totalRows = min($totalRows, $this->maxRecordsPerTable);
        }

        $this->copyData(
            $table, $columnsInfo, $primaryKeys, $foreignKeys, $orderColumn, $sourceSchema, $targetSchema, $totalRows
        );

        $this->logger()->success(
            sprintf(
                "Data was successfully copied to table: %s.", $table
            )
        );


    }

    private function getColumnsInfo($table, $schema) {
        $query = "SHOW COLUMNS FROM `$schema`.`$table`";
        $stmt = $this->sourcePdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPrimaryKeys($columnsInfo): array
    {
        return array_column(array_filter($columnsInfo, function($column) {
            return $column['Key'] === 'PRI';
        }), 'Field');
    }

    private function getForeignKeys($table, $schema) {

        $stmt = $this->sourcePdo->prepare(
            self::QUERY_FOREIGN_KEYS_INFORMATION_SCHEMA
        );

        $stmt->execute(['schema' => $schema, 'table' => $table]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getOrderColumn($columnsInfo, $primaryKeys)
    {
        if (!empty($primaryKeys)) {
            return $primaryKeys[0];
        }

        foreach ($columnsInfo as $column) {
            if (strpos($column['Type'], 'date') !== false || strpos($column['Type'], 'timestamp') !== false) {
                return $column['Field'];
            }
        }

        return $columnsInfo[0]['Field'];
    }

    private function getTotalRows($table, $schema)
    {
        $query = "SELECT COUNT(*) FROM `$schema`.`$table`";
        $stmt = $this->sourcePdo->query($query);
        return $stmt->fetchColumn();
    }

    private function copyData($table, $columnsInfo, $primaryKeys, $foreignKeys, $orderColumn, $sourceSchema, $targetSchema, $totalRows)
    {
        $lastValue = null;
        $processedRows = 0;
        $this->extractedIds[$table] = [];

        while ($processedRows < $totalRows) {
            $rows = $this->fetchRows($table, $orderColumn, $sourceSchema, $lastValue);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                foreach ($primaryKeys as $pk) {
                    $this->extractedIds[$table][] = $row[$pk];
                }
            }

            // Si hay claves foráneas, extrae la tablas relacionadas con los IDs a exportar
            if (!empty($foreignKeys)) {
                foreach ($foreignKeys as $fk) {
                    $this->copyRelatedData(
                        $fk['REFERENCED_TABLE_NAME'],
                        $fk['REFERENCED_COLUMN_NAME'],
                        $rows, $fk['COLUMN_NAME'],
                        $sourceSchema, $targetSchema
                    );
                }
            }

            // Comprueba el tipo de registro
            $this->insertRows($table, $columnsInfo, $primaryKeys, $rows, $targetSchema);

            $lastValue = end($rows)[$orderColumn];
            $processedRows += count($rows);

            $this->logger()
                ->info(
                    sprintf(
                        "A block with %s rows was copied from table %s. Last item registered is %s", count($rows), $table, $lastValue
                    )
                )
            ;

            // Check if max records
            if (isset($this->maxRecordsPerTable) && ($processedRows >= $this->maxRecordsPerTable)) {
                break;
            }
        }

        $this->logger()->success(
            sprintf(
                "Data was successfully copied from table %s", $table
            )
        );
    }

    private function fetchRows($table, $orderColumn, $schema, $lastValue)
    {
        $direction = $this->syncDirection;
        $operator = $direction === 'DESC' ? '<' : '>';

        if ($lastValue === null) {
            $query = "SELECT * FROM `$schema`.`$table` ORDER BY `$orderColumn` $direction LIMIT $this->chunkSize";
            $stmt = $this->sourcePdo->query($query);
        } else {
            $query = "SELECT * FROM `$schema`.`$table` WHERE `$orderColumn` $operator :lastValue ORDER BY `$orderColumn` $direction LIMIT $this->chunkSize";
            $stmt = $this->sourcePdo->prepare($query);
            $stmt->execute(['lastValue' => $lastValue]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTableColumns($schema, $table) {

        $stmt = $this->targetPdo->prepare(
            self::QUERY_TABLE_COLUMNS_INFORMATION_SCHEMA
        );

        $stmt->execute(['schema' => $schema, 'table' => $table]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function copyRelatedData($referencedTable, $referencedColumn, $rows, $foreignKeyColumn, $sourceSchema, $targetSchema)
    {
        $values = array_unique(array_column($rows, $foreignKeyColumn));
        if (empty($values)) {
            return;
        }

        if (isset($this->extractedIds[$referencedTable])) {
            $values = array_diff($values, $this->extractedIds[$referencedTable]);
            if (empty($values)) {
                return;
            }
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $query = "SELECT * FROM `$sourceSchema`.`$referencedTable` WHERE `$referencedColumn` IN ($placeholders)";

        $stmt = $this->sourcePdo->prepare($query);
        $stmt->execute(array_values($values));
        $relatedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($relatedRows)) {
            $columnsInfo = $this->getColumnsInfo($referencedTable, $sourceSchema);
            $primaryKeys = $this->getPrimaryKeys($columnsInfo);

            // Comprueba el tipo de registro
            $this->insertRows($referencedTable, $columnsInfo, $primaryKeys, $relatedRows, $targetSchema);

            foreach ($relatedRows as $row) {
                foreach ($primaryKeys as $pk) {
                    $this->extractedIds[$referencedTable][] = $row[$pk];
                }
            }
        }
    }

    private function insertRows($table, $columnsInfo, $primaryKeys, $rows, $schema)
    {
        if (empty($rows)) {
            return;
        }

        $realColumns = $this->getTableColumns($schema, $table);

        $this->logger()
            ->info(
                sprintf(
                    "Real columns from the table %s: %s", $table,  implode(", ", $realColumns)
                )
            )
        ;

        $columnsList = implode(", ", array_map(function($col) {
            return "`$col`";
        }, $realColumns));

        $placeholders = [];
        $insertData = [];
        foreach ($rows as $row) {
            $rowPlaceholders = [];
            $rowData = [];
            foreach ($realColumns as $column) {
                if (array_key_exists($column, $row)) {
                    $value = $row[$column];
                    if ($value === '' && isset($columnsInfo[$column]) && strpos($columnsInfo[$column], 'int') !== false) {
                        $rowData[] = null;
                    } elseif (is_string($value)) {
                        $rowData[] = mb_convert_encoding($value, 'UTF-8', 'auto');
                    } else {
                        $rowData[] = $value;
                    }
                } else {
                    $rowData[] = null;
                }
                $rowPlaceholders[] = '?';
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
            $insertData = array_merge($insertData, $rowData);
        }

        $placeholdersString = implode(', ', $placeholders);

        $updateList = [];
        foreach ($realColumns as $column) {
            if (!in_array($column, $primaryKeys)) {
                $updateList[] = "`$column` = VALUES(`$column`)";
            }
        }

        $updateClause = implode(", ", $updateList);

        $insertQuery = "INSERT INTO `$schema`.`$table` ($columnsList) VALUES $placeholdersString 
            ON DUPLICATE KEY UPDATE $updateClause";

        try {

            $stmtInsert = $this->targetPdo->prepare($insertQuery);
            $stmtInsert->execute($insertData);

            $this->logger()
                ->info(
                    sprintf("%s rows processed in bulk mode from table %s", count($rows), $table)
                )
            ;

        } catch (PDOException $e) {

            if ($e->getCode() == '23000') {

                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    // Intentar REPLACE INTO
                    $replaceQuery = "REPLACE INTO `$schema`.`$table` ($columnsList) VALUES $placeholdersString";
                    try {
                        $stmtReplace = $this->targetPdo->prepare($replaceQuery);
                        $stmtReplace->execute($insertData);

                        $this->logger()->info(
                            sprintf("%s rows processed in REPLACE mode from table %s", count($rows), $table)
                        );

                    } catch (PDOException $e2) {
                        $this->handleForeignKeyError($table, $schema, $columnsList, $rows, $realColumns);
                    }

                } else {

                    // Try to fix the foreign key
                    $this->handleForeignKeyError($table, $schema, $columnsList, $rows, $realColumns);
                }

            } else {

                $this->logger()->error(
                    sprintf(
                        "Error while insert in %s: %s", $table, $e->getMessage()
                    )
                );

                $this->logger()->error(sprintf("Query: %s",$insertQuery));
            }

        }
    }

    private function handleForeignKeyError($table, $schema, $columnsList, $rows, $realColumns)
    {
        $this->logger()
            ->warning(
                sprintf(
                    "Reviewing the foreign key error from table %s", $table
                )
            );

        // Insertar registros uno por uno
        $insertQuery = "INSERT INTO `$schema`.`$table` ($columnsList) VALUES (" . implode(',', array_fill(0, count($realColumns), '?')) . ")
            ON DUPLICATE KEY UPDATE " . implode(', ', array_map(function($col) { return "`$col` = VALUES(`$col`)"; }, $realColumns)
            );

        $stmtInsert = $this->targetPdo->prepare($insertQuery);

        $successCount = 0;
        $errorCount = 0;

        foreach ($rows as $row) {
            $rowData = [];
            foreach ($realColumns as $column) {
                $rowData[] = array_key_exists($column, $row) ? $row[$column] : null;
            }

            try {
                $stmtInsert->execute($rowData);
                $successCount++;
            } catch (PDOException $e) {
                $errorCount++;
                // Opcionalmente, puedes logear los errores específicos aquí
            }
        }

        $this->logger()
            ->success(
                sprintf("%s rows created successfully, %s rows fail from table %s", $successCount, $errorCount, $table)
            )
        ;
    }

}