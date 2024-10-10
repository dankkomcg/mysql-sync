<?php

namespace Dankkomcg\MySQL\Sync\Order;

use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;
use Dankkomcg\MySQL\Sync\Loggers\Loggable;
use Dankkomcg\MySQL\Sync\Models\Column;
use Dankkomcg\MySQL\Sync\Models\ForeignKey;
use Dankkomcg\MySQL\Sync\Models\Table;
use PDO;

abstract class DependencyResolver {

    use Loggable;

    protected const INFORMATION_SCHEMA_TABLES_QUERY =
        "SELECT 
                TABLE_NAME 
            FROM 
                INFORMATION_SCHEMA.TABLES 
            WHERE 
                TABLE_SCHEMA = :schema AND TABLE_TYPE = 'BASE TABLE'
                "
    ;

    protected const FOREIGN_ORDERED_INFORMATION_SCHEMA_TABLES =
        "SELECT
                TABLE_NAME, REFERENCED_TABLE_NAME
            FROM
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE
                TABLE_SCHEMA = :schema AND REFERENCED_TABLE_SCHEMA = :schema
                AND REFERENCED_TABLE_NAME IS NOT NULL
                "
    ;

    protected const FOREIGN_KEY_PATTERN_TABLE =
        "SELECT 
            TABLE_NAME, 
            CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, COLUMN_NAME
         FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
         WHERE TABLE_SCHEMA = :schema
         AND 
            TABLE_NAME = :table_name AND 
            REFERENCED_TABLE_NAME IS NOT NULL
        ;"
    ;

    /**
     * @var PDO
     */
    protected PDO $sourcePdo;

    /**
     * @var string
     */
    protected string $sourceSchema;

    public function __construct(PDO $sourceConnectionPdo, string $sourceSchema) {
        $this->sourcePdo    = $sourceConnectionPdo;
        $this->sourceSchema = $sourceSchema;
    }

    /**
     * Devuelve las tablas ordenadas en el orden que plantea el algoritmo concreto
     *
     * @return array
     */
    public abstract function getTablesInDependencyOrder(): array;

    /**
     * @throws QueryOrderException
     */
    protected function getSourceSchemaTables(): array {
        // Retrieve all tables on the information schema definition
        $stmt = $this->sourcePdo->prepare(self::INFORMATION_SCHEMA_TABLES_QUERY);
        $stmt->execute(['schema' => $this->sourceSchema]);

        if(!$tables = $stmt->fetchAll(PDO::FETCH_COLUMN)) {
            throw new QueryOrderException(
                sprintf(
                    "Can't find any tables on %s schema", $this->sourceSchema
                )
            );
        }

        // Mapear como objeto
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

        $stmt = $this->sourcePdo->prepare(self::FOREIGN_KEY_PATTERN_TABLE);

        $stmt->execute([
            'schema'     => $this->sourceSchema,
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
                    $table['CONSTRAINT_NAME']
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

}