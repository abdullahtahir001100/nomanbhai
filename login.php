<?php
// login.php - Smart Mobile Authentication Portal (Admin ERP & Wholesale Client Portal)
require_once __DIR__ . '/includes/auth.php';

// Allow forced re-login via ?relogin=1
if (isset($_GET['relogin']) && $_GET['relogin'] == '1') {
    logout_user();
}

// If already logged in, redirect appropriately
if (is_wholesale_client_logged_in() && !is_logged_in()) {
    header("Location: wholesale_catalog.php");
    exit;
}
if (is_logged_in()) {
    $target = $_GET['redirect'] ?? 'index.php';
    if (strpos($target, 'login.php') !== false || empty($target)) {
        $target = 'index.php';
    }
    header("Location: " . $target);
    exit;
}

$errorMessage = '';
$successMessage = '';
$activeTab = (isset($_GET['client']) || isset($_GET['type']) && $_GET['type'] === 'client') ? 'client' : 'admin';

if ((isset($_GET['logged_out']) && $_GET['logged_out'] == '1') || isset($_GET['logout'])) {
    $successMessage = 'Aap kamyabi se logout ho chuke hain (You have been safely signed out).';
}

// Process login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginType = $_POST['login_type'] ?? 'admin';

    // 1. Wholesale Client Login
    if ($loginType === 'client') {
        $activeTab = 'client';
        $clientIdentifier = trim($_POST['client_identifier'] ?? '');
        $pin = trim($_POST['client_pin'] ?? '1234');

        $authResult = authenticate_wholesale_client($clientIdentifier, $pin);
        if ($authResult['success']) {
            header("Location: wholesale_catalog.php");
            exit;
        } else {
            $errorMessage = $authResult['message'];
        }
    } 
    // 2. Admin / Staff System Login
    else {
        $activeTab = 'admin';
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        $authResult = authenticate_user($username, $password);
        if ($authResult['success']) {
            $redirectTarget = $authResult['redirect'] ?? ($_POST['redirect_target'] ?? ($_GET['redirect'] ?? 'index.php'));
            
            $parsedTarget = parse_url($redirectTarget, PHP_URL_PATH);
            if (empty($redirectTarget) || strpos($redirectTarget, 'login.php') !== false || !empty(parse_url($redirectTarget, PHP_URL_HOST))) {
                $redirectTarget = 'index.php';
            }
            
            header("Location: " . $redirectTarget);
            exit;
        } else {
            $errorMessage = $authResult['message'];
        }
    }
}

$currentRedirect = htmlspecialchars($_GET['redirect'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Mobile ERP & Wholesale Portal</title>
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
            --primary-gradient: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #0f172a 100%);
            --accent-glow: rgba(37, 99, 235, 0.35);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0b0f19;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: #e2e8f0;
            position: relative;
            overflow-x: hidden;
        }
        /* Background Ambient Glows */
        .ambient-glow-1 {
            position: absolute;
            top: -120px;
            left: -100px;
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.22) 0%, rgba(15, 23, 42, 0) 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }
        .ambient-glow-2 {
            position: absolute;
            bottom: -100px;
            right: -80px;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, rgba(15, 23, 42, 0) 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }
        .login-card {
            background: rgba(17, 24, 39, 0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
            width: 100%;
            max-width: 460px;
            z-index: 1;
            position: relative;
            padding: 36px 30px;
            animation: fadeIn 0.4s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .brand-icon-box {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, #2563eb, #38bdf8);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px auto;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
        }
        .form-control, .form-select {
            background: #1e293b;
            border: 1px solid #334155;
            color: #f8fafc;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            background: #1e293b;
            color: #fff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        .form-control::placeholder {
            color: #64748b;
            font-size: 0.9rem;
        }
        .input-group-text {
            background: #1e293b;
            border: 1px solid #334155;
            color: #94a3b8;
            border-radius: 10px;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: none;
            color: #fff;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.98rem;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            transition: all 0.2s ease;
            width: 100%;
        }
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.45);
            color: #fff;
        }
        .btn-warning-custom {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none;
            color: #111827;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.98rem;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
            transition: all 0.2s ease;
            width: 100%;
        }
        .btn-warning-custom:hover {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.45);
            color: #000;
        }
        .quick-badge {
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 0.76rem;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(30, 41, 59, 0.7);
            color: #94a3b8;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .quick-badge:hover {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
            transform: translateY(-1px);
        }
        .quick-badge-client:hover {
            background: #f59e0b;
            color: #000;
            border-color: #f59e0b;
        }
        .nav-pills-custom {
            background: #1e293b;
            padding: 4px;
            border-radius: 12px;
            border: 1px solid #334155;
            display: flex;
            margin-bottom: 22px;
        }
        .nav-pills-custom button {
            flex: 1;
            padding: 8px 12px;
            border: none;
            background: transparent;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .nav-pills-custom button.active {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
        }
        .nav-pills-custom button.client-active {
            background: #f59e0b;
            color: #111827;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
        }
        .shake-animation {
            animation: shake 0.4s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-3px, 0, 0); }
            40%, 60% { transform: translate3d(3px, 0, 0); }
        }
    </style>
