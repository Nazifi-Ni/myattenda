<?php

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        // Helper to grab env vars from Apache/Nixpacks reliably
        $getEnvVar = function($key) {
            if (!empty($_SERVER[$key])) return $_SERVER[$key];
            if (!empty($_ENV[$key])) return $_ENV[$key];
            $val = getenv($key);
            if ($val !== false && $val !== '') return $val;
            return null;
        };

        if (self::$instance === null) {
            $host = $getEnvVar('MYSQLHOST') ?: ($getEnvVar('DB_HOST') ?: 'sql310.infinityfree.com');
            $port = $getEnvVar('MYSQLPORT') ?: ($getEnvVar('DB_PORT') ?: '3306');
            $dbName = $getEnvVar('MYSQLDATABASE') ?: ($getEnvVar('DB_NAME') ?: 'if0_43123445_myattenda');
            $user = $getEnvVar('MYSQLUSER') ?: ($getEnvVar('DB_USER') ?: 'if0_43123445');
            $pass = $getEnvVar('MYSQLPASSWORD') ?: ($getEnvVar('DB_PASS') ?: 'LysPup52CVK');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return rows as associative arrays
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
                PDO::ATTR_STRINGIFY_FETCHES  => false,                  // Don't convert numeric values to strings
            ];

            // If connecting to cloud providers requiring SSL (like Aiven or TiDB)
            if ($getEnvVar('DB_SSL') === 'true' || strpos($host, 'aivencloud.com') !== false) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
                date_default_timezone_set('Africa/Lagos');
                self::$instance->exec("SET time_zone = '+01:00'");
            } catch (PDOException $e) {
                // In production, log the error rather than displaying it
                error_log("Database Connection Error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    "error" => "Internal Server Error - Database connection failed",
                    "details" => $e->getMessage(),
                    "host_attempted" => $host
                ]);
                exit;
            }
        }
        return self::$instance;
    }
}
