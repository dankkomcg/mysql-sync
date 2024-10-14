<?php

namespace Dankkomcg\MySQL\Sync\Database\Tables\Conditions\Resolvers;

use Dankkomcg\MySQL\Sync\Database\Models\Column;
use Dankkomcg\MySQL\Sync\Database\Models\ConstraintForeignKey;
use Dankkomcg\MySQL\Sync\Database\Models\ForeignKey;
use Dankkomcg\MySQL\Sync\Database\Models\TargetSchema;
use Dankkomcg\MySQL\Sync\Database\Models\TemplateSchema;
use Dankkomcg\MySQL\Sync\Database\Models\Table;
use Dankkomcg\MySQL\Sync\Database\Tables\QueryHelper;
use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;
use Dankkomcg\MySQL\Sync\Loggers\Loggable;
use PDO;

abstract class DependencyResolver {

    use Loggable;

    protected TemplateSchema $templateSchema;

    protected TargetSchema $targetSchema;

    public function __construct(TemplateSchema $schema, TargetSchema $targetSchema) {
        $this->templateSchema = $schema;
        $this->targetSchema = $targetSchema;
    }

    abstract function getFilteredTablesInDependencyOrder(array $filteredTables): array;

    /**
     * Devuelve las tablas ordenadas en el orden que plantea el algoritmo concreto
     *
     * @return array
     */
    abstract function getTablesInDependencyOrder(): array;

    /**
     * @throws QueryOrderException
     */
    protected function getSourceSchemaTables(): array {
        // Retrieve all tables on the information schema definition
        $stmt = $this->templateSchema->getDatabaseConnection()->prepare(QueryHelper::INFORMATION_SCHEMA_TABLES_QUERY);
        $stmt->execute(['schema' => $this->templateSchema->getSchemaName()]);

        if(!$tables = $stmt->fetchAll(PDO::FETCH_COLUMN)) {
            throw new QueryOrderException(
                sprintf(
                    "Can't find any tables on %s schema", $this->templateSchema->getSchemaName()
                )
            );
        }

        return $this->mapToTableEntity($tables);

    }

    /**
     * @throws QueryOrderException
     */
    protected function getSourceSchemaTablesFiltered(array $filteredTables): array {

        $filteredTablesToQuery = $this->getTablesToWhereIn($filteredTables);

        // Retrieve all tables on the information schema definition
        $stmt = $this->templateSchema->getDatabaseConnection()->prepare(
            sprintf(
                QueryHelper::INFORMATION_SCHEMA_TABLES_QUERY_FILTERED, $filteredTablesToQuery
            )
        );

        $stmt->execute(['schema' => $this->templateSchema->getSchemaName()]);

        if(!$tables = $stmt->fetchAll(PDO::FETCH_COLUMN)) {
            throw new QueryOrderException(
                sprintf(
                    "Can't find any tables on %s schema", $this->templateSchema->getSchemaName()
                )
            );
        }

        $this->logger()->info(
            sprintf(
                "Filtered %s tables successfully", count($tables)
            )
        );

        return $this->mapToTableEntity($tables);

    }

    protected function getTablesToWhereIn(array $filteredTables): string
    {

        $filteredTablesToQuery = array_map(function ($table) {
            return "'{$table}'";
        }, $filteredTables);

        return implode(',', $filteredTablesToQuery);

    }

    protected function mapToTableEntity(array $tables): array
    {
        return array_map(function ($table) {
            return new Table($table);
        }, $tables);
    }

    /**
     * Devuelve las tablas que requiere como clave foránea la tabla actual
     *
     * @param Table $queryTable
     * @return array <ForeignKey>
     */
    protected function getForeignKeyReferencedTables(Table $queryTable): array {

        $stmt = $this->templateSchema->getDatabaseConnection()->prepare(QueryHelper::FOREIGN_KEY_PATTERN_TABLE);

        $stmt->execute([
            'schema'     => $this->templateSchema->getSchemaName(),
            'table_name' => $queryTable->getName()
        ]);

        if ($tables = $stmt->fetchAll(PDO::FETCH_ASSOC)) {

            $this->logger()
                ->warning(
                    sprintf(
                        "Table %s reference %s tables", $queryTable->getName(), count($tables)
                    )
                )
            ;

            // Map to object
            return array_map(function ($table) {
                return new ForeignKey(
                    new Table($table['TABLE_NAME']),
                    new Table($table['REFERENCED_TABLE_NAME']),
                    new Column($table['COLUMN_NAME']),
                    new Column($table['REFERENCED_COLUMN_NAME']),
                    new ConstraintForeignKey($table['CONSTRAINT_NAME'])
                );
            }, $tables);
        }

        $this->logger()
            ->info(
                sprintf(
                    "Table %s hasn't any pattern table", $queryTable->getName()
                )
            )
        ;

        return [];
    }

    /**
     * @param Table $table
     * @param array $queue
     * @param array $processedTables
     * @return void
     */
    protected function processTableAndAncestors(Table $table, array &$queue, array &$processedTables): void {

        if (in_array($table->getName(), $processedTables)) {
            return;
        }

        $parentTables = $this->getParentTables($table);
        foreach ($parentTables as $parentTable) {
            $this->processTableAndAncestors($parentTable, $queue, $processedTables);
        }

        $queue[] = $table;
        $processedTables[] = $table->getName();
    }

    /**
     * @param Table $table
     * @return array
     */
    protected function getParentTables(Table $table): array {

        $parentTables = [];
        $stmt = $this->templateSchema->getDatabaseConnection()->prepare(QueryHelper::QUERY_PARENT_TABLES_FOREIGN_KEY);

        $stmt->execute([
            'schema' => $this->templateSchema->getSchemaName(),
            'table_name' => $table->getName()
        ]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $parentTables[] = new Table($row['REFERENCED_TABLE_NAME']);
        }

        return $parentTables;
    }

}