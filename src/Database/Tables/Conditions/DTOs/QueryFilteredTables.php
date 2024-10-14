<?php

namespace Dankkomcg\MySQL\Sync\Database\Tables\Conditions\DTOs;

use Dankkomcg\MySQL\Sync\Database\Models\Table;

class QueryFilteredTables {

    /**
     * @var array
     */
    private array $tables;

    public function __construct(array $tables = []) {
        $this->setFilteredTables($tables);
    }

    /**
     * Tables which is not synchronized between origin and destiny
     *
     * @param array $tables
     * @return void
     */
    private function setFilteredTables(array $tables): void {

        $this->tables = array_map(function (string $table) {
                return new Table($table);
            }, $tables
        );

    }

    /**
     * Get tables parsed to object
     *
     * @return array
     */
    public function getTables(): array {
        return $this->tables;
    }

}