<?php

namespace Db_config;

/**
 * Database connection and configuration management class.
 */
class Database
{
    private static ?DatabaseConfig $config = null;

    /**
     * Get the database configuration instance.
     *
     * @return DatabaseConfig
     */
    public static function getConfig(): DatabaseConfig
    {
        if (self::$config === null) {
            self::$config = new DatabaseConfig();
        }

        return self::$config;
    }

    public static function connect(): \mysqli
    {
        $config = self::getConfig();
        $host = $config->getHost();
        $username = $config->getUsername();
        $password = $config->getPassword();
        $database = $config->getDatabase();
        $port = $config->getPort();

        try {
            $connection = new \mysqli($host, $username, $password, $database, $port);
        } catch (\mysqli_sql_exception $e) {
            die("Connection failed: " . htmlspecialchars($e->getMessage()));
        }

        return $connection;
    }
}

/**
 * Database configuration class.
 */
class DatabaseConfig
{
    private static ?string $host = null;
    private static ?string $username = null;
    private static ?string $password = null;
    private static ?string $database = null;
    private static ?int $port = null;

    public function __construct()
    {
        // Load environment variables from .env file
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env');
        $dotenv->load();

        // Load configuration from environment variables
        self::$host = $_ENV['DB_HOST'] ?: 'localhost';
        self::$port = (int) ($_ENV['DB_PORT'] ?: 3306);
        self::$database = $_ENV['DB_NAME'] ?: $_ENV['DB_NAME'] ?: 'my_database';
        self::$username = $_ENV['DB_USER'] ?: $_ENV['DB_USER'] ?: 'root';
        self::$password = $_ENV['DB_PASSWORD'] ?: '';
    }

    // Getters for database configuration properties
    // Give the instance configuration properties, and if they are null, 
    // load them from environment variables with default values.

    public static function getHost(): string
    {
        if (self::$host === null) {
            //warn if the host is null, load it from environment variables with a default value of 'localhost'.
            trigger_error("DB_HOST is not set, using default value 'localhost'", E_USER_WARNING);
            self::$host = $_ENV['DB_HOST'] ?: 'localhost';
        }


        return self::$host;
    }

    public static function getUsername(): string
    {
        if (self::$username === null) {
            trigger_error("DB_USER is not set, using default value 'root'", E_USER_WARNING);
            self::$username = $_ENV['DB_USER'] ?: 'root';
        }

        return self::$username;
    }

    public static function getPassword(): string
    {
        if (self::$password === null) {
            trigger_error("DB_PASSWORD is not set, using default value ''", E_USER_WARNING);
            self::$password = $_ENV['DB_PASSWORD'] ?: '';
        }

        return self::$password;
    }

    public static function getDatabase(): string
    {
        if (self::$database === null) {
            trigger_error("DB_NAME is not set, using default value 'my_database'", E_USER_WARNING);
            self::$database = $_ENV['DB_NAME'] ?: 'my_database';
        }

        return self::$database;
    }

    public static function getPort(): int
    {
        if (self::$port === null) {
            trigger_error("DB_PORT is not set, using default value 3306", E_USER_WARNING);
            self::$port = (int) ($_ENV['DB_PORT'] ?: 3306);
        }

        return self::$port;
    }
}
