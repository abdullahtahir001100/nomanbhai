<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$pages = [
    'index.php',
    'pos.php',
    'inventory.php',
    'repairs.php',
    'track.php',
    'clients.php',
    'ledgers.php',
    'dailyclosing.php',
    'wholesale_catalog.php',
    'login.php'
];

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';

foreach ($pages as $page) {
    echo "Testing $page... ";
    $_SERVER['SCRIPT_NAME'] = '/' . $page;
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['user_logged_in'] = true;
    $_SESSION['logged_user'] = [
        'username' => 'admin',
        'name' => 'Administrator',
        'role' => 'Store Manager',
        'avatar' => 'AD',
        'badge_color' => 'bg-danger'
    ];

    ob_start();
    try {
        include __DIR__ . '/../' . $page;
        $out = ob_get_clean();
        echo "OK (output length: " . strlen($out) . " bytes)\n";
    } catch (Throwable $e) {
        ob_end_clean();
        echo "ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}
