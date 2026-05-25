<?php
/**
 * Registration Controller & View
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$page_title = "Register";

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
        $error = "Security validation failed. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'customer';

        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = "Please fill in all general fields.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif (!in_array($role, ['customer', 'agent'])) {
            $error = "Invalid role selection.";
        } else {
            // Verify if email or username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn()) {
                $error = "Username or Email already registered.";
            } else {
                // If agent, validate agent profile fields
                $agency_name = trim($_POST['agency_name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');

                if ($role === 'agent' && (empty($agency_name) || empty($phone))) {
                    $error = "Agency Name and Phone are required for Agent registration.";
                } else {
                    // All validations passed, insert user
                    try {
                        $pdo->beginTransaction();

                        $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
                        $status = ($role === 'agent') ? 'pending' : 'approved';

                        $insertUser = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                        $insertUser->execute([$username, $email, $hashed_pass, $role, $status]);
                        $new_user_id = $pdo->lastInsertId();

                        // Create agent profile if role is agent
                        if ($role === 'agent') {
                            $insertProfile = $pdo->prepare("INSERT INTO agent_profiles (user_id, agency_name, phone) VALUES (?, ?, ?)");
                            $insertProfile->execute([$new_user_id, $agency_name, $phone]);
                            $success = "Registration successful! Your agency account is pending approval by the Super Admin.";
                            log_activity($pdo, $new_user_id, 'AGENT_REGISTER', "Agent signed up: $agency_name ($phone)");
                        } else {
                            $success = "Registration successful! You can now log in.";
                            log_activity($pdo, $new_user_id, 'CUSTOMER_REGISTER', "Customer signed up: $username");
                        }

                        $pdo->commit();
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "Registration failed. Please try again. Info: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="glass-card p-5 mt-3">
            <div class="text-center mb-4">
                <i class="fa-solid fa-user-plus text-gradient-purple" style="font-size: 3rem; filter: drop-shadow(0 0 15px rgba(139,92,246,0.3));"></i>
                <h2 class="fw-bold mt-3 text-white">Create An Account</h2>
                <p class="text-secondary small">Register to book tickets or manage fleet services</p>
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

                <!-- Role Selection -->
                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">I want to register as:</label>
                    <div class="d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role" id="role_customer" value="customer" checked>
                            <label class="form-check-label text-white" for="role_customer">
                                Customer (Book Tickets)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role" id="role_agent" value="agent">
                            <label class="form-check-label text-white" for="role_agent">
                                Travel Agent (Manage Fleet & Earn)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="username" class="form-label text-secondary small fw-semibold">Username</label>
                        <input type="text" name="username" id="username" class="form-control form-control-swift" placeholder="Username" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="email" class="form-label text-secondary small fw-semibold">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control form-control-swift" placeholder="name@domain.com" required>
                    </div>
                </div>

                <!-- Password Row -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label for="password" class="form-label text-secondary small fw-semibold">Password</label>
                        <input type="password" name="password" id="password" class="form-control form-control-swift" placeholder="Min. 6 chars" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="confirm_password" class="form-label text-secondary small fw-semibold">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-swift" placeholder="Re-enter password" required>
                    </div>
                </div>

                <!-- Agent Specific Section (jQuery Toggle) -->
                <div id="agent_fields_section" class="p-4 rounded-4 mb-4 border border-secondary bg-dark bg-opacity-20" style="display: none;">
                    <h5 class="text-indigo mb-3 fw-bold"><i class="fa-solid fa-hotel me-2"></i>Agency Credentials</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="agency_name" class="form-label text-secondary small fw-semibold">Agency Name</label>
                            <input type="text" name="agency_name" id="agency_name" class="form-control form-control-swift" placeholder="e.g. Golden Tours">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label text-secondary small fw-semibold">Contact Mobile</label>
                            <input type="text" name="phone" id="phone" class="form-control form-control-swift" placeholder="Phone Number">
                        </div>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary-gradient py-3">Register Now</button>
                </div>
            </form>

            <div class="text-center mt-4">
                <span class="text-secondary small">Already have an account? </span>
                <a href="<?= BASE_URL ?>/login.php" class="text-decoration-none small text-indigo" style="color: #818cf8; font-weight: 500;">Login here</a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Dynamic Form Toggle based on role
    $('input[name="role"]').change(function() {
        if ($(this).val() === 'agent') {
            $('#agent_fields_section').slideDown(400);
            $('#agency_name, #phone').attr('required', true);
        } else {
            $('#agent_fields_section').slideUp(400);
            $('#agency_name, #phone').removeAttr('required').val('');
        }
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
