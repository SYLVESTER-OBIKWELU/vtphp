<?php

namespace Core;

use PDO;
use PDOException;

class Database
{
    protected static $instance;
    protected $connection;
    protected $config;

    private function __construct($config)
    {
        $this->config = $config;
        $this->connect();
    }

    public static function getInstance($config = null)
    {
        if (!self::$instance) {
            if (!$config) {
                throw new \Exception("Database configuration is required");
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    protected function connect()
    {
        try {
            $driver = $this->config['driver'] ?? 'mysql';
            $host = $this->config['host'] ?? 'localhost';
            $port = $this->config['port'] ?? 3306;
            $database = $this->config['database'] ?? '';
            $username = $this->config['username'] ?? 'root';
            $password = $this->config['password'] ?? '';
            $charset = $this->config['charset'] ?? 'utf8mb4';

            $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";

            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new \Exception("Query failed: " . $e->getMessage() . "\nSQL: {$sql}");
        }
    }

    public function select($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function selectOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($values), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, $values);
        return $this->connection->lastInsertId();
    }

    public function update($table, $data, $where)
    {
        $setParts = [];
        $values = [];

        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = ?";
            $values[] = $value;
        }

        $whereParts = [];
        foreach ($where as $column => $value) {
            $whereParts[] = "{$column} = ?";
            $values[] = $value;
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );

        $stmt = $this->query($sql, $values);
        return $stmt->rowCount();
    }

    public function delete($table, $where)
    {
        $whereParts = [];
        $values = [];

        foreach ($where as $column => $value) {
            $whereParts[] = "{$column} = ?";
            $values[] = $value;
        }

        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            $table,
            implode(' AND ', $whereParts)
        );

        $stmt = $this->query($sql, $values);
        return $stmt->rowCount();
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollback()
    {
        return $this->connection->rollBack();
    }

    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    public function table($table)
    {
        return new QueryBuilder($this, $table);
    }
}
