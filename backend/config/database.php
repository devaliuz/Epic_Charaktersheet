<?php
/**
 * Database Configuration
 * Singleton Pattern für Datenbank-Verbindung
 */

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $driver = getenv('DB_DRIVER') ?: 'pgsql'; // 'pgsql' oder 'mysql'
        $host = getenv('DB_HOST') ?: 'postgres';
        $dbname = getenv('DB_DATABASE') ?: 'dnd_charsheet';
        $username = getenv('DB_USERNAME') ?: 'dnd_user';
        $password = getenv('DB_PASSWORD') ?: 'dnd_password';
        $port = getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
        
        try {
            if ($driver === 'pgsql') {
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            } else {
                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            }

            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ]);

            // Für PostgreSQL: auf 'app' Schema umschalten
            if ($driver === 'pgsql') {
                $this->connection->exec("SET search_path TO app, public");
            }
        } catch (PDOException $e) {
            error_log("Datenbank-Verbindungsfehler: " . $e->getMessage());
            throw new Exception("Verbindungsfehler zur Datenbank. Bitte prüfe die Konfiguration.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Verhindere Klonierung
    private function __clone() {}
    
    // Verhindere Deserialisierung
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
