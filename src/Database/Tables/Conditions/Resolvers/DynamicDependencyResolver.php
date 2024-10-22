<?php

namespace Dankkomcg\MySQL\Sync\Database\Tables\Conditions\Resolvers;

use Dankkomcg\Logger\Exceptions\NoLogFoundException;
use Dankkomcg\MySQL\Sync\Database\Models\ForeignKey;
use Dankkomcg\MySQL\Sync\Database\Models\Table;
use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;

class DynamicDependencyResolver extends DependencyResolver {

    /**
     * @throws NoLogFoundException
     * @throws QueryOrderException
     */
    public function getFilteredTablesInDependencyOrder(array $filteredTables): array {

        $availableTables = $this->getSourceSchemaTablesFiltered($filteredTables);
        $dependantTables = [];
        $queue = [];
        $processedTables = [];

        // Procesar tablas iniciales y sus dependencias ascendentes
        foreach ($availableTables as $table) {
            $this->processTableAndAncestors($table, $queue, $processedTables);
        }

        // Procesar dependencias descendentes
        foreach ($queue as $table) {
            if ($referencedTables = $this->getForeignKeyReferencedTables($table)) {
                if (!empty($referencedTables)) {
                    $dependantTables = array_merge($dependantTables, $referencedTables);
                }
            }
        }

        // Asignar las tablas referenciadas a las tablas resueltas correspondientes
        foreach ($dependantTables as $foreignKey) {
            foreach ($queue as $table) {
                if ($table->getName() === $foreignKey->getReferencedTable()->getName()) {
                    $table->addForeignKey($foreignKey);
                }
            }
        }

        $totalQueueTables = count($queue);
        $totalFilteredTables = count($filteredTables);

        if ($totalQueueTables > $totalFilteredTables) {
            $this->logger()->warning(
                sprintf(
                    "%s filtered tables, %s tables to synchronize", $totalFilteredTables, $totalQueueTables
                )
            );
        }

        return $queue;
    }

    /**
     * @throws QueryOrderException
     */
    public function getTablesInDependencyOrder(): array {

        $availableTables = $this->getSourceSchemaTables();
        $dependantTables = [];
        $queue = [];

        /** @var Table $table */
        foreach ($availableTables as $table) {

            if ($referencedTables = $this->getForeignKeyReferencedTables($table)) {
                if (!empty($referencedTables)) {
                    // Agrega las claves que referencian a la tabla a la lista de tablas excluidas
                    $dependantTables = array_merge($dependantTables, $referencedTables);
                    continue;
                }
            }

            // Si no hay tablas referenciadas, podemos agregarla a la cola
            $queue[] = $table;

        }

        if (empty($referencedTables)) {
            return $queue;
        }

        // Asignar las tablas referenciadas a las tablas resueltas correspondientes
        /** @var ForeignKey $foreignKey */
        foreach ($dependantTables as $foreignKey) {

            /** @var Table $table */
            foreach ($queue as $table) {
                if ($table->getName() === $foreignKey->getReferencedTable()->getName()) {
                    $table->addForeignKey($foreignKey);
                }
            }

        }

        return $queue;
    }

}