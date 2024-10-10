<?php

namespace Dankkomcg\MySQL\Sync\Order;

use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;
use Dankkomcg\MySQL\Sync\Models\ForeignKey;
use Dankkomcg\MySQL\Sync\Models\Table;

class DynamicDependencyResolver extends DependencyResolver {

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
                    // Agrega las las claves que referencian a la tabla a la lista de tablas excluidas
                    $dependantTables = array_merge(
                        $dependantTables, $referencedTables
                    );
                    continue;
                }
            }

            // Si no hay tablas referenciadas, podemos agregarla a la cola
            $queue[] = $table;

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