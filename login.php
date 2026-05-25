<?php
/**
 * Universal Login Page
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$page_title = "Login";

// Redirect if already logged in
if (is_logged_in()) {
    $user = get_logged_user();
    if ($user['role'] === 'admin') {
        header("Location: " . BASE_URL . "/admin/dashboard.php");
    } elseif ($user['role'] === 'agent') {
        header("Location: " . BASE_URL . "/agent/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "/index.php");
    }
    exit();
}

$error = '';
$success = '';

// Handle timeout message
if (isset($_SESSION['timeout_message'])) {
    $error = $_SESSION['timeout_message'];
    unset($_SESSION['timeout_message']);
}

// Handle login submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security validation failed. Please try again.";
    } else {
        $login_input = trim($_POST['login_input'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login_input) || empty($password)) {
            $error = "Please fill in all fields.";
        } else {
            // Find user by username or email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :login OR email = :login LIMIT 1");
            $stmt->execute([':login' => $login_input]);
            $user_row = $stmt->fetch();

            if ($user_row && password_verify($password, $user_row['password'])) {
                // Check status
                if ($user_row['role'] === 'agent' && $user_row['status'] === 'pending') {
                    $error = "Your account is pending Super Admin approval. Please contact support.";
                } elseif ($user_row['role'] === 'agent' && $user_row['status'] === 'suspended') {
                    $error = "Your account is currently suspended by the Super Admin.";
                } else {
                    // Create Session
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_row['id'];
                    $_SESSION['username'] = $user_row['username'];
                    $_SESSION['user_role'] = $user_row['role'];
                    $_SESSION['user_email'] = $user_row['email'];
                    $_SESSION['LAST_ACTIVITY'] = time();

                    log_activity($pdo, $user_row['id'], 'LOGIN_SUCCESS', 'User logged in successfully.');

                    // Redirect based on role
                    if ($user_row['role'] === 'admin') {
                        header("Location: " . BASE_URL . "/admin/dashboard.php");
                    } elseif ($user_row['role'] === 'agent') {
                        header("Location: " . BASE_URL . "/agent/dashboard.php");
                    } else {
                        // Check if redirect url is set
                        $redirect = $_SESSION['redirect_url'] ?? (BASE_URL . '/index.php');
                        unset($_SESSION['redirect_url']);
                        header("Location: " . $redirect);
                    }
                    exit();
                }
            } else {
                $error = "Invalid username/email or password.";
                // System-wide error logger
                log_activity($pdo, null, 'LOGIN_FAILED', "Failed login attempt for: $login_input");
            }
        }
    }
}

// Include Header
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="glass-card p-5 mt-3">
            <div class="text-center mb-4">
                <i class="fa-solid fa-bus text-indigo" style="font-size: 3rem; color: #818cf8; filter: drop-shadow(0 0 15px rgba(129,140,248,0.4));"></i>
                <h2 class="fw-bold mt-3 text-white">Staff & Member Login</h2>
                <p class="text-secondary small">Access the ticketing booking engine, agent portal, or admin desk</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-3" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" autocomplete="off">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

                <div class="mb-4">
                    <label for="login_input" class="form-label text-secondary small fw-semibold">Username or Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="login_input" id="login_input" class="form-control form-control-swift border-start-0" placeholder="Enter username or email" style="border-radius: 0 12px 12px 0;" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label text-secondary small fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-key"></i></span>
                        <input type="password" name="password" id="password" class="form-control form-control-swift border-start-0" placeholder="Enter password" style="border-radius: 0 12px 12px 0;" required>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary-gradient py-3">Sign In</button>
                </div>
            </form>

            <div class="text-center mt-4">
                <span class="text-secondary small">Don't have an account? </span>
                <a href="<?= BASE_URL ?>/register.php" class="text-decoration-none small text-indigo" style="color: #818cf8; font-weight: 500;">Register as Agent/Customer</a>
            </div>
        </div>
    </div>
</div>

<?php
// Include Footer
require_once __DIR__ . '/includes/footer.php';
?>
