<?php

declare(strict_types=1);

namespace DonFactura\DTE\Core;

use PDO;
use PDOException;

/**
 * Clase para manejo de conexión a base de datos
 */
class Database
{
    private PDO $connection;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    private function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset']
        );

        try {
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
        } catch (PDOException $e) {
            throw new \RuntimeException(
                'Error de conexión a la base de datos: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollback(): bool
    {
        return $this->connection->rollback();
    }

    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }
}
