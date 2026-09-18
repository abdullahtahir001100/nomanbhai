<?php
// track.php - Customer Public Live Mobile Repair Status Tracking Portal
require_once __DIR__ . '/includes/repairs_data.php';
require_once __DIR__ . '/includes/config.php';

$searched = false;
$foundJobs = [];
$searchQuery = trim($_GET['token'] ?? ($_GET['id'] ?? ($_GET['phone'] ?? ($_POST['query'] ?? ''))));

if (!empty($searchQuery)) {
    $searched = true;
    // Check by token first
    $job = get_repair_by_token($searchQuery);
    if ($job) {
        $foundJobs[] = $job;
    } else {
        // Check by phone number
        $foundJobs = get_repairs_by_phone($searchQuery);
    }
}

$storeWhatsApp = defined('STORE_WHATSAPP_NUMBER') ? STORE_WHATSAPP_NUMBER : '923041612042';
$storePhoneDisplay = defined('STORE_PHONE_DISPLAY') ? STORE_PHONE_DISPLAY : '0304-1612042';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Mobile Repair - Smart Mobile Miani</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --bg-body: #0b1120;
            --card-bg: rgba(30, 41, 59, 0.85);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: #f1f5f9;
            min-height: 100vh;
            padding-bottom: 40px;
            position: relative;
            overflow-x: hidden;
        }
        /* Background Glows */
        .ambient-glow-1 {
            position: absolute;
            top: -120px;
            left: -100px;
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.22) 0%, rgba(11, 17, 32, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .ambient-glow-2 {
            position: absolute;
            bottom: -100px;
            right: -80px;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.16) 0%, rgba(11, 17, 32, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .track-container {
            max-width: 680px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.45);
        }
        .brand-icon-box {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2563eb, #38bdf8);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px auto;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.35);
        }
        /* Stepper Styling */
        .stepper {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 30px 0 20px 0;
        }
        .stepper::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 30px;
            right: 30px;
            height: 3px;
            background: #334155;
            z-index: 0;
        }
        .stepper-progress {
            position: absolute;
            top: 20px;
            left: 30px;
            height: 3px;
            background: #10b981;
            z-index: 0;
            transition: width 0.4s ease;
        }
        .step-item {
            position: relative;
            z-index: 1;
            text-align: center;
            flex: 1;
        }
        .step-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #1e293b;
            border: 2px solid #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px auto;
            color: #94a3b8;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .step-item.active .step-icon {
            background: #2563eb;
            border-color: #38bdf8;
            color: #fff;
            box-shadow: 0 0 16px rgba(56, 189, 248, 0.5);
            transform: scale(1.1);
        }
        .step-item.completed .step-icon {
            background: #10b981;
            border-color: #34d399;
            color: #fff;
        }
        .step-label {
            font-size: 0.76rem;
            color: #94a3b8;
            font-weight: 600;
        }
        .step-item.active .step-label {
            color: #38bdf8;
            font-weight: 700;
        }
        .step-item.completed .step-label {
            color: #34d399;
        }
        .info-pill {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 12px 16px;
        }
    </style>
