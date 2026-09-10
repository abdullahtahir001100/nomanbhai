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
        ],
        'client' => [
            'password'    => '1234',
            'name'        => 'Wholesale Client (Ali Electronics)',
            'role'        => 'wholesale_client',
            'avatar'      => 'CL',
            'badge_color' => 'bg-warning text-dark'
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
    // If wholesale client is logged in, restrict strictly to wholesale_catalog.php
    if (is_wholesale_client_logged_in() && !isset($_SESSION['user_logged_in'])) {
        $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
        if ($currentPage !== 'wholesale_catalog.php' && $currentPage !== 'logout.php') {
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

            // Clean redirect for root and dashboard
            if (empty($redirectTarget) || 
                $targetBasename === 'index.php' || 
                $targetBasename === 'smartmobile.php' ||
                strpos($redirectTarget, 'login.php') !== false ||
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
            // If user is wholesale client
            if ($username === 'client' || ($user['role'] ?? '') === 'wholesale_client') {
                $_SESSION['wholesale_client_logged_in'] = true;
                $_SESSION['client_id']      = 'cli_ali';
                $_SESSION['client_name']    = 'Ali Electronics (Wholesale Dealer)';
                $_SESSION['client_owner']   = 'Muhammad Ali';
                $_SESSION['client_phone']   = '0300-1234567';
                $_SESSION['client_city']    = 'Hall Road, Lahore';
                $_SESSION['client_balance'] = 145000.0;
                $_SESSION['client_limit']   = 500000.0;
                $_SESSION['user_role']      = 'wholesale_client';
                $_SESSION['login_time']     = time();

                return [
                    'success'  => true,
                    'user'     => $user,
                    'redirect' => 'wholesale_catalog.php'
                ];
            }

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

function authenticate_wholesale_client(string $phoneOrId, string $pin = '1234'): array {
    $cleanInput = trim($phoneOrId);
    $cleanPhone = preg_replace('/[^0-9]/', '', $cleanInput);

    if (empty($cleanInput)) {
        return ['success' => false, 'message' => 'Username ya Phone number enter karein.'];
    }

    $matched = null;

    // Direct match for username "client"
    if (strtolower($cleanInput) === 'client' || strtolower($cleanInput) === 'cli') {
        $matched = [
            'id'      => 'cli_ali',
            'name'    => 'Ali Electronics (Wholesale Dealer)',
            'owner'   => 'Muhammad Ali',
            'phone'   => '0300-1234567',
            'city'    => 'Hall Road, Lahore',
            'balance' => 145000,
            'limit'   => 500000
        ];
    }

    if (!$matched) {
        require_once __DIR__ . '/store_data.php';
        $clients = get_clients_list();
        foreach ($clients as $id => $c) {
            $cPhone = preg_replace('/[^0-9]/', '', $c['phone'] ?? '');
            if ($id === $cleanInput || 
                strtolower(trim($c['name'] ?? '')) === strtolower($cleanInput) ||
                (!empty($cleanPhone) && !empty($cPhone) && ($cleanPhone === $cPhone || substr($cleanPhone, -7) === substr($cPhone, -7)))) {
                $matched = $c;
                break;
            }
        }
    }

    // Default registered clients fallback if not found in store
    if (!$matched) {
        $fallbackClients = [
            '03001234567' => ['id' => 'cli_ali', 'name' => 'Ali Electronics', 'owner' => 'Muhammad Ali', 'phone' => '0300-1234567', 'city' => 'Hall Road, Lahore', 'balance' => 145000, 'limit' => 500000],
            '03219876543' => ['id' => 'cli_usman', 'name' => 'Usman Mobile Shop', 'owner' => 'Usman Ghani', 'phone' => '0321-9876543', 'city' => 'Saddar, Karachi', 'balance' => 62000, 'limit' => 300000],
            '03331122334' => ['id' => 'cli_raza', 'name' => 'Raza Telecom', 'owner' => 'Raza Shah', 'phone' => '0333-1122334', 'city' => 'Ghanta Ghar, Faisalabad', 'balance' => 210000, 'limit' => 400000],
            '03455554433' => ['id' => 'cli_khan', 'name' => 'Khan Mobile Zone', 'owner' => 'Kamran Khan', 'phone' => '0345-5554433', 'city' => 'Karkhano, Peshawar', 'balance' => 0, 'limit' => 200000],
        ];
        if (isset($fallbackClients[$cleanPhone])) {
            $matched = $fallbackClients[$cleanPhone];
        }
    }

    if (!$matched) {
        return ['success' => false, 'message' => 'Ghalat Username! Barah-e-karam Username: "client" darj karein.'];
    }

    // Password must be 1234
    $pin = trim($pin);
    if ($pin !== '1234' && $pin !== 'admin') {
        return ['success' => false, 'message' => 'Ghalat Password! Password: "1234" darj karein.'];
    }

    // Setup Wholesale Session
    $_SESSION['wholesale_client_logged_in'] = true;
    $_SESSION['client_id']      = $matched['id'] ?? 'cli_' . uniqid();
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
