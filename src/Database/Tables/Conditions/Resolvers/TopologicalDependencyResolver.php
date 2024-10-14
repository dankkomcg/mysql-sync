<?php

namespace Dankkomcg\MySQL\Sync\Database\Tables\Conditions\Resolvers;

use Dankkomcg\MySQL\Sync\Database\Models\Table;
use Dankkomcg\MySQL\Sync\Database\Tables\QueryHelper;
use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;
use PDO;

class TopologicalDependencyResolver extends DependencyResolver {

    /**
     * @param array $filteredTables
     * @return array
     */
    public function getFilteredTablesInDependencyOrder(array $filteredTables): array {

        $availableTables = [];

        // Simula estructura para getPreparedFillDegree para no modificar los formatos de entrada
        $filteredTables = $this->mapToTableEntity($filteredTables);

        // Obtiene las tablas padre de las tablas filtradas
        /** @var Table $table */
        foreach ($filteredTables as $table) {

            if (!$parentTables = $this->getParentTables($table)) {
                continue;
            }

            /** @var Table $parentTable */
            foreach ($parentTables as $parentTable) {
                // Agrega las tablas padre al listado de tablas requeridas para que el algoritmo las ordene
                $availableTables[] = $parentTable->getName();
            }

        };

        // Mapea como tablas para simular getSourceSchemaTables() y modificarlos métodos de entrada
        $availableTables = $this->mapToTableEntity($availableTables);

        // Mezcla las tablas filtradas y las tablas padre
        $availableTables = array_merge_recursive($availableTables, $filteredTables);

        // Retrieve all tables on the information schema definition
        return $this->getPreparedFillDegree($availableTables);

    }

    /**
     * Construye un grafo de dependencias entre las tablas de la base de datos
     *
     * @return array
     * @throws QueryOrderException
     */
    public function getTablesInDependencyOrder(): array {

        // Retrieve all tables on the information schema definition
        if (!$tables = $this->getSourceSchemaTables()) {
            return [];
        }

        return $this->getPreparedFillDegree($tables);

    }

    private function getPreparedFillDegree(array $tables): array {

        // apply fix to adapt to the algorithm
        $preparedFillTables = array_map(function ($table) {
                /** @var Table $table */
                return $table->getName();
            }, $tables
        );

        // Obtener las claves foráneas de las tablas filtradas
        $stmt = $this->sourceSchema->getDatabaseConnection()->prepare(
            sprintf(
                QueryHelper::REFERENCED_PARENT_TABLE_NAME_QUERY, $this->getTablesToWhereIn($preparedFillTables)
            )
        );

        $stmt->execute(['schema' => $this->sourceSchema]);
        if ($foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC)) {

            // Además de las tablas padre, también debemos obtener las tablas padre de las padre (REFERENCED_TABLE_NAME)
            foreach ($foreignKeys as $foreignKey) {
                $preparedFillTables[] = $foreignKey['REFERENCED_TABLE_NAME'];
            }

            // Obtenemos los valores únicos (evitar cyclic)
            $preparedFillTables = array_unique($preparedFillTables);

            // Inicializar el grafo y los grados de entrada para estructurar el orden de ejecución de las tablas
            $graph    = array_fill_keys($preparedFillTables, []);
            $inDegree = array_fill_keys($preparedFillTables, 0);

            // Construir el grafo de dependencias
            foreach ($foreignKeys as $fk) {
                $table = $fk['TABLE_NAME'];
                $referencedTable = $fk['REFERENCED_TABLE_NAME'];
                if (!in_array($referencedTable, $graph[$table])) {
                    $graph[$table][] = $referencedTable;
                    // Incrementar el grado de la tabla que tiene la clave foránea
                    $inDegree[$table]++;
                }
            }

            // Mantener el formato de entrada de getTopologicalSort para las tablas
            $tables = $this->mapToTableEntity($preparedFillTables);

            return $this->getTopologicalSort($tables, $graph, $inDegree);

        }

        return [];

    }

    private function getTopologicalSort(array $availableTables, array $graph, array $inDegree): array {
        $queue = [];
        $sortedTables = [];
        $processedTables = [];

        // Inicializar la cola con tablas sin dependencias
        foreach ($availableTables as $table) {
            if ($inDegree[$table->getName()] == 0) {
                $queue[] = $table;
            }
        }

        while (!empty($queue) || count($sortedTables) < count($availableTables)) {
            if (empty($queue)) {
                // Si la cola está vacía pero aún quedan tablas, tomamos la de menor grado
                $minDegree = min(array_diff_key($inDegree, array_flip($processedTables)));
                foreach ($inDegree as $tableName => $degree) {
                    if ($degree == $minDegree && !in_array($tableName, $processedTables)) {
                        $queue[] = $this->getTableByName($availableTables, $tableName);
                        break;
                    }
                }
            }

            /** @var Table $table */
            $table = array_shift($queue);
            $sortedTables[] = $table;
            $processedTables[] = $table->getName();

            if (isset($graph[$table->getName()])) {
                foreach ($graph[$table->getName()] as $dependentTable) {
                    $inDegree[$dependentTable]--;
                    if ($inDegree[$dependentTable] == 0 && !in_array($dependentTable, $processedTables)) {
                        $queue[] = $this->getTableByName($availableTables, $dependentTable);
                    }
                }
            }
        }

        return $sortedTables;

    }

    private function getTableByName(array $tables, string $name): ?Table {
        foreach ($tables as $table) {
            if ($table->getName() === $name) {
                return $table;
            }
        }
        return null;
    }

}