<?php

namespace Dankkomcg\MySQL\Sync\Database\Models;

use Dankkomcg\MySQL\Sync\Database\DatabaseConnection;

final class TemplateSchema extends Schema {

    /**
     * @var array
     */
    private array $tables;

    /**
     * @param string $schemaName
     * @param DatabaseConnection $databaseConnection
     * @param ?array $tables
     */
    public function __construct(string $schemaName, DatabaseConnection $databaseConnection, array $tables = []) {

        parent::__construct($schemaName, $databaseConnection);

        if (!empty($tables)) {
            $this->tables = $tables;
        }

    }

    /**
     * @return array
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @return bool
     */
    public function isPartialSchema(): bool {
        return !empty($this->tables);
    }

}