</head>
<body>

    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <div class="container py-4 track-container">
        
        <!-- Header & Logo -->
        <div class="text-center mb-4">
            <div class="brand-icon-box">
                <i class="fa-solid fa-screwdriver-wrench text-white fs-3"></i>
            </div>
            <h3 class="fw-bold text-white mb-1">Smart Mobile Repair Lab</h3>
            <p class="text-secondary small mb-0">
                <i class="fa-solid fa-location-dot me-1 text-danger"></i> Milad Chowk Main Bazar Miani &bull; Ph: <?php echo $storePhoneDisplay; ?>
            </p>
        </div>

        <!-- Search Input Card -->
        <div class="glass-card p-4 mb-4">
            <form action="track.php" method="GET">
                <label class="form-label text-light fw-semibold small mb-2">
                    <i class="fa-solid fa-magnifying-glass me-1 text-primary"></i> Apna Repair Token No ya Mobile Number enter karein:
                </label>
                <div class="input-group input-group-lg">
                    <input 
                        type="text" 
                        name="token" 
                        class="form-control bg-dark text-white border-secondary border-opacity-50" 
                        placeholder="e.g. REP-1001 ya 0300-1234567" 
                        value="<?php echo htmlspecialchars($searchQuery); ?>" 
                        required 
                        autofocus
                    >
                    <button class="btn btn-primary px-4 fw-bold" type="submit">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Track Status
                    </button>
                </div>
                <small class="text-secondary d-block mt-2" style="font-size: 0.76rem;">
                    Token number aapki dukan slip par likha hota hai (maslan <code>#REP-1001</code>).
                </small>
            </form>
        </div>

        <!-- Search Results -->
        <?php if ($searched): ?>
            <?php if (empty($foundJobs)): ?>
                <!-- Not Found Alert -->
                <div class="glass-card p-4 text-center">
                    <div class="py-4">
                        <i class="fa-solid fa-circle-question fa-3x text-warning mb-3 opacity-75"></i>
                        <h5 class="fw-bold text-white">Koi Record Nahi Mila</h5>
                        <p class="text-secondary small mb-3">
                            Token <code><?php echo htmlspecialchars($searchQuery); ?></code> ka koi repair record system mein nahi hai. Barah-e-karam apna Token No ya Mobile number dobara check karein.
                        </p>
                        <a href="https://wa.me/<?php echo $storeWhatsApp; ?>?text=Assalam-o-Alaikum%20Smart%20Mobile,%20mera%20repair%20token%20number%20<?php echo urlencode($searchQuery); ?>%20hai,%20status%20check%20kar%20dijiye." target="_blank" class="btn btn-outline-success btn-sm px-4 fw-bold">
                            <i class="fa-brands fa-whatsapp me-1"></i> Shop par WhatsApp Rabta Karein
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($foundJobs as $job): 
                    $status = $job['status'] ?? 'received';
                    $token = $job['token_no'];
                    $cost = (float)($job['estimated_cost'] ?? 0);
                    $adv = (float)($job['advance_paid'] ?? 0);
                    $due = max(0, $cost - $adv);

                    // Determine stepper stage (1: Received, 2: In Progress, 3: Ready, 4: Delivered)
                    $stage = 1;
                    $progressWidth = '0%';
                    if ($status === 'received') {
                        $stage = 1;
                        $progressWidth = '0%';
                    } elseif ($status === 'in_progress') {
                        $stage = 2;
                        $progressWidth = '33%';
                    } elseif ($status === 'ready') {
                        $stage = 3;
                        $progressWidth = '66%';
                    } elseif ($status === 'delivered') {
                        $stage = 4;
                        $progressWidth = '100%';
                    } elseif ($status === 'cancelled') {
                        $stage = 0;
                    }
                ?>
                    <!-- Job Status Card -->
                    <div class="glass-card p-4 mb-4">
                        
                        <!-- Top Header & Status Badge -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom border-secondary border-opacity-25 pb-3 mb-3">
                            <div>
                                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-1 fw-bold fs-6">
                                    Token #<?php echo htmlspecialchars($token); ?>
                                </span>
                                <span class="text-secondary small ms-2">Received: <?php echo date('d M Y, h:i A', strtotime($job['created_at'])); ?></span>
                            </div>
                            <div>
                                <?php if ($status === 'ready'): ?>
                                    <span class="badge bg-success px-3 py-2 fw-bold shadow-sm" style="font-size: 0.88rem;">
                                        <i class="fa-solid fa-circle-check me-1"></i> READY FOR PICKUP
                                    </span>
                                <?php elseif ($status === 'in_progress'): ?>
                                    <span class="badge bg-primary px-3 py-2 fw-bold shadow-sm" style="font-size: 0.88rem;">
                                        <i class="fa-solid fa-gears fa-spin me-1"></i> REPAIR IN PROGRESS
                                    </span>
                                <?php elseif ($status === 'delivered'): ?>
                                    <span class="badge bg-secondary px-3 py-2 fw-bold" style="font-size: 0.88rem;">
                                        <i class="fa-solid fa-handshake me-1"></i> DELIVERED
                                    </span>
                                <?php elseif ($status === 'cancelled'): ?>
                                    <span class="badge bg-danger px-3 py-2 fw-bold" style="font-size: 0.88rem;">
                                        <i class="fa-solid fa-ban me-1"></i> CANCELLED
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark px-3 py-2 fw-bold shadow-sm" style="font-size: 0.88rem;">
                                        <i class="fa-solid fa-clock me-1"></i> RECEIVED IN LAB
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Stepper Bar (if not cancelled) -->
                        <?php if ($status !== 'cancelled'): ?>
                            <div class="stepper">
                                <div class="stepper-progress" style="width: <?php echo $progressWidth; ?>;"></div>
                                
                                <div class="step-item <?php echo ($stage >= 1) ? ($stage > 1 ? 'completed' : 'active') : ''; ?>">
                                    <div class="step-icon">
                                        <i class="fa-solid <?php echo ($stage > 1) ? 'fa-check' : 'fa-inbox'; ?>"></i>
                                    </div>
                                    <div class="step-label">1. Received</div>
                                </div>

                                <div class="step-item <?php echo ($stage >= 2) ? ($stage > 2 ? 'completed' : 'active') : ''; ?>">
                                    <div class="step-icon">
                                        <i class="fa-solid <?php echo ($stage > 2) ? 'fa-check' : 'fa-screwdriver-wrench'; ?>"></i>
                                    </div>
                                    <div class="step-label">2. In Lab</div>
                                </div>

                                <div class="step-item <?php echo ($stage >= 3) ? ($stage > 3 ? 'completed' : 'active') : ''; ?>">
                                    <div class="step-icon">
                                        <i class="fa-solid <?php echo ($stage > 3) ? 'fa-check' : 'fa-bell'; ?>"></i>
                                    </div>
                                    <div class="step-label">3. Ready!</div>
                                </div>

                                <div class="step-item <?php echo ($stage >= 4) ? 'completed' : ''; ?>">
                                    <div class="step-icon">
                                        <i class="fa-solid fa-handshake"></i>
                                    </div>
                                    <div class="step-label">4. Delivered</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Status Highlight Message Box -->
                        <?php if ($status === 'ready'): ?>
                            <div class="alert alert-success bg-success bg-opacity-10 border-success border-opacity-25 d-flex align-items-center p-3 my-3 rounded-3 text-success">
                                <i class="fa-solid fa-circle-check fs-2 me-3"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Aap ka mobile theek ho kar tayyar hai!</h6>
                                    <small class="text-white-50">Dukan par aakar apna phone receive kar lein. Smart Mobile, Milad Chowk Main Bazar Miani.</small>
                                </div>
                            </div>
                        <?php elseif ($status === 'in_progress'): ?>
                            <div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 d-flex align-items-center p-3 my-3 rounded-3 text-primary">
                                <i class="fa-solid fa-gears fs-2 me-3 fa-spin"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Karigar aapke phone par kaam kar raha hai</h6>
                                    <small class="text-white-50">Mobile testing & part installation process mein hai. Tayyar hote hi yahan status update ho jayega.</small>
                                </div>
                            </div>
                        <?php elseif ($status === 'received'): ?>
                            <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 d-flex align-items-center p-3 my-3 rounded-3 text-warning">
                                <i class="fa-solid fa-clock fs-2 me-3"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Mobile dukan par jama ho chuka hai</h6>
                                    <small class="text-white-50">Technician jald hi inspect kar ke kaam shuru karega.</small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Details Grid -->
                        <div class="row g-3 my-2">
                            <div class="col-sm-6">
                                <div class="info-pill h-100">
                                    <small class="text-secondary d-block">Device Model</small>
                                    <strong class="text-white fs-6">
                                        <i class="fa-solid fa-mobile-screen me-1 text-primary"></i> <?php echo htmlspecialchars($job['device_model']); ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-pill h-100">
                                    <small class="text-secondary d-block">Reported Fault / Problem</small>
                                    <strong class="text-white">
                                        <i class="fa-solid fa-wrench me-1 text-warning"></i> <?php echo htmlspecialchars($job['fault_issue']); ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="col-sm-4 col-6">
                                <div class="info-pill h-100">
                                    <small class="text-secondary d-block">Estimated Bill</small>
                                    <strong class="text-white">PKR <?php echo number_format($cost); ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-4 col-6">
                                <div class="info-pill h-100">
                                    <small class="text-secondary d-block">Advance Paid</small>
                                    <strong class="text-success">PKR <?php echo number_format($adv); ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-4 col-12">
                                <div class="info-pill h-100">
                                    <small class="text-secondary d-block">Remaining Balance Due</small>
                                    <strong class="<?php echo ($due > 0) ? 'text-danger' : 'text-success'; ?> fs-6">
                                        PKR <?php echo number_format($due); ?>
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <!-- Technician Note if any -->
                        <?php if (!empty($job['technician_notes'])): ?>
                            <div class="p-3 bg-dark bg-opacity-50 rounded-3 border border-secondary border-opacity-25 mt-3">
                                <small class="text-muted d-block"><i class="fa-solid fa-clipboard-user me-1 text-info"></i> Technician Note / Hidayat:</small>
                                <div class="text-white-50 small mt-1"><?php echo nl2br(htmlspecialchars($job['technician_notes'])); ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- WhatsApp Help Action -->
                        <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <small class="text-secondary">
                                <i class="fa-solid fa-shop me-1 text-primary"></i> Smart Mobile Lab Desk
                            </small>
                            <a href="https://wa.me/<?php echo $storeWhatsApp; ?>?text=Assalam-o-Alaikum%20Smart%20Mobile,%20mera%20repair%20token%20number%20%23<?php echo urlencode($token); ?>%20(<?php echo urlencode($job['device_model']); ?>)%20hai.%20Mjy%20status%20maloom%20karna%20hai." target="_blank" class="btn btn-success btn-sm px-3 fw-semibold">
                                <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp par Baat Karein
                            </a>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center mt-5">
            <small class="text-secondary" style="font-size: 0.74rem;">
                Smart Mobile &bull; Milad Chowk Main Bazar Miani &bull; Powered by Smart Mobile ERP
            </small>
        </div>

    </div>

</body>
</html>
