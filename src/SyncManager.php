<?php

namespace Dankkomcg\MySQL\Sync;

use Dankkomcg\MySQL\Sync\Exceptions\ChunkSizeValueException;
use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;
use Dankkomcg\MySQL\Sync\Loggers\Loggable;
use Dankkomcg\MySQL\Sync\Order\DependencyResolver;
use Dankkomcg\MySQL\Sync\Order\DynamicDependencyResolver;
use Dankkomcg\MySQL\Sync\Order\TopologicalDependencyResolver;
use Exception;

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
     * @var mixed
     */
    private $maxRecordsPerTable;

    /**
     * @var string
     */
    private string $queryOrder;

    /**
     * @var array
     */
    private array $filteredTables = [];

    /**
     * @var DependencyResolver
     */
    private DependencyResolver $dependencyResolver;

    /**
     * Simple self construct class
     * 
     * @param DatabaseConnection $sourceConnection
     * @param DatabaseConnection $targetConnection
     * @return SyncManager
     */
    public static function create(DatabaseConnection $sourceConnection, DatabaseConnection $targetConnection): SyncManager {
        return new self($sourceConnection, $targetConnection);
    }

    /**
     * @throws ChunkSizeValueException
     */
    public function setChunkSize(int $chunkSize): void {

        if($chunkSize <= 0) {
            throw new ChunkSizeValueException(
                sprintf(
                    "%s as chunk size can't be less or equal to zero", $chunkSize
                )
            );
        }

        $this->chunkSize = $chunkSize;
    }

    public function setMaxRecordsPerTable(int $maxRecordsPerTable): void {
        $this->maxRecordsPerTable = $maxRecordsPerTable;
    }

    /**
     * @throws QueryOrderException
     */
    public function setQueryOrder(string $queryOrder): void {
        
        if(!in_array($queryOrder, ['ASC', 'DESC'])) {
            throw new QueryOrderException(
                sprintf(
                    "%s is a not valid value to define the synchronization order.", $queryOrder
                )
            );
        }

        $this->queryOrder = $queryOrder;

    }

    /**
     * Classic construct of class will be updated to use self construct methods in next version
     *
     * @param DatabaseConnection $sourceConnection
     * @param DatabaseConnection $targetConnection
     * @param int $chunkSize
     * @param mixed $maxRecordsPerTable
     * @param string $queryOrder
     */
    public function __construct(
        DatabaseConnection $sourceConnection, DatabaseConnection $targetConnection,
        // Will be removed in next version
        int $chunkSize = 1000, $maxRecordsPerTable = null, string $queryOrder = 'DESC'
    ) {
        $this->sourceConnection   = $sourceConnection;
        $this->targetConnection   = $targetConnection;
        $this->chunkSize          = $chunkSize;
        $this->maxRecordsPerTable = $maxRecordsPerTable;
        $this->queryOrder         = $queryOrder;
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

    /**
     * Extract the data information from origin to import into destiny schema
     *
     * @param string $sourceSchema
     * @param string $targetSchema
     * @return void
     */
    public function run(string $sourceSchema, string $targetSchema) {
        
        try {

            // Validate the configuration
            $this->validate();
            
            $this->logger()->info(
                sprintf(
                    "Synchronization from %s origin schema to %s target schema",
                    $sourceSchema, $targetSchema
                )
            );

            // Retrieve tables from source schema with foreign key dependency order resolved
            $dependencyOrderedTables = $this->getSourceTablesWithDependencyOrder($sourceSchema);

            $tableSync = new TableSync(
                $this->sourceConnection->getPdo(), $this->targetConnection->getPdo()
            );

            $tableSync->setChunkSize($this->chunkSize);
            $tableSync->setQueryOrder($this->queryOrder);

            if (isset($this->maxRecordsPerTable)) {
                $tableSync->setMaxRecordsPerTable($this->maxRecordsPerTable);
            }

            $tableSync->syncSchemaTables($dependencyOrderedTables, $sourceSchema, $targetSchema);
            
            $this->logger()->success("Synchronization is completed");

        } catch (Exception $e) {
            $this->logger()->error(
                sprintf(
                    "Error while synchronization: %s", $e->getMessage()
                )
            );
        }
    }

    /**
     * @throws ChunkSizeValueException
     * @throws QueryOrderException
     */
    private function validate() {

        if (empty($this->chunkSize)) {
            throw new ChunkSizeValueException(
                "The chunk size must be greater than 0 or null"
            );
        }

        if (empty($this->queryOrder)) {
            throw new QueryOrderException(
                "The synchronization order must be defined"
            );
        }

    }

    /**
     * Crea el orden de dependencias de las tablas para evitar insertar datos huérfanos
     * De esta forma evita el tener que desactivar las claves foráneas en destino
     *
     * @param string $sourceSchema
     * @return array
     * @throws QueryOrderException
     */
    private function getSourceTablesWithDependencyOrder(string $sourceSchema): array {

        // $dependencyOrderTableResolver = new TopologicalDependencyResolver($this->sourceConnection->getPdo(), $sourceSchema);
        $dependencyOrderTableResolver = new DynamicDependencyResolver($this->sourceConnection->getPdo(), $sourceSchema);
        $tablesOrderedByForeignKey    = $dependencyOrderTableResolver->getTablesInDependencyOrder();

        print_r($tablesOrderedByForeignKey) && exit;

        // If isset filteredTables, only make the sync with this tables
        if (!empty($this->filteredTables)) {

            $this->logger()->warning(
                sprintf(
                    "You've add %s tables to the filter, be carefully with foreign keys dependency order", count($this->filteredTables)
                )
            );

            // Exclude tables
            $tablesOrderedByForeignKey = array_intersect(
                $tablesOrderedByForeignKey, $this->filteredTables
            );
        }

        return $tablesOrderedByForeignKey;

    }

}