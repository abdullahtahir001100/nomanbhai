<?php
require_once __DIR__ . '/../includes/store_data.php';
$clients = get_clients_list();
echo "Total clients: " . count($clients) . "\n";
print_r($clients);
