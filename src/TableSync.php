<?php

namespace Dankkomcg\MySQL\Sync;

use PDO;
use PDOException;

// Clase que gestiona la sincronización de tablas
class TableSync extends Loggable
{
    private $sourcePdo;
    private $targetPdo;
    private $chunkSize;
    private $maxRecordsPerTable;
    private $extractedIds;
    private $insertRowBulkMode;
    private $syncDirection;

    public function __construct($sourcePdo, $targetPdo, $chunkSize, $maxRecordsPerTable = null, $syncDirection = 'DESC')
    {
        $this->sourcePdo          = $sourcePdo;
        $this->targetPdo          = $targetPdo;
        $this->chunkSize          = $chunkSize;
        $this->maxRecordsPerTable = $maxRecordsPerTable;
        $this->extractedIds       = [];
        $this->insertRowBulkMode  = true;
        $this->syncDirection = $syncDirection;
    }

    public function syncTables($tables, $sourceSchema, $targetSchema)
    {
        foreach ($tables as $table) {
            
            $this->logger()->info("Copiando datos de la tabla: $table...");

            $columnsInfo = $this->getColumnsInfo($table, $sourceSchema);
            $primaryKeys = $this->getPrimaryKeys($columnsInfo);
            $foreignKeys = $this->getForeignKeys($table, $sourceSchema);

            $orderColumn = $this->getOrderColumn($columnsInfo, $primaryKeys);

            $totalRows = $this->getTotalRows($table, $sourceSchema);
            if ($this->maxRecordsPerTable !== null) {
                $totalRows = min($totalRows, $this->maxRecordsPerTable);
            }

            $this->copyData($table, $columnsInfo, $primaryKeys, $foreignKeys, $orderColumn, $sourceSchema, $targetSchema, $totalRows);
        }

        $this->logger()->success("Copia de datos completada.");
    }

