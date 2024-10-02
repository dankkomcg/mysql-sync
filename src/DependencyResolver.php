<?php

namespace Dankkomcg\MySQL\Sync;

use PDO;

class DependencyResolver {

    use Loggable;

    private array $graph;
    private array $inDegree;
    private ?array $tables;

    private const INFORMATION_SCHEMA_TABLES_QUERY =
        "SELECT 
                TABLE_NAME 
            FROM 
                INFORMATION_SCHEMA.TABLES 
            WHERE 
                TABLE_SCHEMA = :schema AND TABLE_TYPE = 'BASE TABLE'
                "
    ;

    private const FOREIGN_ORDERED_INFORMATION_SCHEMA_TABLES =
        "SELECT
                TABLE_NAME, REFERENCED_TABLE_NAME
            FROM
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE
                TABLE_SCHEMA = :schema
                AND REFERENCED_TABLE_SCHEMA = :schema
                AND REFERENCED_TABLE_NAME IS NOT NULL
                "
    ;

    public function getTablesInDependencyOrder(PDO $pdo, string $schema): array {
        $this->buildDependencyGraph($pdo, $schema);
        return $this->topologicalSort();
    }

    private function buildDependencyGraph(PDO $pdo, string $schema) {

        // Retrieve all tables on the information schema definition
        $stmt = $pdo->prepare(self::INFORMATION_SCHEMA_TABLES_QUERY);
        $stmt->execute(['schema' => $schema]);

        if($this->tables = $stmt->fetchAll(PDO::FETCH_COLUMN)) {

            // Inicializar el grafo y los grados de entrada
            $this->graph = array_fill_keys($this->tables, []);
            $this->inDegree = array_fill_keys($this->tables, 0);

            // Obtener las relaciones de claves foráneas
            $stmt = $pdo->prepare(self::FOREIGN_ORDERED_INFORMATION_SCHEMA_TABLES);
            $stmt->execute(['schema' => $schema]);
            $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Construir el grafo de dependencias
            foreach ($foreignKeys as $fk) {
                $table = $fk['TABLE_NAME'];
                $referencedTable = $fk['REFERENCED_TABLE_NAME'];
                if (!in_array($referencedTable, $this->graph[$table])) {
                    $this->graph[$table][] = $referencedTable;
                    $this->inDegree[$referencedTable]++;
                }
            }

        }

    }

    private function topologicalSort(): array {

        $queue = [];
        foreach ($this->tables as $table) {
            if ($this->inDegree[$table] == 0) {
                $queue[] = $table;
            }
        }

        $sortedTables = [];
        while (!empty($queue)) {
            $table = array_shift($queue);
            $sortedTables[] = $table;

            foreach ($this->graph[$table] as $dependentTable) {
                $this->inDegree[$dependentTable]--;
                if ($this->inDegree[$dependentTable] == 0) {
                    $queue[] = $dependentTable;
                }
            }
        }

        // Verificar si hay ciclos
        if (count($sortedTables) != count($this->tables)) {
            
            $remainingTables = array_diff($this->tables, $sortedTables);
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