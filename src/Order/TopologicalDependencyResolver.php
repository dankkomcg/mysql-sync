<?php

namespace Dankkomcg\MySQL\Sync\Order;

use Dankkomcg\MySQL\Sync\Exceptions\QueryOrderException;
use PDO;

class TopologicalDependencyResolver extends DependencyResolver {

    /**
     * Construye un grafo de dependencias entre las tablas de la base de datos
     *
     * @return array
     * @throws QueryOrderException
     */
    public function getTablesInDependencyOrder(): array {

        // Retrieve all tables on the information schema definition
        $tables = $this->getSourceSchemaTables();

        // Inicializar el grafo y los grados de entrada
        $graph = array_fill_keys($tables, []);
        $inDegree = array_fill_keys($tables, 0);

        // Obtener las relaciones de claves foráneas
        $stmt = $this->sourcePdo->prepare(self::FOREIGN_ORDERED_INFORMATION_SCHEMA_TABLES);
        $stmt->execute(['schema' => $this->sourceSchema]);
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Construir el grafo de dependencias
        foreach ($foreignKeys as $fk) {
            $table = $fk['TABLE_NAME'];
            $referencedTable = $fk['REFERENCED_TABLE_NAME'];
            if (!in_array($referencedTable, $graph[$table])) {
                $graph[$table][] = $referencedTable;
                $inDegree[$referencedTable]++;
            }
        }

        return $this->getTopologicalSort(
            $tables, $graph, $inDegree
        );

    }

    /**
     * Realiza un ordenamiento topológico de las tablas basado en sus dependencias
     *
     * @param array $availableTables
     * @param array $graph
     * @param array $inDegree
     * @return array
     */
    private function getTopologicalSort(array $availableTables, array $graph, array $inDegree): array {

        $queue = [];
        foreach ($availableTables as $table) {
            if ($inDegree[$table] == 0) {
                $queue[] = $table;
            }
        }

        $sortedTables = [];
        while (!empty($queue)) {
            $table = array_shift($queue);
            $sortedTables[] = $table;

            foreach ($graph[$table] as $dependentTable) {
                $inDegree[$dependentTable]--;
                if ($inDegree[$dependentTable] == 0) {
                    $queue[] = $dependentTable;
                }
            }
        }

        // Verificar si hay ciclos
        if (count($sortedTables) != count($availableTables)) {
            
            $remainingTables = array_diff($availableTables, $sortedTables);
            $this->logger()
                ->warning(
                    "Warning: cyclic dependencies in the tables: " . implode(", ", $remainingTables)
                )
            ;
            
            // Agregar las tablas restantes al final
            $sortedTables = array_merge($sortedTables, $remainingTables);
        }

        // Invertir el orden para que las tablas dependientes estén al final
        return array_reverse($sortedTables);

    }

}