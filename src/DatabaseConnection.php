<?php

namespace Dankkomcg\MySQL\Sync;

use PDO;
use PDOException;
use Exception;

// Clase para gestionar la conexión a la base de datos
class DatabaseConnection
{
    private $pdo;

    public function __construct($host, $username, $password, $schema)
    {
        try {
            $dsn = "mysql:host=$host;dbname=$schema;charset=utf8mb4";
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Error en la conexión a la base de datos: " . $e->getMessage());
        }
    }

    public function getPdo()
    {
        return $this->pdo;
    }
}