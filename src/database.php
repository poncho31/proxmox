<?php

require_once __DIR__ . '/env.php';

class Database
{
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    private $pdo;

    public function __construct()
    {
        // Charger les variables d'environnement
        Env::load();
        
        // Récupérer la configuration de la base de données
        $config = Env::getDatabaseConfig();
        
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->dbname = $config['dbname'];
        $this->username = $config['username'];
        $this->password = $config['password'];

        $this->connect();
    }

    private function connect()
    {
        try {
            $dsn = Env::getDatabaseDsn();
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}