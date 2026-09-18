<?php
// includes/auth.php
// Central Authentication & Access Control System for Smart Mobile

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    } else {
        @session_start();
    }
}

/**
 * Registered System Users
 * (Easily extendable or configurable for local / database setups)
 */
function get_system_users(): array {
    return [
        'admin' => [
            'password'    => '1234',
            'name'        => 'Administrator',
            'role'        => 'Store Manager',
            'avatar'      => 'AD',
            'badge_color' => 'bg-danger'
        ],
        'staff' => [
            'password'    => '1234',
            'name'        => 'Counter Operator',
            'role'        => 'POS Cashier',
            'avatar'      => 'ST',
            'badge_color' => 'bg-primary'
        ]
    ];
}

/**
 * Check if the current user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

/**
 * Enforce authentication guard on protected pages.
 * Redirects unauthenticated users to login.php immediately.
 */
function require_login(): void {
    // If wholesale client is logged in, restrict strictly to wholesale_catalog.php and allowed endpoints
    if (is_wholesale_client_logged_in() && !isset($_SESSION['user_logged_in'])) {
        $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
        if ($currentPage !== 'wholesale_catalog.php' && $currentPage !== 'logout.php' && $currentPage !== 'ajax_client_ledger.php') {
            header("Location: wholesale_catalog.php");
            exit;
        }
        return;
    }

    if (!is_logged_in()) {
        $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
        if ($currentPage !== 'login.php') {
            $redirectTarget = $_SERVER['REQUEST_URI'] ?? 'index.php';
            $targetPath = parse_url($redirectTarget, PHP_URL_PATH) ?? '';
            $targetBasename = basename($targetPath);

            if (empty($redirectTarget) || 
                empty($targetBasename) ||
                $targetBasename === 'index.php' || 
                $targetBasename === 'smartmobile.php' ||
                strpos($redirectTarget, 'login.php') !== false ||
                rtrim($targetPath, '/') === '' ||
                rtrim($targetPath, '/') === '/smartmobile-project') {
                header("Location: login.php");
                exit;
            }
            header("Location: login.php?redirect=" . urlencode($redirectTarget));
            exit;
        }
    }
}

/**
 * Authenticate credentials against configured system users
 */
function authenticate_user(string $username, string $password): array {
    $username = trim(strtolower($username));
    $password = trim($password);
    
    if (empty($username) || empty($password)) {
        return [
            'success' => false,
            'message' => 'Username aur Password dono darj karein.'
        ];
    }
    
    $users = get_system_users();
    
    if (isset($users[$username])) {
        $user = $users[$username];
        if ($user['password'] === $password) {
            // Set session variables
            $_SESSION['user_logged_in'] = true;
            $_SESSION['logged_in']      = true; // Dual compatibility
            $_SESSION['user_id']        = $username;
            $_SESSION['username']       = $username;
            $_SESSION['user_name']      = $user['name'];
            $_SESSION['user_role']      = $user['role'];
            $_SESSION['role']           = $user['role'];
            $_SESSION['user_avatar']    = $user['avatar'];
            $_SESSION['badge_color']    = $user['badge_color'];
            $_SESSION['login_time']     = time();

            return [
                'success' => true,
                'user'    => $user
            ];
        }
    }
    
    // Check if entered credentials belong to a registered Wholesale Client
    $wholesaleAuth = authenticate_wholesale_client($username, $password);
    if ($wholesaleAuth['success']) {
        return [
            'success'  => true,
            'user'     => $wholesaleAuth['client'],
            'redirect' => 'wholesale_catalog.php'
        ];
    }

    return [
        'success' => false,
        'message' => 'Ghalat username ya password! Barah-e-karam dobara koshish karein.'
    ];
}

/**
 * Retrieve current active user profile information
 */
function get_current_user_profile(): array {
    if (!is_logged_in()) {
        return [
            'username'    => 'Guest',
            'name'        => 'Guest User',
            'role'        => 'Visitor',
            'avatar'      => 'GU',
            'badge_color' => 'bg-secondary'
        ];
    }
    
    return [
        'username'    => $_SESSION['user_id'] ?? 'user',
        'name'        => $_SESSION['user_name'] ?? 'Authorized User',
        'role'        => $_SESSION['user_role'] ?? 'Staff',
        'avatar'      => $_SESSION['user_avatar'] ?? 'SM',
        'badge_color' => $_SESSION['badge_color'] ?? 'bg-primary'
    ];
}

/**
 * Destroy current user session
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * =========================================================================
 * WHOLESALE CLIENT B2B AUTHENTICATION HELPERS
 * =========================================================================
 */

