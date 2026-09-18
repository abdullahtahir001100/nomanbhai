<?php
// includes/repairs_data.php - Central Data Manager for Mobile Repairs & WhatsApp Tracking
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

/**
 * Ensure `repairs` table exists in MySQL
 */
function init_repairs_table(): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `repairs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `token_no` VARCHAR(50) NOT NULL UNIQUE,
                `customer_name` VARCHAR(100) NOT NULL,
                `customer_phone` VARCHAR(30) NOT NULL,
                `device_model` VARCHAR(100) NOT NULL,
                `fault_issue` TEXT NOT NULL,
                `pattern_lock` VARCHAR(100) DEFAULT NULL,
                `estimated_cost` DECIMAL(10,2) DEFAULT 0.00,
                `advance_paid` DECIMAL(10,2) DEFAULT 0.00,
                `status` ENUM('received', 'in_progress', 'ready', 'delivered', 'cancelled') DEFAULT 'received',
                `technician_notes` TEXT DEFAULT NULL,
                `delivered_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (`token_no`),
                INDEX (`customer_phone`),
                INDEX (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Throwable $e) {
        error_log("Repairs Table Init Error: " . $e->getMessage());
    }
}

// Auto initialize table if connected
init_repairs_table();

function get_repairs_file_path(): string {
    return __DIR__ . '/../data/repairs.json';
}

/**
 * Fetch all repairs from MySQL (or JSON fallback)
 */
