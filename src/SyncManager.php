<?php

namespace Dankkomcg\MySQL\Sync;

use Exception;

// Clase que coordina la sincronización
class SyncManager extends Loggable
{
    private $sourceConnection;
    private $targetConnection;
    private $chunkSize;
    private $maxRecordsPerTable;
    private $syncDirection;
    
    public function __construct(
        $sourceConnection, $targetConnection, $chunkSize, $maxRecordsPerTable = null,
        $syncDirection = 'DESC'
        )
    {
        $this->sourceConnection   = $sourceConnection;
        $this->targetConnection   = $targetConnection;
        $this->chunkSize          = $chunkSize;
        $this->maxRecordsPerTable = $maxRecordsPerTable;
        $this->syncDirection = $syncDirection;
    }

    /**
     * Extrae la información desde el esquema de origen hasta el esquema de destino
     * 
     * @param mixed $sourceSchema
     * @param mixed $targetSchema
     * @return void
     */
    public function run($sourceSchema, $targetSchema)
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