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

// Registered wholesale clients directory
$wholesale_clients = [
    '0300-1234567' => ['name' => 'Ali Electronics', 'pin' => '1234', 'area' => 'Hall Road, Lahore'],
    '0321-7654321' => ['name' => 'Usman Traders', 'pin' => '1234', 'area' => 'Hafeez Centre, Lahore'],
    '0333-9876543' => ['name' => 'Khan Mobile Zone', 'pin' => '1234', 'area' => 'Karkhano, Peshawar'],
    '0345-1122334' => ['name' => 'Bilal Telecom', 'pin' => '1234', 'area' => 'Saddar, Karachi'],
];

// Normalize phone number for flexible matching
$cleanInputPhone = preg_replace('/[^0-9]/', '', $phone);

$matchedClient = null;
foreach ($wholesale_clients as $cPhone => $clientData) {
    $cleanClientPhone = preg_replace('/[^0-9]/', '', $cPhone);
    if ($cleanInputPhone === $cleanClientPhone || $phone === $cPhone) {
        $matchedClient = $clientData;
        break;
    }
}

if ($matchedClient) {
    if ($pin === $matchedClient['pin']) {
        echo json_encode([
            'success'     => true,
            'client_name' => $matchedClient['name'],
            'area'        => $matchedClient['area'],
            'message'     => 'Wholesale Client Verified! Special rates unlocked.'
        ]);
        exit;
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Ghalat PIN! Barah-e-karam durust PIN darj karein (Default: 1234).'
        ]);
        exit;
    }
} else {
    // If phone not explicitly listed, allow verification if PIN is 1234
    if ($pin === '1234') {
        echo json_encode([
            'success'     => true,
            'client_name' => 'Verified Dealer (' . htmlspecialchars($phone) . ')',
            'area'        => 'Wholesale Dealer',
            'message'     => 'Wholesale Client Verified! Special rates unlocked.'
        ]);
        exit;
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Ghalat PIN! Durust PIN 1234 darj karein.'
        ]);
        exit;
    }
}
