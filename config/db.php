<?php
/**
 * Database Configuration
 * Establishes and provides PDO database connection with proper error handling.
 */

// Set default timezone to WITA (UTC+8) for South Sulawesi
date_default_timezone_set('Asia/Makassar');

function getDBConnection()
{
    static $pdo = null;

    if ($pdo === null) {
        if (!class_exists('PDO')) {
            die('Error: Database extension PDO tidak aktif di server ini. Silakan hubungi administrator hosting Anda.');
        }

        // Load .env file if it exists at project root
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Skip comments or lines without '='
                    if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                        continue;
                    }
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);
                    // Strip quotes if wrapped
                    if (preg_match('/^"([^"]*)"$/', $value, $matches) || preg_match('/^\'([^\']*)\'$/', $value, $matches)) {
                        $value = $matches[1];
                    }
                    
                    if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                        if (function_exists('putenv')) {
                            @putenv("{$name}={$value}");
                        }
                        $_ENV[$name] = $value;
                        $_SERVER[$name] = $value;
                    }
                }
            }
        }

        // Fetch credentials from environment variables with fallback
        $host = $_ENV['DB_HOST'] ?? (function_exists('getenv') ? getenv('DB_HOST') : null) ?: 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? (function_exists('getenv') ? getenv('DB_NAME') : null) ?: 'steven_it_shop';
        $username = $_ENV['DB_USER'] ?? (function_exists('getenv') ? getenv('DB_USER') : null) ?: 'root';
        $passwordEnv = function_exists('getenv') ? getenv('DB_PASS') : false;
        $password = $_ENV['DB_PASS'] ?? ($passwordEnv !== false ? $passwordEnv : '');
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            // Ensure database session timezone matches Asia/Makassar (WITA, UTC+8)
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            die('Terjadi kesalahan koneksi database. Silakan coba lagi nanti.');
        }
    }

    return $pdo;
}
