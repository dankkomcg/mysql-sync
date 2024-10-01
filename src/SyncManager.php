<?php

namespace Dankkomcg\MySQL\Sync;

use Dankkomcg\MySQL\Sync\Exceptions\OrderSyncException;
use Exception;

// Clase que coordina la sincronización
class SyncManager extends Loggable
{
    private DatabaseConnection $sourceConnection;
    private DatabaseConnection $targetConnection;
    private int $chunkSize;
    private int $maxRecordsPerTable;
    private $syncDirection;
    
    private static SyncManager $_instance;

    /**
     * 
     * Fake instance creator to prevent create a new version
     * In next version, construct class must be changed by self construct methods
     * 
     * @throws \Exception
     * @return \Dankkomcg\MySQL\Sync\SyncManager
     */
    public static function getInstance(): SyncManager {
        if(!isset(self::$_instance)) {
            throw new Exception("");
        }
        return self::$_instance;
    }

    /**
     * Simple self construct class
     * 
     * @param \Dankkomcg\MySQL\Sync\DatabaseConnection $sourceConnection
     * @param \Dankkomcg\MySQL\Sync\DatabaseConnection $targetConnection
     * @return \Dankkomcg\MySQL\Sync\SyncManager
     */
    public static function create(
        DatabaseConnection $sourceConnection, DatabaseConnection $targetConnection
    ): SyncManager {
        return new self(
            $sourceConnection, $targetConnection
        );
    }

    /**
     * Create with chunkSize to delimite the data to extract and synchronize
     * 
     * @param \Dankkomcg\MySQL\Sync\Databaseconnection $sourceConnection
     * @param \Dankkomcg\MySQL\Sync\DatabaseConnection $targetConnection
     * @param int $chunkSize
     * @return \Dankkomcg\MySQL\Sync\SyncManager
     */
    public static function createByChunkSize(
        Databaseconnection $sourceConnection, DatabaseConnection $targetConnection, int $chunkSize
    ): SyncManager {
        return new self(
            $sourceConnection, $targetConnection, $chunkSize
        );
    }
    
    public function setChunkSize(int $chunkSize): void {

        if($chunkSize <= 0) {
            throw new OrderSyncException(
                sprintf(
                    "%s as chunk size can't be less or equal to zero"
                )
            );
        }

        $this->chunkSize = $chunkSize;
    }

    public function setMaxRecordsPerTable(int $maxRecordsPerTable): void {
        $this->maxRecordsPerTable = $maxRecordsPerTable;
    }

    public function setSyncDirection(string $syncDirection): void {
        
        if(!in_array($syncDirection, ['ASC', 'DESC'])) {
            throw new OrderSyncException(
                sprintf(
                    "%s is a not valid value to define the synchronization order."
                )
            );
        }

        $this->syncDirection = $syncDirection;

    }

    /**
     * Classic construct of class will be modificated to use self construct methods in next version
     * 
     * @param DatabaseConnection $sourceConnection
     * @param DatabaseConnection $targetConnection
     * @param mixed $chunkSize
     * @param mixed $maxRecordsPerTable
     * @param mixed $syncDirection
     * 
     */
    public function __construct(
        DatabaseConnection $sourceConnection, DatabaseConnection $targetConnection, 
        // Will be removed in next version
        $chunkSize = null, $maxRecordsPerTable = null, $syncDirection = 'DESC'
    ) {
        $this->sourceConnection   = $sourceConnection;
        $this->targetConnection   = $targetConnection;
        $this->chunkSize          = $chunkSize;
        $this->maxRecordsPerTable = $maxRecordsPerTable;
        $this->syncDirection      = $syncDirection;

        // Marcar la instancia como creada
        self::$_instance = $this;

    }

    /**
     * Extrae la información desde el esquema de origen hasta el esquema de destino
     * 
     * @param string $sourceSchema
     * @param string $targetSchema
     * @return void
     */
    public function run(string $sourceSchema, string $targetSchema)
    {
        
        try {
            
            $this->logger()->write("Iniciando proceso de sincronización...", 'info');

            // Crea el orden de dependencias de las tablas para evitar insertar datos huérfanos
            // De esta forma evita el tener que desactivar las claves foráneas en destino
            $resolver = new DependencyResolver();
            
            $tables = $resolver->getTablesInDependencyOrder(
                $this->sourceConnection->getPdo(), $sourceSchema
            );

            $tableSync = new TableSync(
                $this->sourceConnection->getPdo(),
                $this->targetConnection->getPdo(),
                $this->chunkSize,
                $this->maxRecordsPerTable,
                $this->syncDirection
            );

            $tableSync->syncTables($tables, $sourceSchema, $targetSchema);
            
            $this->logger()->success("Sincronización completada exitosamente.");

        } catch (Exception $e) {
            $this->logger()->error("Error durante la sincronización: " . $e->getMessage());
        }
    }
}