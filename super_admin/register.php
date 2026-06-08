<?php
/**
 * Super Admin Registration Portal
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

$page_title = __('super_admin_registration_title', "Super Admin Registration");

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = __('security_validation_failed_try_again', "Security validation failed. Please try again.");
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = 'super_admin';

        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = __('please_fill_all_fields', "Please fill in all fields.");
        } elseif ($password !== $confirm_password) {
            $error = __('passwords_do_not_match', "Passwords do not match.");
        } elseif (strlen($password) < 6) {
            $error = __('password_too_short', "Password must be at least 6 characters long.");
        } else {
            // Verify if email or username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn()) {
                $error = __('username_email_exists', "Username or Email already registered.");
            } else {
                try {
                    $pdo->beginTransaction();

                    $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
                    $status = 'approved'; // Super Admins are auto-approved

                    $insertUser = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                    $insertUser->execute([$username, $email, $hashed_pass, $role, $status]);
                    $new_user_id = $pdo->lastInsertId();

                    $success = __('super_admin_reg_success', "Super Admin registration successful! You can now log in.");
                    log_activity($pdo, $new_user_id, 'SUPER_ADMIN_REGISTER', "Super Admin signed up: $username");

                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = __('registration_failed_info', "Registration failed. Please try again. Info: ") . $e->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-sm-12">
        <div class="glass-card p-5 mt-3">
            <div class="text-center mb-4">
                <i class="fa-solid fa-user-shield text-indigo" style="font-size: 3rem; color: var(--accent); filter: drop-shadow(0 0 15px rgba(212,175,55,0.4));"></i>
                <h2 class="fw-bold mt-3 text-white"><?= __('super_admin_registration_title', 'Super Admin Registration') ?></h2>
                <p class="text-secondary small"><?= __('register_sys_admin_subtitle', 'Register a system administrator account') ?></p>
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

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" autocomplete="off" id="registerForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

                <div class="mb-4">
                    <label for="username" class="form-label text-secondary small fw-semibold"><?= __('admin_username_lbl', 'Admin Username') ?></label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="username" id="username" class="form-control form-control-swift" placeholder="<?= __('admin_username_placeholder', 'Enter username') ?>" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="email" class="form-label text-secondary small fw-semibold"><?= __('email_address_label', 'Email Address') ?></label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control form-control-swift" placeholder="name@domain.com" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="password" class="form-label text-secondary small fw-semibold"><?= __('password_label', 'Password') ?></label>
                        <input type="password" name="password" id="password" class="form-control form-control-swift" placeholder="<?= __('password_min_chars_placeholder', 'Min. 6 chars') ?>" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="confirm_password" class="form-label text-secondary small fw-semibold"><?= __('confirm_password_label', 'Confirm Password') ?></label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-swift" placeholder="<?= __('re_enter_password_placeholder', 'Re-enter password') ?>" required>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary-gradient py-3"><?= __('register_super_admin_btn', 'Register Super Admin') ?></button>
                </div>
            </form>

            <div class="text-center mt-4">
                <span class="text-secondary small"><?= __('already_have_account', 'Already have an account? ') ?></span>
                <a href="<?= BASE_URL ?>/login.php" class="text-decoration-none small" style="color: var(--accent); font-weight: 500;"><?= __('login_here_link', 'Login here') ?></a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
