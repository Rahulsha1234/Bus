<?php
/**
 * Agent Registration Portal
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

$page_title = "Agent Partner Registration";

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
        $agency_name = trim($_POST['agency_name'] ?? '');
        $agency_name = trim($_POST['agency_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $operator_code = strtoupper(trim($_POST['operator_code'] ?? ''));
        $role = 'agent';

        if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($agency_name) || empty($phone) || empty($operator_code)) {
            $error = "Please fill in all fields including agency credentials and Operator Code.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            // Verify Operator Code exists and belongs to approved Bus Operator
            $op_stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND status = 'approved' AND operator_code = ? LIMIT 1");
            $op_stmt->execute([$operator_code]);
            $admin_id = $op_stmt->fetchColumn();

            if (!$admin_id) {
                $error = "Invalid or inactive Operator Code. Please check with your Bus Operator.";
            } else {
                // Verify if email or username already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute([$username, $email]);
                if ($stmt->fetchColumn()) {
                    $error = "Username or Email already registered.";
                } else {
                    try {
                        $pdo->beginTransaction();

                        $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
                        $status = 'pending'; // Agents are pending admin approval

                        $insertUser = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                        $insertUser->execute([$username, $email, $hashed_pass, $role, $status]);
                        $new_user_id = $pdo->lastInsertId();

                        $insertProfile = $pdo->prepare("INSERT INTO agent_profiles (user_id, agency_name, phone, admin_id) VALUES (?, ?, ?, ?)");
                        $insertProfile->execute([$new_user_id, $agency_name, $phone, $admin_id]);

                        $success = "Registration successful! Your agency account is pending approval by the Bus Operator.";
                        log_activity($pdo, $new_user_id, 'AGENT_REGISTER', "Agent signed up: $agency_name under Admin ID: $admin_id");

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

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-sm-12">
        <div class="glass-card p-5 mt-3">
            <div class="text-center mb-4">
                <i class="fa-solid fa-handshake text-indigo" style="font-size: 3rem; color: var(--accent); filter: drop-shadow(0 0 15px rgba(212,175,55,0.4));"></i>
                <h2 class="fw-bold mt-3 text-white">Agent Registration</h2>
                <p class="text-secondary small">Register to partner with a Bus Operator and earn commissions</p>
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
                    <label for="username" class="form-label text-secondary small fw-semibold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="username" id="username" class="form-control form-control-swift" placeholder="Enter username" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="email" class="form-label text-secondary small fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control form-control-swift" placeholder="name@domain.com" required>
                    </div>
                </div>

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

                <div class="p-4 rounded-4 mb-4 border border-secondary bg-dark bg-opacity-20">
                    <h5 class="mb-3 fw-bold" style="color: var(--accent);"><i class="fa-solid fa-briefcase me-2"></i>Agency Details</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="agency_name" class="form-label text-secondary small fw-semibold">Agency Name</label>
                            <input type="text" name="agency_name" id="agency_name" class="form-control form-control-swift" placeholder="e.g. Golden Travels" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label text-secondary small fw-semibold">Contact Mobile</label>
                            <input type="text" name="phone" id="phone" class="form-control form-control-swift" placeholder="Phone Number" required>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="operator_code" class="form-label text-secondary small fw-semibold">Enter Bus Operator Code</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-key"></i></span>
                            <input type="text" name="operator_code" id="operator_code" class="form-control form-control-swift" placeholder="e.g. 7A8D3F9E12" required>
                        </div>
                        <div class="small text-secondary mt-1" style="font-size:0.75rem;"><i class="fa-solid fa-circle-info me-1"></i> Ask your partner Bus Operator for their unique 10-digit connection code.</div>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary-gradient py-3">Register Account</button>
                </div>
            </form>

            <div class="text-center mt-4">
                <span class="text-secondary small">Already have an account? </span>
                <a href="<?= BASE_URL ?>/login.php" class="text-decoration-none small" style="color: var(--accent); font-weight: 500;">Login here</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