function get_repairs_list(): array {
    $repairs = [];
    $filePath = get_repairs_file_path();

    if (is_db_connected()) {
        try {
            $pdo = get_db();
            $stmt = $pdo->query("SELECT * FROM `repairs` ORDER BY `id` DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $token = $r['token_no'];
                $repairs[$token] = [
                    'id'               => (int)$r['id'],
                    'token_no'         => $r['token_no'],
                    'customer_name'    => $r['customer_name'],
                    'customer_phone'   => $r['customer_phone'],
                    'device_model'     => $r['device_model'],
                    'fault_issue'      => $r['fault_issue'],
                    'pattern_lock'     => $r['pattern_lock'] ?? '',
                    'estimated_cost'   => (float)$r['estimated_cost'],
                    'advance_paid'     => (float)$r['advance_paid'],
                    'status'           => $r['status'],
                    'technician_notes' => $r['technician_notes'] ?? '',
                    'delivered_at'     => $r['delivered_at'],
                    'created_at'       => $r['created_at'],
                    'updated_at'       => $r['updated_at']
                ];
            }

            // Sync to JSON for offline backup
            if (!empty($repairs) || !file_exists($filePath)) {
                @file_put_contents($filePath, json_encode($repairs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            return $repairs;
        } catch (Throwable $e) {
            error_log("Fetch repairs error: " . $e->getMessage());
        }
    }

    // Fallback to JSON
    if (file_exists($filePath)) {
        $raw = file_get_contents($filePath);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

/**
 * Save repairs list to JSON
 */
function save_repairs_json(array $repairs): bool {
    $filePath = get_repairs_file_path();
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return (bool) @file_put_contents($filePath, json_encode($repairs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Generate Next Token Number
 */
function generate_next_repair_token(): string {
    $repairs = get_repairs_list();
    $maxNum = 1000;
    foreach ($repairs as $token => $item) {
        if (preg_match('/REP-(\d+)/i', $token, $matches)) {
            $num = (int)$matches[1];
            if ($num > $maxNum) {
                $maxNum = $num;
            }
        }
    }
    return 'REP-' . ($maxNum + 1);
}

/**
 * Add a new repair entry
 */
function add_repair_job(array $data): array {
    $name    = trim($data['customer_name'] ?? '');
    $phone   = trim($data['customer_phone'] ?? '');
    $model   = trim($data['device_model'] ?? '');
    $fault   = trim($data['fault_issue'] ?? ($data['fault_description'] ?? ''));
    $lock    = trim($data['pattern_lock'] ?? ($data['security_lock'] ?? ''));
    $estCost = max(0.0, (float)($data['estimated_cost'] ?? 0));
    $advance = max(0.0, (float)($data['advance_paid'] ?? 0));
    $notes   = trim($data['technician_notes'] ?? ($data['notes'] ?? ''));
    $token   = trim($data['token_no'] ?? ($data['token'] ?? ''));

    if (empty($name)) {
        return ['success' => false, 'message' => 'Customer name zaroori hai.'];
    }
    if (empty($phone)) {
        return ['success' => false, 'message' => 'Customer mobile number zaroori hai.'];
    }
    if (empty($model)) {
        return ['success' => false, 'message' => 'Mobile device model likhna zaroori hai.'];
    }
    if (empty($fault)) {
        return ['success' => false, 'message' => 'Mobile ka masla / fault likhna zaroori hai.'];
    }

    if (empty($token)) {
        $token = generate_next_repair_token();
    }

    $createdAt = date('Y-m-d H:i:s');
    $status = 'received';

    // 1. Sync to MySQL
    $insertedId = 0;
    if (is_db_connected()) {
        try {
            $pdo = get_db();
            $stmt = $pdo->prepare("
                INSERT INTO `repairs` 
                (`token_no`, `customer_name`, `customer_phone`, `device_model`, `fault_issue`, `pattern_lock`, `estimated_cost`, `advance_paid`, `status`, `technician_notes`, `created_at`, `updated_at`)
                VALUES (:token, :name, :phone, :model, :fault, :lock, :est, :adv, :status, :notes, :created, :updated)
            ");
            $stmt->execute([
                ':token'   => $token,
                ':name'    => $name,
                ':phone'   => $phone,
                ':model'   => $model,
                ':fault'   => $fault,
                ':lock'    => $lock,
                ':est'     => $estCost,
                ':adv'     => $advance,
                ':status'  => $status,
                ':notes'   => $notes,
                ':created' => $createdAt,
                ':updated' => $createdAt
            ]);
            $insertedId = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log("DB Add Repair Error: " . $e->getMessage());
        }
    }

    // 2. Sync to JSON
    $repairs = get_repairs_list();
    $newJob = [
        'id'               => $insertedId ?: count($repairs) + 1,
        'token_no'         => $token,
        'token'            => $token,
        'customer_name'    => $name,
        'customer_phone'   => $phone,
        'device_model'     => $model,
        'fault_issue'      => $fault,
        'pattern_lock'     => $lock,
        'estimated_cost'   => $estCost,
        'advance_paid'     => $advance,
        'balance_due'      => max(0.0, $estCost - $advance),
        'status'           => $status,
        'technician_notes' => $notes,
        'delivered_at'     => null,
        'created_at'       => $createdAt,
        'updated_at'       => $createdAt
    ];
    $repairs[$token] = $newJob;
    save_repairs_json($repairs);

    return [
        'success' => true, 
        'message' => "Repair token {$token} kamyabi se create ho gaya.",
        'job'     => $newJob
    ];
}

/**
 * Update Repair Status
 */
function update_repair_status(string $token, string $status, ?string $notes = null): array {
    $allowedStatuses = ['received', 'in_progress', 'ready', 'delivered', 'cancelled'];
    if (!in_array($status, $allowedStatuses)) {
        return ['success' => false, 'message' => 'Ghalat status value.'];
    }

    $now = date('Y-m-d H:i:s');
    $deliveredAt = ($status === 'delivered') ? $now : null;

    if (is_db_connected()) {
        try {
            $pdo = get_db();
            if ($notes !== null && $notes !== '') {
                $stmt = $pdo->prepare("
                    UPDATE `repairs` 
                    SET `status` = :status, `technician_notes` = :notes, `delivered_at` = COALESCE(:del, `delivered_at`), `updated_at` = :now 
                    WHERE `token_no` = :token
                ");
                $stmt->execute([
                    ':status' => $status,
                    ':notes'  => $notes,
                    ':del'    => $deliveredAt,
                    ':now'    => $now,
                    ':token'  => $token
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE `repairs` 
                    SET `status` = :status, `delivered_at` = COALESCE(:del, `delivered_at`), `updated_at` = :now 
                    WHERE `token_no` = :token
                ");
                $stmt->execute([
                    ':status' => $status,
                    ':del'    => $deliveredAt,
                    ':now'    => $now,
                    ':token'  => $token
                ]);
            }
        } catch (Throwable $e) {
            error_log("DB Update Repair Status Error: " . $e->getMessage());
        }
    }

    $repairs = get_repairs_list();
    if (isset($repairs[$token])) {
        $repairs[$token]['status'] = $status;
        $repairs[$token]['updated_at'] = $now;
        if ($notes !== null && $notes !== '') {
            $repairs[$token]['technician_notes'] = $notes;
        }
        if ($status === 'delivered') {
            $repairs[$token]['delivered_at'] = $now;
        }
        save_repairs_json($repairs);
        return ['success' => true, 'message' => "Token {$token} ka status '{$status}' update ho gaya.", 'job' => $repairs[$token]];
    }

    return ['success' => false, 'message' => 'Token nahi mila.'];
}

/**
 * Edit Full Repair Job Details
 */
function update_repair_job(string $origToken, array $data): array {
    $name    = trim($data['customer_name'] ?? '');
    $phone   = trim($data['customer_phone'] ?? '');
    $model   = trim($data['device_model'] ?? '');
    $fault   = trim($data['fault_issue'] ?? '');
    $lock    = trim($data['pattern_lock'] ?? '');
    $estCost = max(0.0, (float)($data['estimated_cost'] ?? 0));
    $advance = max(0.0, (float)($data['advance_paid'] ?? 0));
    $status  = trim($data['status'] ?? 'received');
    $notes   = trim($data['technician_notes'] ?? '');

    $now = date('Y-m-d H:i:s');
    $deliveredAt = ($status === 'delivered') ? $now : null;

    if (is_db_connected()) {
        try {
            $pdo = get_db();
            $stmt = $pdo->prepare("
                UPDATE `repairs`
                SET `customer_name` = :name, `customer_phone` = :phone, `device_model` = :model, 
                    `fault_issue` = :fault, `pattern_lock` = :lock, `estimated_cost` = :est, 
                    `advance_paid` = :adv, `status` = :status, `technician_notes` = :notes,
                    `delivered_at` = COALESCE(:del, `delivered_at`), `updated_at` = :now
                WHERE `token_no` = :token
            ");
            $stmt->execute([
                ':name'   => $name,
                ':phone'  => $phone,
                ':model'  => $model,
                ':fault'  => $fault,
                ':lock'   => $lock,
                ':est'    => $estCost,
                ':adv'    => $advance,
                ':status' => $status,
                ':notes'  => $notes,
                ':del'    => $deliveredAt,
                ':now'    => $now,
                ':token'  => $origToken
            ]);
        } catch (Throwable $e) {
            error_log("DB Edit Repair Error: " . $e->getMessage());
        }
    }

    $repairs = get_repairs_list();
    if (isset($repairs[$origToken])) {
        $repairs[$origToken]['customer_name']    = $name;
        $repairs[$origToken]['customer_phone']   = $phone;
        $repairs[$origToken]['device_model']     = $model;
        $repairs[$origToken]['fault_issue']      = $fault;
        $repairs[$origToken]['pattern_lock']     = $lock;
        $repairs[$origToken]['estimated_cost']   = $estCost;
        $repairs[$origToken]['advance_paid']     = $advance;
        $repairs[$origToken]['status']           = $status;
        $repairs[$origToken]['technician_notes'] = $notes;
        $repairs[$origToken]['updated_at']       = $now;
        if ($status === 'delivered') {
            $repairs[$origToken]['delivered_at'] = $now;
        }
        save_repairs_json($repairs);
        return ['success' => true, 'message' => "Repair token {$origToken} details update ho gayi hain.", 'job' => $repairs[$origToken]];
    }

    return ['success' => false, 'message' => 'Token nahi mila.'];
}

/**
 * Delete a repair job
 */
function delete_repair_job(string $token): array {
    if (is_db_connected()) {
        try {
            $pdo = get_db();
            $stmt = $pdo->prepare("DELETE FROM `repairs` WHERE `token_no` = :token");
            $stmt->execute([':token' => $token]);
        } catch (Throwable $e) {
            error_log("DB Delete Repair Error: " . $e->getMessage());
        }
    }

    $repairs = get_repairs_list();
    if (isset($repairs[$token])) {
        unset($repairs[$token]);
        save_repairs_json($repairs);
        return ['success' => true, 'message' => "Token {$token} delete ho gaya."];
    }
    return ['success' => false, 'message' => 'Token nahi mila.'];
}

/**
 * Get single repair by token
 */
function get_repair_by_token(string $token): ?array {
    $token = trim($token);
    if (empty($token)) return null;

    if (is_db_connected()) {
        try {
            $pdo = get_db();
            $stmt = $pdo->prepare("SELECT * FROM `repairs` WHERE `token_no` = :token LIMIT 1");
            $stmt->execute([':token' => $token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        } catch (Throwable $e) {}
    }

    $repairs = get_repairs_list();
    return $repairs[$token] ?? null;
}

/**
 * Get repairs by customer phone
 */
function get_repairs_by_phone(string $phone): array {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleaned) < 5) return [];

    $results = [];
    $repairs = get_repairs_list();
    foreach ($repairs as $r) {
        $rClean = preg_replace('/[^0-9]/', '', $r['customer_phone'] ?? '');
        if (strpos($rClean, $cleaned) !== false || strpos($cleaned, $rClean) !== false) {
            $results[] = $r;
        }
    }
    return $results;
}

/**
 * Calculate repair summary metrics
 */
function calculate_repairs_metrics(?array $repairs = null): array {
    if ($repairs === null) {
        $repairs = get_repairs_list();
    }
    $total = count($repairs);
    $received = 0;
    $inProgress = 0;
    $ready = 0;
    $delivered = 0;
    $cancelled = 0;
    $totalPendingRevenue = 0.0;

    foreach ($repairs as $r) {
        $st = $r['status'] ?? 'received';
        if ($st === 'received') $received++;
        elseif ($st === 'in_progress') $inProgress++;
        elseif ($st === 'ready') $ready++;
        elseif ($st === 'delivered') $delivered++;
        elseif ($st === 'cancelled') $cancelled++;

        if ($st !== 'delivered' && $st !== 'cancelled') {
            $due = max(0.0, (float)($r['estimated_cost'] ?? 0) - (float)($r['advance_paid'] ?? 0));
            $totalPendingRevenue += $due;
        }
    }

    return [
        'total'                 => $total,
        'active_jobs'           => ($received + $inProgress + $ready),
        'received_count'        => $received,
        'in_progress_count'     => $inProgress,
        'ready_count'           => $ready,
        'delivered_count'       => $delivered,
        'cancelled_count'       => $cancelled,
        'pending_receivable'    => $totalPendingRevenue
    ];
}

/**
 * Clean phone number for WhatsApp wa.me link
 * Converts 03001234567 -> 923001234567
 */
function format_phone_for_whatsapp(string $phone): string {
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($digits, '03') === 0) {
        return '92' . substr($digits, 1);
    }
    if (strpos($digits, '3') === 0 && strlen($digits) === 10) {
        return '92' . $digits;
    }
    return $digits;
}

/**
 * Generate formatted WhatsApp URL for a repair job
 */
function generate_repair_whatsapp_url(array $job, string $type = 'status_update', ?string $customMsg = null): string {
    $phone = format_phone_for_whatsapp($job['customer_phone'] ?? '');
    if (empty($phone)) {
        $phone = defined('STORE_WHATSAPP_NUMBER') ? STORE_WHATSAPP_NUMBER : '923041612042';
    }

    $token   = $job['token_no'] ?? ($job['token'] ?? '');
    $name    = $job['customer_name'] ?? 'Customer';
    $model   = $job['device_model'] ?? 'Mobile';
    $fault   = $job['fault_issue'] ?? ($job['fault_description'] ?? 'Device checkup');
    $cost    = number_format((float)($job['estimated_cost'] ?? 0));
    $adv     = number_format((float)($job['advance_paid'] ?? 0));
    $due     = number_format(max(0, (float)($job['estimated_cost'] ?? 0) - (float)($job['advance_paid'] ?? 0)));
    $status  = $job['status'] ?? 'received';

    // Base tracking URL (Current protocol + host + current folder)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $baseUri = '';
    if (!empty($scriptDir)) {
        $scriptDir = preg_replace('#/(includes|scratch)$#', '', $scriptDir);
        if (!empty($scriptDir)) {
            $baseUri = '/' . $scriptDir;
        }
    }
    $trackingUrl = $protocol . $host . $baseUri . '/track.php?token=' . urlencode($token);

    if ($type === 'token_receipt') {
        $msg = "Assalam-o-Alaikum *{$name}*!\n\n"
             . "Smart Mobile par aap ka *{$model}* repair ke liye receive ho chuka hai.\n\n"
             . "📋 *Job Token:* #{$token}\n"
             . "🛠️ *Fault:* {$fault}\n"
             . "💰 *Estimated Bill:* PKR {$cost}\n"
             . "💵 *Advance Paid:* PKR {$adv}\n"
             . "⏳ *Remaining Due:* PKR {$due}\n\n"
             . "🔍 *Live Status Online Check Karein:*\n"
             . "{$trackingUrl}\n\n"
             . "📍 *Smart Mobile* - Milad Chowk Main Bazar Miani\n"
             . "📞 WhatsApp: 0304-1612042";
    } elseif ($type === 'ready_alert') {
        $msg = "Assalam-o-Alaikum *{$name}*!\n\n"
             . "🎉 *Khushkhabri!* Aap ka mobile *{$model}* repair ho kar tayyar hai.\n\n"
             . "📋 *Token No:* #{$token}\n"
             . "🚦 *Status:* 🟢 READY FOR PICKUP\n"
             . "💵 *Baaqi Bill:* PKR {$due}\n\n"
             . "Dukaan par tashreef la kar apna phone receive kar lein.\n"
             . "📍 *Smart Mobile* - Milad Chowk Main Bazar Miani\n"
             . "📞 0304-1612042\n\n"
             . "Track Online: {$trackingUrl}";
    } elseif ($type === 'delivered_receipt') {
        $msg = "Assalam-o-Alaikum *{$name}*!\n\n"
             . "Shukriya! Aap ka mobile *{$model}* (Token #{$token}) deliver ho chuka hai.\n"
             . "Total Paid: PKR {$cost}.\n\n"
             . "Smart Mobile ki khidmaat use karne ka shukriya!\n"
             . "📍 Milad Chowk Main Bazar Miani | 0304-1612042";
    } else {
        $statusLabels = [
            'received'    => '🟡 RECEIVED (Dukan par jama hai)',
            'in_progress' => '🔵 IN PROGRESS (Karigar kaam kar raha hai)',
            'ready'       => '🟢 READY FOR DELIVERY (Tayyar hai)',
            'delivered'   => '⚪ DELIVERED (Deliver ho gaya)',
            'cancelled'   => '🔴 CANCELLED'
        ];
        $stLabel = $statusLabels[$status] ?? strtoupper($status);

        $msg = "Assalam-o-Alaikum *{$name}*!\n\n"
             . "Aap ke mobile *{$model}* (Token #{$token}) ka update:\n"
             . "🚦 *Current Status:* {$stLabel}\n"
             . "💰 *Remaining Bill:* PKR {$due}\n\n"
             . "🔍 Live Status: {$trackingUrl}\n\n"
             . "📍 Smart Mobile, Milad Chowk Main Bazar Miani";
    }

    return "https://wa.me/{$phone}?text=" . rawurlencode($msg);
}