    private function getColumnsInfo($table, $schema)
    {
        $query = "SHOW COLUMNS FROM `$schema`.`$table`";
        $stmt = $this->sourcePdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPrimaryKeys($columnsInfo)
    {
        return array_column(array_filter($columnsInfo, function($column) {
            return $column['Key'] === 'PRI';
        }), 'Field');
    }

    private function getForeignKeys($table, $schema)
    {
        $query = "
        SELECT
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            TABLE_SCHEMA = :schema
            AND TABLE_NAME = :table
            AND REFERENCED_TABLE_NAME IS NOT NULL";
        
        $stmt = $this->sourcePdo->prepare($query);
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

            foreach ($foreignKeys as $fk) {
                $this->copyRelatedData($fk['REFERENCED_TABLE_NAME'], $fk['REFERENCED_COLUMN_NAME'], $rows, $fk['COLUMN_NAME'], $sourceSchema, $targetSchema);
            }

            // Comprueba el tipo de registro
            $this->insertRows($table, $columnsInfo, $primaryKeys, $rows, $targetSchema);

            $lastValue = end($rows)[$orderColumn];
            $processedRows += count($rows);

            $this->logger()->info(
                "Copiado un bloque de " . count($rows) . " registros de la tabla: $table. Último valor procesado: $lastValue."
            );

            if ($this->maxRecordsPerTable !== null && $processedRows >= $this->maxRecordsPerTable) {
                break;
            }
        }

        $this->logger()->success("Datos copiados completamente para la tabla: $table.");
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

    /**
     * 
     
     * @param mixed $table
     * @param mixed $orderColumn
     * @param mixed $schema
     * @param mixed $lastValue
     * @return mixed
     * @deprecated Se agrega la funcionalidad para ordenar según se indica en la configuración
     */
    private function _fetchRows($table, $orderColumn, $schema, $lastValue)
    {
        // Obtener solo las columnas necesarias
        $columns = $this->getTableColumns($schema, $table);
        $columnList = implode(', ', array_map(function($col) { return "`$col`"; }, $columns));
    
        // Preparar la consulta base
        $query = "SELECT $columnList FROM `$schema`.`$table`";
        
        // Añadir condición WHERE y ORDER BY
        if ($lastValue !== null) {
            $query .= " WHERE `$orderColumn` > :lastValue";
        }
        $query .= " ORDER BY `$orderColumn` ASC LIMIT :limit";
    
        // Preparar y ejecutar la consulta
        $stmt = $this->sourcePdo->prepare($query);
        
        if ($lastValue !== null) {
            $stmt->bindValue(':lastValue', $lastValue, $this->getColumnType($schema, $table, $orderColumn));
        }
        $stmt->bindValue(':limit', $this->chunkSize, PDO::PARAM_INT);
        
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getTableColumns($schema, $table) {
        $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table";
        $stmt = $this->targetPdo->prepare($query);
        $stmt->execute(['schema' => $schema, 'table' => $table]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    private function getColumnType($schema, $table, $column)
    {
        $query = "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column";
        $stmt = $this->sourcePdo->prepare($query);
        $stmt->execute(['schema' => $schema, 'table' => $table, 'column' => $column]);
        $type = $stmt->fetchColumn();
    
        switch (strtolower($type)) {
            case 'int': case 'bigint': case 'tinyint': case 'smallint': case 'mediumint':
                return PDO::PARAM_INT;
            case 'datetime': case 'timestamp': case 'date': case 'time':
                return PDO::PARAM_STR;
            default:
                return PDO::PARAM_STR;
        }
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
        $this->logger()->info("Columnas reales de la tabla $table: " . implode(", ", $realColumns));
    
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

            $this->logger()->info(sprintf("%s registros procesados en modo bulk para la tabla %s", count($rows), $table));

        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    // Intentar REPLACE INTO
                    $replaceQuery = "REPLACE INTO `$schema`.`$table` ($columnsList) VALUES $placeholdersString";
                    try {
                        $stmtReplace = $this->targetPdo->prepare($replaceQuery);
                        $stmtReplace->execute($insertData);
                        $this->logger()->info(
                            sprintf("%s registros procesados en modo REPLACE para la tabla %s", count($rows), $table)
                        );
                    } catch (PDOException $e2) {
                        $this->handleForeignKeyViolation($table, $schema, $columnsList, $rows, $realColumns);
                    }
                } else {
                    // Manejar violación de clave foránea
                    $this->handleForeignKeyViolation($table, $schema, $columnsList, $rows, $realColumns);
                }
            } else {
                $this->logger()->error("Error al insertar en la tabla $table: " . $e->getMessage());
                $this->logger()->error("Query: " . $insertQuery);
            }
        }
    }
    
    private function handleForeignKeyViolation($table, $schema, $columnsList, $rows, $realColumns)
    {
        $this->logger()->warning("Manejando violación de clave foránea para la tabla $table");
        
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
        
        $this->logger()->success(
            sprintf("%s registros insertados exitosamente, %s registros fallaron para la tabla %s", $successCount, $errorCount, $table)
        );
    }

    /**
     * Summary of _insertRows
     * @param mixed $table
     * @param mixed $columnsInfo
     * @param mixed $primaryKeys
     * @param mixed $rows
     * @param mixed $schema
     * @return void
     * @deprecated Cambia la query para evitar actualización de registros en origen que provoquen huérfanos
     */
    private function _insertRows($table, $columnsInfo, $primaryKeys, $rows, $schema)
    {
        if (empty($rows)) {
            return;
        }
    
        // Obtener las columnas reales de la tabla
        $realColumns = $this->getTableColumns($schema, $table);
    
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
                    $rowData[] = null; // Si la columna no existe en los datos, insertamos NULL
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
            
            $this->logger()->info(
                sprintf("%s registros procesados en modo bulk para la tabla %s", count($rows), $table)
            );
        } catch (PDOException $e) {
            $this->logger()->error("Error al insertar en la tabla $table: " . $e->getMessage());
            $this->logger()->error("Query: " . $insertQuery);
            // Aquí puedes decidir si quieres continuar o detener el proceso
            // throw $e; // Para detener el proceso
        }
    }

}