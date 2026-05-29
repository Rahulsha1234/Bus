<?php
/**
 * Operator Monitor & Approval Panel
 */
require_once __DIR__ . '/header.php';

$error = '';
$success = '';

// Handle actions (Approve, Suspend, Reactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Security token validation failed.";
    } else {
        $action = $_POST['action'] ?? '';
        $operator_user_id = intval($_POST['operator_id'] ?? 0);

        if ($operator_user_id > 0) {
            $new_status = '';
            if ($action === 'approve') {
                $new_status = 'approved';
            } elseif ($action === 'suspend') {
                $new_status = 'suspended';
            } elseif ($action === 'reactivate') {
                $new_status = 'approved';
            }

            if (!empty($new_status)) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'admin'");
                    $stmt->execute([$new_status, $operator_user_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $_SESSION['success'] = "Operator status updated to " . strtoupper($new_status) . " successfully!";
                        log_activity($pdo, $_SESSION['user_id'], 'OPERATOR_STATUS_CHANGE', "Updated status of operator user ID $operator_user_id to $new_status");
                    } else {
                        $_SESSION['error'] = "Failed to update operator status. Verify user exists and is an operator.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error during write: " . $e->getMessage();
                }
            }
        }
    }
    if (!headers_sent()) {
        header("Location: " . $_SERVER['PHP_SELF']);
    } else {
        echo "<script>window.location.replace('" . $_SERVER['PHP_SELF'] . "');</script>";
    }
    exit();
}

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Fetch Operators List
try {
    $stmt = $pdo->query("
        SELECT 
            id AS user_id,
            username,
            email,
            status,
            operator_code,
            created_at
        FROM users
        WHERE role = 'admin'
        ORDER BY created_at DESC
    ");
    $operators = $stmt->fetchAll();
} catch (PDOException $e) {
    $operators = [];
}
?>

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

<div class="glass-card p-4">
    <h5 class="text-white fw-bold mb-4"><i class="fa-solid fa-users-gear text-indigo me-2"></i>Operator Management Control Desk</h5>
    
    <?php if (count($operators) === 0): ?>
        <div class="text-center py-5 text-secondary small">No bus operators registered on the platform yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle datatable-swift">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Operator Code</th>
                        <th>Contact Email</th>
                        <th>Status</th>
                        <th>Registered Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operators as $operator): ?>
                        <tr>
                            <td><span class="fw-semibold text-white fs-6"><?= htmlspecialchars($operator['username']) ?></span></td>
                            <td><code class="text-warning font-monospace small"><?= htmlspecialchars($operator['operator_code'] ?? 'N/A') ?></code></td>
                            <td><?= htmlspecialchars($operator['email']) ?></td>
                            <td>
                                <?php if ($operator['status'] === 'approved'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">APPROVED / ACTIVE</span>
                                <?php elseif ($operator['status'] === 'pending'): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">PENDING APPROVAL</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">SUSPENDED</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-secondary small"><?= date('d M Y', strtotime($operator['created_at'])) ?></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <?php if ($operator['status'] === 'pending'): ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="operator_id" value="<?= $operator['user_id'] ?>">
                                            <button type="submit" class="btn btn-success py-1 px-3 small" style="font-size:0.75rem;"><i class="fa-solid fa-circle-check me-1"></i>Approve</button>
                                        </form>
                                    <?php elseif ($operator['status'] === 'approved'): ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="action" value="suspend">
                                            <input type="hidden" name="operator_id" value="<?= $operator['user_id'] ?>">
                                            <button type="submit" class="btn btn-danger py-1 px-3 small" style="font-size:0.75rem;" onclick="return confirm('Suspend operations for this operator? This will lock all operator functionalities.')"><i class="fa-solid fa-user-slash me-1"></i>Suspend</button>
                                        </form>
                                    <?php else: ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="action" value="reactivate">
                                            <input type="hidden" name="operator_id" value="<?= $operator['user_id'] ?>">
                                            <button type="submit" class="btn btn-indigo py-1 px-3 small" style="font-size:0.75rem;"><i class="fa-solid fa-user-check me-1"></i>Reactivate</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
