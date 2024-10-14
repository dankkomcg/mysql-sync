<?php

namespace Dankkomcg\MySQL\Sync;

use Dankkomcg\MySQL\Sync\Database\DatabaseConnection;
use Dankkomcg\MySQL\Sync\Database\Models\TargetSchema;
use Dankkomcg\MySQL\Sync\Database\Models\TemplateSchema;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\Resolvers\DependencyResolver;
use Dankkomcg\MySQL\Sync\Database\Tables\Conditions\TableCondition;
use Dankkomcg\MySQL\Sync\Exceptions\ChunkSizeValueException;
use Dankkomcg\MySQL\Sync\Exceptions\EmptyTablesFilteredSchemaException;
use Dankkomcg\MySQL\Sync\Exceptions\MaxRecordsValueException;
use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;
use Dankkomcg\MySQL\Sync\Loggers\Loggable;

class SyncManager {

    use Loggable;

    /**
     * @var DatabaseConnection
     */
    private DatabaseConnection $sourceConnection;

    /**
     * @var DatabaseConnection
     */
    private DatabaseConnection $targetConnection;

    /**
     * @var int
     */
    private int $chunkSize;

    /**
     * @var string
     */
    private string $maxRecordsPerTable;

    /**
     * @var string
     */
    private string $queryOrder;

    /**
     * @var array
     */
    private array $filteredTables;

    /**
     * @var DependencyResolver
     */
    private DependencyResolver $dependencyResolver;

    /**
     * @var TemplateSchema
     */
    private TemplateSchema $templateSchema;

    /**
     * @param DatabaseConnection $sourceConnection
     * @param DatabaseConnection $targetConnection
     * @return SyncManager
     */
    public static function create(DatabaseConnection $sourceConnection,
                                  DatabaseConnection $targetConnection): SyncManager {

        return new self($sourceConnection, $targetConnection);

    }

    /**
     * Extract the data information from origin to import into destiny schema
     *
     * @param string $sourceSchemaString
     * @param string $targetSchemaString
     * @param array $tables
     * @return void
     * @throws ChunkSizeValueException
     * @throws MaxRecordsValueException
     * @throws QueryOrderException
     */
    public function run(string $sourceSchemaString, string $targetSchemaString, array $tables = []) {

        $copyParameters = new SyncParameters(
            $this->chunkSize, $this->queryOrder, $this->filteredTables, $this->maxRecordsPerTable
        );

        $this->copy(
            new TemplateSchema(
                $sourceSchemaString, $this->sourceConnection, $copyParameters->getFilteredTables()
            ),
            new TargetSchema(
                $targetSchemaString, $this->targetConnection
            ),
            $copyParameters
        );

        /*
        try {

            // Set as a default criteria
            $criteria = new TransactionalCriteria($this->chunkSize);

            // Create an object to abstract to the client
            $criteriaSchema = new Schema($sourceSchema, $this->sourceConnection->getConnection());
            // Retrieve tables from source schema with foreign key dependency order resolved
            $dependencyOrderedTables = $criteria->getTablesBasedOnCriteria($criteriaSchema, $this->filteredTables);

            print_r($dependencyOrderedTables) && exit;

            $this->adviceWarningFilteredTables($dependencyOrderedTables);

            $tableSync = new TableSync(
                $this->sourceConnection->getConnection(), $this->targetConnection->getConnection()
            );

            $tableSync->setChunkSize($this->chunkSize);
            $tableSync->setQueryOrder($this->queryOrder);

            if (isset($this->maxRecordsPerTable)) {
                $tableSync->setMaxRecordsPerTable($this->maxRecordsPerTable);
            }

            $this->logger()->info(
                sprintf(
                    "Synchronization from %s origin schema to %s target schema",
                    $sourceSchema, $targetSchema
                )
            );

            $tableSync->syncSchemaTables($dependencyOrderedTables, $sourceSchema, $targetSchema);

            $this->logger()->success("Synchronization is completed");

        } catch (Exception $e) {
            $this->logger()->error(
                sprintf(
                    "Error while synchronization: %s", $e->getMessage()
                )
            );
        }
        */
    }

    private function copy(TemplateSchema $templateSchema, TargetSchema $targetSchema, SyncParameters $parameters) {

        print_r($parameters) && exit;

        // todo Decidir el table condition
        $tableCondition = new TableCondition();

        $this->dependencyResolver->whereTableCondition($tableCondition)->getTablesBasedOnCriteria();

        if (isset($this->filteredTables)) {
            $tables = $tableCondition->getTablesBasedOnCriteria($templateSchema);
        } else {
            $tables = $tableCondition->getTablesBasedOnCriteria($templateSchema);
        }

        print_r($tables) && exit;

    }

    /**
     * @param DatabaseConnection $sourceConnection
     * @param DatabaseConnection $targetConnection
     * @param int $chunkSize
     * @param $maxRecordsPerTable
     * @param string $queryOrder
     */
    public function __construct(
        DatabaseConnection $sourceConnection, DatabaseConnection $targetConnection,
        // Will be removed in next version because bad parametrization
        int $chunkSize = 1000, $maxRecordsPerTable = null, string $queryOrder = 'DESC'
    ) {

        $this->sourceConnection   = $sourceConnection;
        $this->targetConnection   = $targetConnection;

        $this->chunkSize          = $chunkSize;
        $this->queryOrder         = $queryOrder;
        $this->filteredTables     = [];

        if (isset($maxRecordsPerTable)) {
            $this->maxRecordsPerTable = $maxRecordsPerTable;
        }

    }

    public function setChunkSize(int $chunkSize): void {
        $this->chunkSize = $chunkSize;
    }

    public function setMaxRecordsPerTable(int $maxRecordsPerTable): void {
        $this->maxRecordsPerTable = $maxRecordsPerTable;
    }

    public function setQueryOrder(string $queryOrder): void {
        $this->queryOrder = $queryOrder;
    }

    /**
     * Tables which is not synchronized between origin and destiny
     *
     * @param array $filteredTables
     * @return void
     */
    public function setFilteredTables(array $filteredTables): void {
        $this->filteredTables = $filteredTables;
    }


}