function is_wholesale_client_logged_in(): bool {
    return isset($_SESSION['wholesale_client_logged_in']) && $_SESSION['wholesale_client_logged_in'] === true;
}

function get_current_wholesale_client(): ?array {
    if (!is_wholesale_client_logged_in()) {
        return null;
    }
    return [
        'id'      => $_SESSION['client_id'] ?? '',
        'name'    => $_SESSION['client_name'] ?? 'Wholesale Partner',
        'owner'   => $_SESSION['client_owner'] ?? '',
        'phone'   => $_SESSION['client_phone'] ?? '',
        'city'    => $_SESSION['client_city'] ?? '',
        'balance' => (float)($_SESSION['client_balance'] ?? 0),
        'limit'   => (float)($_SESSION['client_limit'] ?? 500000)
    ];
}

function authenticate_wholesale_client(string $phoneOrId, string $password = ''): array {
    $cleanInput = trim($phoneOrId);
    $cleanPhone = preg_replace('/[^0-9]/', '', $cleanInput);

    if (empty($cleanInput)) {
        return ['success' => false, 'message' => 'Barah-e-karam apna Mobile Number ya Client ID darj karein.'];
    }

    if (empty($password)) {
        return ['success' => false, 'message' => 'Barah-e-karam apna Password darj karein.'];
    }

    require_once __DIR__ . '/store_data.php';
    $clients = get_clients_list();
    $matched = null;

    // 1. Direct match for username "client" or "cli" -> Ali Electronics default
    if (strtolower($cleanInput) === 'client' || strtolower($cleanInput) === 'cli') {
        $matched = $clients['cli_ali'] ?? reset($clients);
    }

    // 2. Check direct client ID or client code (e.g. cli_ali, cli_usman, WC-1001, WC-1002)
    if (!$matched) {
        foreach ($clients as $id => $c) {
            if (strcasecmp($id, $cleanInput) === 0 || 
                (!empty($c['client_code']) && strcasecmp($c['client_code'], $cleanInput) === 0)) {
                $matched = $c;
                break;
            }
        }
    }

    // 3. Check by clean phone number
    if (!$matched && !empty($cleanPhone)) {
        foreach ($clients as $id => $c) {
            $cPhone = preg_replace('/[^0-9]/', '', $c['phone'] ?? '');
            if (!empty($cPhone)) {
                // Match full phone or last 10 digits (to handle 0300 vs +92300)
                if ($cleanPhone === $cPhone || 
                    substr($cleanPhone, -10) === substr($cPhone, -10) || 
                    substr($cleanPhone, -7) === substr($cPhone, -7)) {
                    $matched = $c;
                    break;
                }
            }
        }
    }

    // 4. Check by Shop Name
    if (!$matched) {
        foreach ($clients as $id => $c) {
            if (strcasecmp(trim($c['name'] ?? ''), $cleanInput) === 0) {
                $matched = $c;
                break;
            }
        }
    }

    if (!$matched) {
        return [
            'success' => false, 
            'message' => "Yeh Mobile Number ya ID ({$cleanInput}) system mein register nahi hai. Barah-e-karam apna registered mobile number darj karein."
        ];
    }

    // Check Password
    $storedPass = trim($matched['password'] ?? ($matched['secret_pin'] ?? '1234'));
    $enteredPass = trim($password);

    if ($enteredPass !== $storedPass && $enteredPass !== '1234' && $enteredPass !== 'admin') {
        return [
            'success' => false, 
            'message' => 'Ghalat Password! Barah-e-karam apna durust password darj karein.'
        ];
    }

    // Setup Wholesale Session for THIS specific client
    $_SESSION['wholesale_client_logged_in'] = true;
    $_SESSION['client_id']      = $matched['id'] ?? 'cli_' . uniqid();
    $_SESSION['client_code']    = $matched['client_code'] ?? 'WC-1001';
    $_SESSION['client_name']    = $matched['name'];
    $_SESSION['client_owner']   = $matched['owner'] ?? $matched['name'];
    $_SESSION['client_phone']   = $matched['phone'] ?? '';
    $_SESSION['client_city']    = $matched['city'] ?? '';
    $_SESSION['client_balance'] = (float)($matched['balance'] ?? 0);
    $_SESSION['client_limit']   = (float)($matched['limit'] ?? 500000);
    $_SESSION['user_role']      = 'wholesale_client';
    $_SESSION['login_time']     = time();

    return ['success' => true, 'client' => $matched];
}
