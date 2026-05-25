<?php
/**
 * Universal Login Page for Customers, Agents, and Administrators
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
        $role_selection = $_POST['role_selection'] ?? 'customer'; // customer or agent/admin

        if (empty($login_input) || empty($password)) {
            $error = "Please fill in all fields.";
        } else {
            // Find user by username or email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :email LIMIT 1");
            $stmt->execute([
                ':username' => $login_input,
                ':email' => $login_input
            ]);
            $user_row = $stmt->fetch();

            if ($user_row && password_verify($password, $user_row['password'])) {
                // Check if user matches role selection
                if ($role_selection === 'customer' && $user_row['role'] !== 'customer') {
                    $error = "This account is a staff account. Please login using the Staff/Agent option.";
                } elseif ($role_selection === 'staff' && $user_row['role'] === 'customer') {
                    $error = "This account is a customer account. Please login using the Customer option.";
                } elseif ($user_row['role'] === 'agent' && $user_row['status'] === 'pending') {
                    $error = "Your agency account is pending approval by the Super Admin.";
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

                    log_activity($pdo, $user_row['id'], 'LOGIN_SUCCESS', "User logged in successfully as {$user_row['role']}.");

                    // Redirect
                    if ($user_row['role'] === 'admin') {
                        header("Location: " . BASE_URL . "/admin/dashboard.php");
                    } elseif ($user_row['role'] === 'agent') {
                        header("Location: " . BASE_URL . "/agent/dashboard.php");
                    } else {
                        $redirect = $_SESSION['redirect_url'] ?? (BASE_URL . '/index.php');
                        unset($_SESSION['redirect_url']);
                        header("Location: " . $redirect);
                    }
                    exit();
                }
            } else {
                $error = "Invalid username/email or password.";
                log_activity($pdo, null, 'LOGIN_FAILED', "Failed login attempt for: $login_input");
            }
        }
    }
}

// Include Header
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 col-sm-12">
        <div class="glass-card p-5 mt-3">
            <div class="text-center mb-4">
                <i class="fa-solid fa-bus text-indigo animate-pulse" id="login-icon" style="font-size: 3rem; color: #818cf8; filter: drop-shadow(0 0 15px rgba(129,140,248,0.4));"></i>
                <h2 class="fw-bold mt-3 text-white" id="login-title">Customer Login</h2>
                <p class="text-secondary small" id="login-desc">Access the ticket booking engine</p>
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

            <!-- Role Selector Tabs -->
            <div class="d-flex justify-content-center gap-2 mb-4">
                <button type="button" class="btn btn-secondary-glass flex-grow-1 active font-semibold" id="tab-customer" onclick="setRole('customer')">
                    <i class="fa-solid fa-user me-2"></i>Customer
                </button>
                <button type="button" class="btn btn-secondary-glass flex-grow-1 font-semibold" id="tab-staff" onclick="setRole('staff')">
                    <i class="fa-solid fa-briefcase me-2"></i>Staff / Agent
                </button>
            </div>

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" autocomplete="off">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="role_selection" id="role_selection" value="customer">

                <div class="mb-4">
                    <label for="login_input" class="form-label text-secondary small fw-semibold">Username or Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-user" id="input-icon"></i></span>
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
                    <button type="submit" class="btn btn-primary-gradient py-3 font-semibold" id="btn-submit">Sign In</button>
                </div>
            </form>

            <div class="text-center mt-4" id="register-link-container">
                <span class="text-secondary small">Don't have an account? </span>
                <a href="<?= BASE_URL ?>/register.php" class="text-decoration-none small text-indigo" style="color: #818cf8; font-weight: 500;">Register here</a>
            </div>
        </div>
    </div>
</div>

<script>
function setRole(role) {
    document.getElementById('role_selection').value = role;
    
    const tabCustomer = document.getElementById('tab-customer');
    const tabStaff = document.getElementById('tab-staff');
    const loginTitle = document.getElementById('login-title');
    const loginDesc = document.getElementById('login-desc');
    const loginIcon = document.getElementById('login-icon');
    const inputIcon = document.getElementById('input-icon');
    const btnSubmit = document.getElementById('btn-submit');
    const registerContainer = document.getElementById('register-link-container');
    
    if (role === 'customer') {
        tabCustomer.classList.add('active');
        tabStaff.classList.remove('active');
        loginTitle.textContent = 'Customer Login';
        loginDesc.textContent = 'Access the ticket booking engine';
        loginIcon.className = 'fa-solid fa-bus text-indigo animate-pulse';
        inputIcon.className = 'fa-solid fa-user';
        btnSubmit.textContent = 'Sign In';
        registerContainer.style.display = 'block';
    } else {
        tabCustomer.classList.remove('active');
        tabStaff.classList.add('active');
        loginTitle.textContent = 'Staff / Agent Login';
        loginDesc.textContent = 'Access travel agency desk & schedule management';
        loginIcon.className = 'fa-solid fa-briefcase text-indigo animate-pulse';
        inputIcon.className = 'fa-solid fa-user-tie';
        btnSubmit.textContent = 'Sign In as Staff/Agent';
        registerContainer.style.display = 'none';
    }
</script>

<?php
// Include Footer
require_once __DIR__ . '/includes/footer.php';
?>
