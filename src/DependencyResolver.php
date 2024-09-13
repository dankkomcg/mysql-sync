<?php

namespace Dankkomcg\MySQL\Sync;

use PDO;

class DependencyResolver extends Loggable
{
    private $graph;
    private $inDegree;
    private $tables;

    public function getTablesInDependencyOrder($pdo, $schema)
    {
        $this->buildDependencyGraph($pdo, $schema);
        return $this->topologicalSort();
    }

    private function buildDependencyGraph($pdo, $schema)
    {
        // Obtener todas las tablas del esquema
        $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_TYPE = 'BASE TABLE'";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['schema' => $schema]);
        $this->tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Inicializar el grafo y los grados de entrada
        $this->graph = array_fill_keys($this->tables, []);
        $this->inDegree = array_fill_keys($this->tables, 0);

        // Obtener las relaciones de claves foráneas
        $fkQuery = "
        SELECT
            TABLE_NAME,
            REFERENCED_TABLE_NAME
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            TABLE_SCHEMA = :schema
            AND REFERENCED_TABLE_SCHEMA = :schema
            AND REFERENCED_TABLE_NAME IS NOT NULL";
        $stmt = $pdo->prepare($fkQuery);
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

    private function topologicalSort()
    {
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
            $this->logger()->warning("Advertencia: Se detectaron dependencias cíclicas en las siguientes tablas: " . implode(", ", $remainingTables));
            
            // Agregar las tablas restantes al final
            $sortedTables = array_merge($sortedTables, $remainingTables);
        }

        // Invertir el orden para que las tablas dependientes estén al final
        return array_reverse($sortedTables);
    }
}