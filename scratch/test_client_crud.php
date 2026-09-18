<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/store_data.php';

echo "=== TESTING CLIENT DELETION & ZERO RESEEDING ===\n\n";

// 1. Delete all currently existing clients
$clients = get_clients_list();
echo "Found " . count($clients) . " clients before test:\n";
foreach ($clients as $cid => $c) {
    echo "  - Deleting client: {$c['name']} (ID: $cid)\n";
    $res = delete_client($cid);
    echo "    Result: " . ($res['success'] ? 'OK' : 'FAIL: ' . $res['message']) . "\n";
}

// 2. Now call get_clients_list() again
echo "\nChecking get_clients_list() after deleting all:\n";
$after = get_clients_list();
echo "Client count: " . count($after) . "\n";
if (empty($after)) {
    echo "SUCCESS: No dummy clients were resurrected! Table and JSON stay empty.\n";
} else {
    echo "FAILED: Clients resurrected: " . print_r($after, true) . "\n";
}

// 3. Test adding 1 legitimate client
echo "\nTesting adding 1 legitimate client...\n";
$newRes = add_client([
    'name'     => 'Shahid Telecom Miani',
    'owner'    => 'Shahid Mehmood',
    'phone'    => '0300-9988776',
    'password' => '1234',
    'city'     => 'Miani',
    'balance'  => 0,
    'limit'    => 200000
]);

$list2 = get_clients_list();
echo "Clients after adding: " . count($list2) . " (Expected: 1)\n";
$addedKey = key($list2);
echo "Added Client ID: $addedKey, Name: " . $list2[$addedKey]['name'] . "\n";

// 4. Test deleting this client
echo "\nTesting deleting this client ($addedKey)...\n";
$delRes = delete_client($addedKey);
echo "Delete result: " . ($delRes['success'] ? 'OK' : 'FAIL') . "\n";

$list3 = get_clients_list();
echo "Clients count after deleting: " . count($list3) . " (Expected: 0)\n";
if (count($list3) === 0) {
    echo "PERFECT! Client deleted and stays permanently deleted!\n";
} else {
    echo "ERROR: Clients found after delete!\n";
}