</head>
<body>

    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <div class="login-card <?php echo !empty($errorMessage) ? 'shake-animation' : ''; ?>">
        
        <!-- Logo & Branding -->
        <div class="text-center mb-3">
            <div class="brand-icon-box">
                <i class="fa-solid fa-mobile-screen-button text-white fa-2x"></i>
            </div>
            <h3 class="fw-bold text-white mb-1">Smart Mobile</h3>
            <p class="text-secondary small mb-0" style="letter-spacing: 0.5px;">WHOLESALE & RETAIL ERP PORTAL</p>
        </div>

        <!-- Success Toast / Notice -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success d-flex align-items-center p-2 mb-3 rounded-3" style="font-size: 0.86rem;" role="alert">
                <i class="fa-solid fa-circle-check me-2 fs-6"></i>
                <div><?php echo htmlspecialchars($successMessage); ?></div>
            </div>
        <?php endif; ?>

        <!-- Error Message Notice -->
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger d-flex align-items-center p-2 mb-3 rounded-3" style="font-size: 0.86rem;" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2 fs-6"></i>
                <div><?php echo htmlspecialchars($errorMessage); ?></div>
            </div>
        <?php endif; ?>

        <!-- Mode Navigation Switcher Tabs -->
        <div class="nav-pills-custom" id="loginTabSwitcher">
            <button type="button" class="<?php echo ($activeTab === 'admin') ? 'active' : ''; ?>" id="tabAdminBtn" onclick="switchLoginTab('admin')">
                <i class="fa-solid fa-user-shield me-1"></i> Admin / Staff
            </button>
            <button type="button" class="<?php echo ($activeTab === 'client') ? 'client-active' : ''; ?>" id="tabClientBtn" onclick="switchLoginTab('client')">
                <i class="fa-solid fa-boxes-stacked me-1"></i> Client Wholesale
            </button>
        </div>

        <!-- ========================================================================= -->
        <!-- FORM 1: ADMIN & STAFF SYSTEM LOGIN -->
        <!-- ========================================================================= -->
        <div id="adminLoginFormSection" class="<?php echo ($activeTab === 'client') ? 'd-none' : ''; ?>">
            <form method="POST" action="login.php" id="adminForm">
                <input type="hidden" name="login_type" value="admin">
                <input type="hidden" name="redirect_target" value="<?php echo $currentRedirect; ?>">

                <div class="mb-3">
                    <label for="usernameInput" class="form-label text-light small fw-semibold">Username / User ID</label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0"><i class="fa-regular fa-user"></i></span>
                        <input 
                            type="text" 
                            class="form-control border-start-0" 
                            id="usernameInput" 
                            name="username" 
                            placeholder="e.g. admin or staff" 
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                            autocomplete="username"
                        >
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="passwordInput" class="form-label text-light small fw-semibold">Password</label>
                        <span class="text-secondary small cursor-pointer" onclick="togglePasswordVisibility('passwordInput', 'eyeIcon1')" style="cursor: pointer; font-size: 0.76rem;">
                            Show
                        </span>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text border-end-0"><i class="fa-solid fa-key"></i></span>
                        <input 
                            type="password" 
                            class="form-control border-start-0 border-end-0" 
                            id="passwordInput" 
                            name="password" 
                            placeholder="Enter security password" 
                            autocomplete="current-password"
                        >
                        <span class="input-group-text cursor-pointer" onclick="togglePasswordVisibility('passwordInput', 'eyeIcon1')">
                            <i class="fa-regular fa-eye text-secondary" id="eyeIcon1"></i>
                        </span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary-custom mb-3">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In to Store Terminal
                </button>
            </form>

            <!-- 1-Click Fill Credentials -->
            <div class="pt-2 text-center">
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <button type="button" class="quick-badge" onclick="fillAdminCreds('admin', '1234')">
                        <i class="fa-solid fa-user-shield text-info"></i> Admin (1234)
                    </button>
                    <button type="button" class="quick-badge" onclick="fillAdminCreds('staff', '1234')">
                        <i class="fa-solid fa-cash-register text-success"></i> Staff (1234)
                    </button>
                    <button type="button" class="quick-badge quick-badge-client" onclick="fillAdminCreds('client', '1234')">
                        <i class="fa-solid fa-store text-warning"></i> Client (1234)
                    </button>
                </div>
            </div>

            <!-- Wholesale Client Promo Switcher Banner (At Bottom of Login Page) -->
            <div class="p-3 bg-dark bg-opacity-75 border border-warning border-opacity-25 rounded-3 text-center mt-3">
                <div class="d-flex justify-content-center align-items-center gap-2 mb-1">
                    <i class="fa-solid fa-store text-warning"></i>
                    <span class="text-white fw-bold small">Wholesale Client / Shopkeeper?</span>
                </div>
                <small class="text-secondary d-block mb-2" style="font-size: 0.78rem;">
                    Username: <strong class="text-warning">client</strong> &bull; Password: <strong class="text-warning">1234</strong>
                </small>
                <button type="button" class="btn btn-sm btn-outline-warning w-100 fw-bold" onclick="switchLoginTab('client')">
                    <i class="fa-solid fa-tags me-1"></i> Client Wholesale Portal Login &rarr;
                </button>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- FORM 2: WHOLESALE CLIENT / DEALER LOGIN -->
        <!-- ========================================================================= -->
        <div id="clientLoginFormSection" class="<?php echo ($activeTab === 'admin') ? 'd-none' : ''; ?>">
            
            <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 p-2 mb-3 rounded-3 text-warning small">
                <i class="fa-solid fa-tag me-1"></i> <strong>Wholesale Access:</strong> Username: <code>client</code> | Password: <code>1234</code>
            </div>

            <form method="POST" action="login.php" id="clientForm">
                <input type="hidden" name="login_type" value="client">

                <!-- Client Username -->
                <div class="mb-3">
                    <label class="form-label text-light small fw-semibold">Client Username</label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0"><i class="fa-solid fa-store text-warning"></i></span>
                        <input 
                            type="text" 
                            class="form-control border-start-0" 
                            id="clientIdentifierInput" 
                            name="client_identifier" 
                            placeholder="client" 
                            value="<?php echo htmlspecialchars($_POST['client_identifier'] ?? 'client'); ?>" 
                            required
                        >
                    </div>
                    <small class="text-secondary d-block mt-1" style="font-size: 0.74rem;">Default Username: <strong>client</strong></small>
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <label class="form-label text-light small fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0"><i class="fa-solid fa-lock text-warning"></i></span>
                        <input 
                            type="password" 
                            class="form-control border-start-0 border-end-0" 
                            id="clientPinInput" 
                            name="client_pin" 
                            placeholder="1234" 
                            value="1234" 
                            required
                        >
                        <span class="input-group-text cursor-pointer" onclick="togglePasswordVisibility('clientPinInput', 'eyeIcon2')">
                            <i class="fa-regular fa-eye text-secondary" id="eyeIcon2"></i>
                        </span>
                    </div>
                    <small class="text-secondary d-block mt-1" style="font-size: 0.74rem;">Default Password: <strong>1234</strong></small>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-warning-custom mb-3">
                    <i class="fa-solid fa-boxes-stacked me-2"></i> View All Wholesale Rates
                </button>
            </form>

            <!-- Quick 1-Click Client Profiles -->
            <div class="pt-2 text-center border-top border-secondary border-opacity-25">
                <small class="text-secondary d-block mb-2" style="font-size: 0.74rem;">QUICK 1-CLICK CLIENT LOGIN:</small>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <button type="button" class="quick-badge quick-badge-client" onclick="fillClientCreds('client', '1234')">
                        <i class="fa-solid fa-bolt text-warning"></i> client (1234)
                    </button>
                    <button type="button" class="quick-badge quick-badge-client" onclick="fillClientCreds('0300-1234567', '1234')">
                        <i class="fa-solid fa-store text-info"></i> Ali Electronics
                    </button>
                </div>
            </div>

            <!-- Return to Admin Terminal -->
            <div class="text-center mt-3 pt-2">
                <button type="button" class="btn btn-link text-secondary text-decoration-none small p-0" onclick="switchLoginTab('admin')">
                    &larr; Switch back to Store Admin / Staff Login
                </button>
            </div>
        </div>

        <!-- Footer Security Note -->
        <div class="text-center mt-3 pt-2">
            <small class="text-secondary opacity-75" style="font-size: 0.72rem;">
                <i class="fa-solid fa-shield-halved me-1 text-primary"></i> Protected by Smart Mobile Local Auth Layer &bull; 2026
            </small>
        </div>

    </div>

    <!-- Scripts -->
    <script>
        function switchLoginTab(tab) {
            const adminSection = document.getElementById('adminLoginFormSection');
            const clientSection = document.getElementById('clientLoginFormSection');
            const adminBtn = document.getElementById('tabAdminBtn');
            const clientBtn = document.getElementById('tabClientBtn');

            if (tab === 'client') {
                adminSection.classList.add('d-none');
                clientSection.classList.remove('d-none');
                adminBtn.className = '';
                clientBtn.className = 'client-active';
                document.getElementById('clientIdentifierInput').focus();
            } else {
                clientSection.classList.add('d-none');
                adminSection.classList.remove('d-none');
                clientBtn.className = '';
                adminBtn.className = 'active';
                document.getElementById('usernameInput').focus();
            }
        }

        function fillAdminCreds(user, pass) {
            document.getElementById('usernameInput').value = user;
            document.getElementById('passwordInput').value = pass;
            document.getElementById('usernameInput').focus();
        }

        function fillClientCreds(phone, pin) {
            document.getElementById('clientIdentifierInput').value = phone;
            document.getElementById('clientPinInput').value = pin;
            document.getElementById('clientIdentifierInput').focus();
        }

        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
