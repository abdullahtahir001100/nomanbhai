
<?php
// includes/config.php - Central Database & System Configuration for Smart Mobile ERP

/**
 * =========================================================================
 * DATABASE CONFIGURATION
 * =========================================================================
 * Stackhero MySQL
 */

define('DB_HOST', '4qjhqi.stackhero-network.com');
define('DB_NAME', 'root');
define('DB_USER', 'root');
define('DB_PASS', 'ZgCJr0Kr0almVBYmM3WhjMjCaDxeZNM4');
define('DB_PORT', '4751');
define('DB_CHARSET', 'utf8mb4');

// Application Environment
define('APP_NAME', 'Smart Mobile ERP');
define('APP_ENV', 'development');

// Error Reporting Configuration
if (APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '1');
}
