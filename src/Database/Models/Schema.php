<?php

namespace Dankkomcg\MySQL\Sync\Database\Models;

use Dankkomcg\MySQL\Sync\Database\DatabaseConnection;

class Schema {

    /**
     * @var string
     */
    protected string $schemaName;

    /**
     * @var DatabaseConnection
     */
    protected DatabaseConnection $connection;

    /**
     * @param string $schemaName
     * @param DatabaseConnection $databaseConnection
     */
    public function __construct(string $schemaName, DatabaseConnection $databaseConnection) {

        $this->schemaName      = $schemaName;
        $this->connection = $databaseConnection;

    }

    /**
     * @return string
     */
    public function getSchemaName(): string
    {
        return $this->schemaName;
    }

    /**
     * @return DatabaseConnection
     */
    public function getDatabaseConnection(): DatabaseConnection {
        return $this->connection;
    }

}