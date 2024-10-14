<?php

namespace Dankkomcg\MySQL\Sync\Database\Models;

use Dankkomcg\MySQL\Sync\Database\DatabaseConnection;

final class TargetSchema extends Schema {

    public function __construct(string $schemaName, DatabaseConnection $databaseConnection)
    {
        parent::__construct($schemaName, $databaseConnection);
    }

}