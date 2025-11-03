<?php
// Start output buffering to prevent header issues
ob_start();

// -------------------------------------------------------------
// Optional brand settings (safe if file missing)
// Create settings.php that returns an array like:
// return [
//   'site_name'     => 'GeoLite',
//   'logo_url'      => '/assets/logo.svg',
//   'primary_color' => '#6366f1', // indigo
//   'hero_image'    => null,      // e.g. '/assets/hero.jpg'
//   'footer_text'   => '� ' . date('Y') . ' Your Company'
// ];
// -------------------------------------------------------------
$brand = [
    'site_name'     => 'GeoLite',
    'logo_url'      => null,
    'primary_color' => '#667eea', // original gradient hue
    'hero_image'    => null,
    'footer_text'   => '� ' . date('Y') . ' GeoLite'
];
require_once 'incl/const.php';
if (file_exists('settings.php')) {
    $loaded = include 'settings.php';
    if (is_array($loaded)) {
        $brand = array_merge($brand, array_filter($loaded, fn($v) => $v !== null && $v !== ''));
    }
}

// Load settings from database
require_once 'incl/db.php';
require_once 'incl/Settings.php';
$settingsService = new Settings($pdo, 'assets/brand', 'assets/brand');
$brand = array_merge($brand, $settingsService->load());

// Include authentication (unchanged)
require_once 'incl/Auth.php';

// If already logged in, redirect to index
if (isLoggedIn()) {
    ob_end_clean();
    header('Location: index.php');
    exit;
}

$error = '';
$loginAttempt = false;

// Handle login form submission (unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginAttempt = true;
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (authenticate($username, $password)) {
        // Successful login - redirect to index
        ob_end_clean();
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

// Flush output buffer
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($brand['site_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Material Design Tokens */
        :root {
            --brand-primary: <?= htmlspecialchars($brand['primary_color']) ?>;
            --md-surface: #ffffff;
            --md-surface-variant: #f3f4f6;
            --md-outline: #d0d5dd;
            --md-on-surface: #1f2937;
            --md-on-surface-secondary: #6b7280;
            --md-primary: #3b82f6;
            --md-primary-container: #e0edff;
            --md-on-primary: #ffffff;
            --md-radius-lg: 16px;
            --md-radius-md: 10px;
            --elev-card: 0 8px 24px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.06);
            --elev-card-hover: 0 16px 32px rgba(0,0,0,0.12), 0 4px 8px rgba(0,0,0,0.08);
        }

        /* Page shell */
        html, body {
            height: 100%;
        }
        body {
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr auto;
            background: var(--md-surface-variant);
        }

        /* Header with brand - Material style */
        .brand-header {
            background: var(--md-surface);
            border-bottom: 1px solid var(--md-outline);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .brand-bar {
            height: 3px;
            background: var(--md-primary);
        }
        .brand-logo { height: 32px; width: auto; }

        /* Centered login card - Material style */
        .login-wrap {
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .login-container {
            background: var(--md-surface);
            border-radius: var(--md-radius-lg);
            border: 1px solid var(--md-outline);
            box-shadow: var(--elev-card);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, var(--md-primary) 0%, #2563eb 100%);
            padding: 3rem 2rem;
            text-align: center;
            color: white;
        }
        .login-header i {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .login-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }
        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .login-body {
            padding: 3rem 2rem;
        }

        .form-control {
            border-radius: var(--md-radius-md);
            padding: 12px 15px;
            border: 1px solid var(--md-outline);
            transition: border-color 0.3s ease, box-shadow 0.2s ease;
        }
        .form-control:focus {
            border-color: var(--md-primary);
            box-shadow: 0 0 0 0.15rem rgba(59, 130, 246, 0.15);
            outline: none;
        }
        .input-group-text {
            background: var(--md-surface-variant);
            border: 1px solid var(--md-outline);
            border-right: none;
            border-radius: var(--md-radius-md) 0 0 var(--md-radius-md);
            padding: 12px 15px;
            color: var(--md-on-surface-secondary);
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 var(--md-radius-md) var(--md-radius-md) 0;
        }
        .input-group:focus-within .input-group-text {
            border-color: var(--md-primary);
        }

        .btn-login {
            background: var(--md-primary);
            border: none;
            border-radius: var(--md-radius-md);
            padding: 14px 20px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--md-on-primary);
            width: 100%;
            transition: box-shadow 0.18s ease, transform 0.18s ease;
            box-shadow: 0 3px 5px rgba(0,0,0,0.12);
        }
        .btn-login:hover {
            box-shadow: 0 6px 12px rgba(0,0,0,0.16);
            transform: translateY(-1px);
            color: var(--md-on-primary);
        }

        .alert {
            border-radius: var(--md-radius-md);
            border: 1px solid var(--md-outline);
        }
        .login-footer {
            text-align: center;
            padding: 20px 30px 30px;
            color: var(--md-on-surface-secondary);
            font-size: 0.9rem;
        }
        .site-footer {
            color: var(--md-on-surface-secondary);
            background: var(--md-surface);
            border-top: 1px solid var(--md-outline);
        }

        .shake { animation: shake 0.5s; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <!-- Site Header -->
    <header class="brand-header">
        <div class="container d-flex align-items-center py-2">
            <a href="/" class="d-flex align-items-center text-decoration-none">
                <?php if (!empty($brand['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($brand['logo_url']) ?>" alt="<?= htmlspecialchars($brand['site_name']) ?> logo" class="brand-logo me-2">
                <?php else: ?>
                    <span class="me-2" style="display:inline-block;width:28px;height:28px;border-radius:8px;background:var(--brand-primary)"></span>
                <?php endif; ?>
                <span class="fw-semibold fs-5 text-body"><?= htmlspecialchars($brand['site_name']) ?></span>
            </a>
            <!-- Optional nav (toggle via backend later) -->
            <nav class="ms-auto d-none d-md-flex gap-3">
                <!-- <a class="link-secondary text-decoration-none" href="/">Home</a>
                <a class="link-secondary text-decoration-none" href="/docs">Docs</a>
                <a class="link-secondary text-decoration-none" href="/contact">Contact</a> -->
            </nav>
        </div>
        <div class="brand-bar"></div>
    </header>

    <!-- Main auth area -->
    <main class="login-wrap">
        <div class="login-container <?php echo ($loginAttempt && $error) ? 'shake' : ''; ?>">
            <div class="login-header">
                <i class="bi bi-shield-lock-fill"></i>
                <h1><?= htmlspecialchars($brand['site_name']) ?></h1>                
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['logout'])): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <div>You have been successfully logged out.</div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person-fill"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username"
                                   placeholder="Enter username" required autofocus
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Enter password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </button>
                </form>
            </div>
            <div class="login-footer">
                <i class="bi bi-info-circle"></i> Contact your administrator if you need access
            </div>
        </div>
    </main>

    <!-- Site Footer -->
    <footer class="site-footer py-4">
        <div class="container text-center small">
            <?= $brand['footer_text'] ?>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
