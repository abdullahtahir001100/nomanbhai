<?php
// scratch/test_wholesale_clients.php
require_once __DIR__ . '/../includes/store_data.php';
require_once __DIR__ . '/../includes/auth.php';

echo "====================================================\n";
echo "TEST 1: Check Current Clients List\n";
echo "====================================================\n";
$clients = get_clients_list();
echo "Total clients found: " . count($clients) . "\n";
foreach ($clients as $cid => $c) {
    echo " - [{$c['client_code']}] {$c['name']} | Phone: {$c['phone']} | Pass: {$c['password']} | Bal: {$c['balance']}\n";
}

echo "\n====================================================\n";
echo "TEST 2: Add New Wholesale Client with Custom Phone & Password\n";
echo "====================================================\n";
$testPhone = '0312-7788990';
$randNum = rand(1000, 9999);
$testPhone = "0311-555{$randNum}";
$testPass  = 'secretTestPass123';
$newClientRes = add_client([
    'name'     => 'Bilal Mobile Centre ' . $randNum,
    'owner'    => 'Bilal Ahmed',
    'phone'    => $testPhone,
    'password' => $testPass,
    'city'     => 'Faisalabad',
    'limit'    => 600000,
    'balance'  => 125000
]);

if ($newClientRes['success']) {
    echo "SUCCESS: Added client '{$newClientRes['client']['name']}' with ID {$newClientRes['client']['id']} and Code {$newClientRes['client']['client_code']}\n";
    $newClientId = $newClientRes['client']['id'];
} else {
    echo "ERROR: " . $newClientRes['message'] . "\n";
    exit(1);
}

echo "\n====================================================\n";
echo "TEST 3: Authenticate as New Client (Phone: {$testPhone}, Pass: {$testPass})\n";
echo "====================================================\n";
$authRes = authenticate_wholesale_client($testPhone, $testPass);
if ($authRes['success']) {
    echo "SUCCESS: Logged in as '{$_SESSION['client_name']}'!\n";
    echo " - Client ID: {$_SESSION['client_id']}\n";
    echo " - Client Phone: {$_SESSION['client_phone']}\n";
    echo " - Client City: {$_SESSION['client_city']}\n";
    echo " - Khata Udhaar: {$_SESSION['client_balance']}\n";
    assert(str_starts_with($_SESSION['client_name'], 'Bilal Mobile Centre'));
} else {
    echo "FAILED AUTH: " . $authRes['message'] . "\n";
    exit(1);
}

echo "\n====================================================\n";
echo "TEST 4: Authenticate with WRONG Password (Must Fail)\n";
echo "====================================================\n";
$wrongPassRes = authenticate_wholesale_client($testPhone, 'wrongpass999');
if (!$wrongPassRes['success']) {
    echo "SUCCESS: Rejected wrong password as expected: {$wrongPassRes['message']}\n";
} else {
    echo "SECURITY FAIL: Accepted wrong password!\n";
    exit(1);
}

echo "\n====================================================\n";
echo "TEST 5: Authenticate as another Client (Usman Mobile Shop: 0321-9876543, Pass: 5678)\n";
echo "====================================================\n";
$usmanAuth = authenticate_wholesale_client('0321-9876543', '5678');
if ($usmanAuth['success']) {
    echo "SUCCESS: Separate login for Usman Mobile Shop!\n";
    echo " - Client Name: {$_SESSION['client_name']}\n";
    echo " - Client Phone: {$_SESSION['client_phone']}\n";
    echo " - Khata Udhaar: {$_SESSION['client_balance']}\n";
    assert($_SESSION['client_phone'] === '0321-9876543');
} else {
    echo "FAILED: " . $usmanAuth['message'] . "\n";
    exit(1);
}

echo "\n====================================================\n";
echo "TEST 6: Edit Client Password\n";
echo "====================================================\n";
$newPass = 'updatedPass88';
$editRes = edit_client($newClientId, [
    'name'     => 'Bilal Mobile Centre (Super Store)',
    'owner'    => 'Bilal Ahmed',
    'phone'    => $testPhone,
    'password' => $newPass,
    'city'     => 'Faisalabad',
    'limit'    => 450000
]);

if ($editRes['success']) {
    echo "SUCCESS: Updated client details and password\n";
    $testUpdatedAuth = authenticate_wholesale_client($testPhone, $newPass);
    if ($testUpdatedAuth['success']) {
        echo "SUCCESS: Authenticated with newly updated password!\n";
    } else {
        echo "FAILED auth with updated password\n";
        exit(1);
    }
} else {
    echo "FAILED edit: " . $editRes['message'] . "\n";
    exit(1);
}

echo "\n====================================================\n";
echo "TEST 7: Action Column 'Create Login' Workflow\n";
echo "====================================================\n";
// Create a client without password (default behavior)
$actionPhone = "0333-777" . rand(1000, 9999);
$cRes = add_client([
    'name'     => 'Gujranwala Mobile Zone',
    'owner'    => 'Tariq Mehmood',
    'phone'    => $actionPhone,
    'password' => '1234',
    'city'     => 'Gujranwala',
    'limit'    => 500000,
    'balance'  => 85000
]);
assert($cRes['success']);
$actionCid = $cRes['client']['id'];
echo "Created client '{$cRes['client']['name']}' (ID: {$actionCid})\n";

// Now simulate clicking [Create Login] in Action column and saving new password
$customLoginPhone = "0333-888" . rand(1000, 9999);
$customLoginPass  = "customSecretPass789";
$setupRes = edit_client($actionCid, [
    'phone'    => $customLoginPhone,
    'password' => $customLoginPass
]);
assert($setupRes['success']);
echo "SUCCESS: Action column 'Create Login' saved new credentials (Phone: {$customLoginPhone}, Pass: {$customLoginPass})\n";

// Authenticate with the new credentials
$actionAuth = authenticate_wholesale_client($customLoginPhone, $customLoginPass);
assert($actionAuth['success']);
echo "SUCCESS: Logged in using Action column credentials!\n";
echo " - Logged In Shop: {$_SESSION['client_name']}\n";
echo " - Khata Udhaar: PKR " . number_format($_SESSION['client_balance']) . "\n";

echo "\n====================================================\n";
echo "TEST 8: Render Wholesale Portal & Verify Apna Khaata & Rates\n";
echo "====================================================\n";
ob_start();
include __DIR__ . '/../wholesale_catalog.php';
$portalHtml = ob_get_clean();

assert(strpos($portalHtml, 'Gujranwala Mobile Zone') !== false, 'Portal must show client name');
$expectedBalFormatted = number_format($_SESSION['client_balance']);
assert(strpos($portalHtml, $expectedBalFormatted) !== false, 'Portal must show client Khata balance');
assert(strpos($portalHtml, 'Mera Khata (Ledger)') !== false, 'Portal must show Mera Khata button');
assert(strpos($portalHtml, 'Wholesale Products & Spare Parts Catalog') !== false, 'Portal must show wholesale catalog rates');
echo "SUCCESS: Wholesale portal verified! Rendered client's Apna Khaata (PKR 85,000) and Wholesale Rates.\n";

// Clean up temporary test clients
delete_client($newClientId);
delete_client($actionCid);
echo "Cleaned up temporary test clients.\n";

echo "\n====================================================\n";
echo "ALL TESTS (1 TO 8) COMPLETED SUCCESSFULLY!\n";
echo "====================================================\n";
