<?php
// includes/db.php - PDO Database Connection & Synchronization Layer for Smart Mobile ERP
require_once __DIR__ . '/config.php';

/**
 * Get PDO Database Connection (Singleton)
 */
function get_db(): ?PDO {
    static $pdo = null;
    static $hasFailed = false;

    if ($pdo !== null) {
        return $pdo;
    }

    if ($hasFailed) {
        return null;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        $hasFailed = true;
        error_log("Database Connection Warning: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if Database Connection is active
 */
function is_db_connected(): bool {
    return get_db() !== null;
}
