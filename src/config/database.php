<?php
// src/config/database.php

class Database {
    private $host = '127.0.0.1';
    private $dbname = 'login_system';
    private $username = 'root';
    private $password = ''; // XAMPP default is empty; update if you set a password
    private $charset = 'utf8mb4';

    private $pdo;

    public function __construct() {
        // Allow overriding via environment variables if present
        $this->host = getenv('DB_HOST') ?: $this->host;
        $this->dbname = getenv('DB_NAME') ?: $this->dbname;
        $this->username = getenv('DB_USER') ?: $this->username;
        $envPass = getenv('DB_PASS');
        if ($envPass !== false) { $this->password = $envPass; }
        $this->connect();
    }

    private function connect(): void {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Query failed: ' . $e->getMessage());
            throw new Exception('Database query failed');
        }
    }
}

// Global database instance
$db = new Database();
