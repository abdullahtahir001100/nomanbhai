<?php
// includes/config.php - Central Database & System Configuration for Smart Mobile ERP

/**
 * =========================================================================
 * DATABASE CONFIGURATION
 * =========================================================================
 * For Localhost (XAMPP):
 *   DB_HOST: localhost
 *   DB_USER: root
 *   DB_PASS: ''
 *   DB_NAME: smartmobile_erp
 * 
 * For Live Hosting (cPanel / DirectAdmin / VPS):
 *   Update with your cPanel Database Name, User, and Password:
 *   e.g. DB_USER: 'smartmob_user'
 *        DB_PASS: 'YourLivePassword123'
 *        DB_NAME: 'smartmob_erp'
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'smartmobile_erp');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');

// Application Environment
define('APP_NAME', 'Smart Mobile ERP');
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // 'development' or 'production'

// Error Reporting Configuration
if (APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '1');
}
