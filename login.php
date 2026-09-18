<?php
// login.php - Clean & Secure Login Portal (Admin & Wholesale Client Only)
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

require_once __DIR__ . '/includes/store_data.php';

$errorMessage = '';
$successMessage = '';
$activeTab = (isset($_GET['client']) || (isset($_GET['type']) && $_GET['type'] === 'client')) ? 'client' : 'admin';

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
        $pin = trim($_POST['client_pin'] ?? '');

        $authResult = authenticate_wholesale_client($clientIdentifier, $pin);
        if ($authResult['success']) {
            header("Location: wholesale_catalog.php");
            exit;
        } else {
            $errorMessage = $authResult['message'];
        }
    } 
    // 2. Admin System Login
    else {
        $activeTab = 'admin';
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        $authResult = authenticate_user($username, $password);
        if ($authResult['success']) {
            $redirectTarget = $authResult['redirect'] ?? ($_POST['redirect_target'] ?? ($_GET['redirect'] ?? 'index.php'));
            
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
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: #e2e8f0;
            position: relative;
            overflow-x: hidden;
        }
        /* Subtle Glow Accents */
        .ambient-glow-1 {
            position: absolute;
            top: -100px;
            left: -80px;
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.18) 0%, rgba(15, 23, 42, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .ambient-glow-2 {
            position: absolute;
            bottom: -80px;
            right: -60px;
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.14) 0%, rgba(15, 23, 42, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .login-card {
            background: rgba(30, 41, 59, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            padding: 36px 30px;
            animation: fadeIn 0.3s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .brand-icon-box {
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, #2563eb, #38bdf8);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px auto;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
        }
        /* Tab Switcher */
        .tab-switcher {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 4px;
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
        }
        .tab-btn {
            flex: 1;
            padding: 9px 12px;
            border: none;
            background: transparent;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.86rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .tab-btn:hover {
            color: #fff;
        }
        .tab-btn.active-admin {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }
        .tab-btn.active-client {
            background: #059669;
            color: #fff;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
        }
        .form-label {
            font-size: 0.84rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 6px;
        }
        .form-control {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid #334155;
            color: #f8fafc;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.92rem;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            background: rgba(15, 23, 42, 0.95);
            color: #fff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .form-control::placeholder {
            color: #64748b;
            font-size: 0.88rem;
        }
        .input-group-text {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid #334155;
            color: #94a3b8;
            border-radius: 10px;
            font-size: 0.9rem;
        }
        .btn-admin-submit {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: none;
            color: #fff;
            padding: 11px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            transition: all 0.2s ease;
            width: 100%;
        }
        .btn-admin-submit:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.45);
            color: #fff;
        }
        .btn-client-submit {
            background: linear-gradient(135deg, #059669, #047857);
            border: none;
            color: #fff;
            padding: 11px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
            transition: all 0.2s ease;
            width: 100%;
        }
        .btn-client-submit:hover {
            background: linear-gradient(135deg, #047857, #065f46);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(5, 150, 105, 0.45);
            color: #fff;
        }
        .cursor-pointer { cursor: pointer; }
    </style>
</head>
<body>

    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <div class="login-card">
        
        <!-- Logo & Branding -->
        <div class="text-center mb-4">
            <div class="brand-icon-box">
                <i class="fa-solid fa-mobile-screen-button text-white fs-4"></i>
            </div>
            <h4 class="fw-bold text-white mb-0">Smart Mobile</h4>
            <small class="text-secondary" style="font-size: 0.78rem; letter-spacing: 0.5px;">SIGN IN TO ACCESS YOUR ACCOUNT</small>
        </div>

        <!-- Success Toast / Notice -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success d-flex align-items-center p-2 mb-3 rounded-3" style="font-size: 0.84rem;" role="alert">
                <i class="fa-solid fa-circle-check me-2 text-success"></i>
                <div><?php echo htmlspecialchars($successMessage); ?></div>
            </div>
        <?php endif; ?>

        <!-- Error Message Notice -->
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger d-flex align-items-center p-2 mb-3 rounded-3" style="font-size: 0.84rem;" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2 text-danger"></i>
                <div><?php echo htmlspecialchars($errorMessage); ?></div>
            </div>
        <?php endif; ?>

        <!-- 2-Option Tab Switcher -->
        <div class="tab-switcher">
            <button type="button" class="tab-btn <?php echo ($activeTab === 'admin') ? 'active-admin' : ''; ?>" id="tabAdminBtn" onclick="switchTab('admin')">
                <i class="fa-solid fa-user-shield"></i> Admin Login
            </button>
            <button type="button" class="tab-btn <?php echo ($activeTab === 'client') ? 'active-client' : ''; ?>" id="tabClientBtn" onclick="switchTab('client')">
                <i class="fa-solid fa-store"></i> Wholesale Client
            </button>
        </div>

        <!-- 1. ADMIN LOGIN FORM -->
        <div id="adminSection" class="<?php echo ($activeTab === 'client') ? 'd-none' : ''; ?>">
            <form method="POST" action="login.php">
                <input type="hidden" name="login_type" value="admin">
                <input type="hidden" name="redirect_target" value="<?php echo $currentRedirect; ?>">

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0"><i class="fa-regular fa-user"></i></span>
                        <input type="text" class="form-control border-start-0" id="adminUsername" name="username" placeholder="Enter admin username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required autocomplete="username">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control border-start-0 border-end-0" id="adminPassword" name="password" placeholder="Enter password" required autocomplete="current-password">
                        <span class="input-group-text cursor-pointer" onclick="togglePass('adminPassword', 'eyeIconAdmin')">
                            <i class="fa-regular fa-eye text-secondary" id="eyeIconAdmin"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn btn-admin-submit">
                    <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In to Admin ERP
                </button>
            </form>
        </div>

        <!-- 2. WHOLESALE CLIENT LOGIN FORM -->
        <div id="clientSection" class="<?php echo ($activeTab === 'admin') ? 'd-none' : ''; ?>">
            <form method="POST" action="login.php">
                <input type="hidden" name="login_type" value="client">

                <div class="mb-3">
                    <label class="form-label">Client Mobile Number</label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0"><i class="fa-solid fa-phone text-success"></i></span>
                        <input type="text" class="form-control border-start-0" id="clientPhone" name="client_identifier" placeholder="e.g. 0300-1234567" value="<?php echo htmlspecialchars($_POST['client_identifier'] ?? ''); ?>" required autocomplete="tel">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Client Password</label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0"><i class="fa-solid fa-key text-success"></i></span>
                        <input type="password" class="form-control border-start-0 border-end-0" id="clientPassword" name="client_pin" placeholder="Enter client password" required autocomplete="current-password">
                        <span class="input-group-text cursor-pointer" onclick="togglePass('clientPassword', 'eyeIconClient')">
                            <i class="fa-regular fa-eye text-secondary" id="eyeIconClient"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn btn-client-submit">
                    <i class="fa-solid fa-right-to-bracket me-1"></i> Login to Wholesale Portal
                </button>
            </form>
        </div>

        <!-- Clean Footer -->
        <div class="text-center mt-4 pt-2 border-top border-secondary border-opacity-10">
            <small class="text-secondary opacity-75" style="font-size: 0.72rem;">
                Smart Mobile ERP &bull; 2026
            </small>
        </div>

    </div>

    <!-- Scripts -->
    <script>
        function switchTab(tab) {
            const adminSec = document.getElementById('adminSection');
            const clientSec = document.getElementById('clientSection');
            const adminBtn = document.getElementById('tabAdminBtn');
            const clientBtn = document.getElementById('tabClientBtn');

            if (tab === 'client') {
                adminSec.classList.add('d-none');
                clientSec.classList.remove('d-none');
                adminBtn.className = 'tab-btn';
                clientBtn.className = 'tab-btn active-client';
                document.getElementById('clientPhone').focus();
            } else {
                clientSec.classList.add('d-none');
                adminSec.classList.remove('d-none');
                clientBtn.className = 'tab-btn';
                adminBtn.className = 'tab-btn active-admin';
                document.getElementById('adminUsername').focus();
            }
        }

        function togglePass(inputId, iconId) {
            const inp = document.getElementById(inputId);
            const ico = document.getElementById(iconId);
            if (inp.type === 'password') {
                inp.type = 'text';
                ico.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                inp.type = 'password';
                ico.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
