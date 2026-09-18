<?php
// ajax_wholesale_auth.php - Wholesale Client Verification via AJAX
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/auth.php';

// Must be logged in to store ERP to access wholesale verification
if (!is_logged_in()) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Pehle system mein login karein.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$phone = trim($_POST['phone'] ?? '');
$pin   = trim($_POST['pin'] ?? '');

if (empty($phone) || empty($pin)) {
    echo json_encode([
        'success' => false,
        'message' => 'Phone number aur Secret PIN dono darj karein.'
    ]);
    exit;
}

// Authenticate using central wholesale authentication logic
$authResult = authenticate_wholesale_client($phone, $pin);

if ($authResult['success']) {
    $c = $authResult['client'];
    echo json_encode([
        'success'     => true,
        'client_name' => $c['name'],
        'area'        => $c['city'] ?? 'Wholesale Market',
        'message'     => 'Wholesale Client Verified! Special dealer rates unlocked.'
    ]);
    exit;
} else {
    echo json_encode([
        'success' => false,
        'message' => $authResult['message']
    ]);
    exit;
}
