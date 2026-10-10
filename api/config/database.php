<?php

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // Load environment variables if needed (e.g. via getenv or a simple parser)
            // For this phase, we use basic fallback to default XAMPP credentials
            
            $host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'sql310.infinityfree.com');
            $port = getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: '3306');
            $dbName = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'if0_43123445_myattenda');
            $user = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'if0_43123445');
            $pass = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASS') ?: 'LysPup52CVK');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return rows as associative arrays
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
                PDO::ATTR_STRINGIFY_FETCHES  => false,                  // Don't convert numeric values to strings
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
                date_default_timezone_set('Africa/Lagos');
                self::$instance->exec("SET time_zone = '+01:00'");
            } catch (PDOException $e) {
                // In production, log the error rather than displaying it
                error_log("Database Connection Error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(["error" => "Internal Server Error - Database connection failed"]);
                exit;
            }
        }
        return self::$instance;
    }
}
