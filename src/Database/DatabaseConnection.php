<?php

namespace Dankkomcg\MySQL\Sync\Database;

use Dankkomcg\MySQL\Sync\Exceptions\DatabaseConnectionException;
use Exception;
use PDO;
use PDOException;

class DatabaseConnection
{
    private PDO $pdo;

    /**
     * @throws Exception
     */
    public function __construct(string $host, string $username, string $password, string $schema) {
        try {

            $dsn = "mysql:host=$host;dbname=$schema;charset=utf8mb4";
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {
            throw new DatabaseConnectionException($e->getMessage());
        }
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

}