<?php
/**
 * Operator-specific Travel Agent Approval & Management Panel
 */
require_once __DIR__ . '/header.php';

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle actions (Approve, Suspend, Reactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Security token validation failed.";
    } else {
        $action = $_POST['action'] ?? '';
        $agent_user_id = intval($_POST['agent_id'] ?? 0);

        if ($agent_user_id > 0) {
            // Verify that this agent is indeed linked to this operator (admin_id)
            $chk = $pdo->prepare("SELECT 1 FROM agent_profiles WHERE user_id = ? AND admin_id = ? LIMIT 1");
            $chk->execute([$agent_user_id, $admin_id]);
            
            if (!$chk->fetchColumn()) {
                $_SESSION['error'] = "Unauthorized action. This agent does not belong to your agency.";
            } else {
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
                        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'agent'");
                        $stmt->execute([$new_status, $agent_user_id]);
                        
                        if ($stmt->rowCount() > 0) {
                            $_SESSION['success'] = "Agent status updated to " . strtoupper($new_status) . " successfully!";
                            log_activity($pdo, $admin_id, 'AGENT_STATUS_CHANGE_BY_OPERATOR', "Updated status of agent user ID $agent_user_id to $new_status");
                        } else {
                            $_SESSION['error'] = "Failed to update agent status. Verify agent user exists.";
                        }
                    } catch (PDOException $e) {
                        $_SESSION['error'] = "Database error during status update: " . $e->getMessage();
                    }
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

// Fetch Travel Agents registered under this Operator (Admin)
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id AS user_id,
            u.username,
            u.email,
            u.status,
            u.created_at,
            ap.agency_name,
            ap.phone
        FROM users u
        INNER JOIN agent_profiles ap ON u.id = ap.user_id
        WHERE u.role = 'agent' AND ap.admin_id = ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$admin_id]);
    $agents = $stmt->fetchAll();
} catch (PDOException $e) {
    $agents = [];
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
    <h5 class="text-white fw-bold mb-4"><i class="fa-solid fa-users-gear text-indigo me-2"></i>Agency Management Control Desk</h5>
    
    <?php if (count($agents) === 0): ?>
        <div class="text-center py-5 text-secondary small">No travel agents registered under your operator account yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle">
                <thead>
                    <tr>
                        <th>Agency name</th>
                        <th>User Credentials</th>
                        <th>Contact Email</th>
                        <th>Phone Number</th>
                        <th>Status</th>
                        <th>Registered Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agents as $agent): ?>
                        <tr>
                            <td><span class="fw-semibold text-white fs-6"><?= htmlspecialchars($agent['agency_name'] ?? 'N/A') ?></span></td>
                            <td><span class="small text-secondary"><i class="fa-solid fa-user me-2"></i><?= htmlspecialchars($agent['username']) ?></span></td>
                            <td><?= htmlspecialchars($agent['email']) ?></td>
                            <td><?= htmlspecialchars($agent['phone'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($agent['status'] === 'approved'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">APPROVED / ACTIVE</span>
                                <?php elseif ($agent['status'] === 'pending'): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">PENDING APPROVAL</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">SUSPENDED</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-secondary small"><?= date('d M Y', strtotime($agent['created_at'])) ?></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <?php if ($agent['status'] === 'pending'): ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="agent_id" value="<?= $agent['user_id'] ?>">
                                            <button type="submit" class="btn btn-success py-1 px-3 small" style="font-size:0.75rem;"><i class="fa-solid fa-circle-check me-1"></i>Approve</button>
                                        </form>
                                    <?php elseif ($agent['status'] === 'approved'): ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="action" value="suspend">
                                            <input type="hidden" name="agent_id" value="<?= $agent['user_id'] ?>">
                                            <button type="submit" class="btn btn-danger py-1 px-3 small" style="font-size:0.75rem;" onclick="return confirm('Suspend operations for this travel agent?')"><i class="fa-solid fa-user-slash me-1"></i>Suspend</button>
                                        </form>
                                    <?php else: ?>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="action" value="reactivate">
                                            <input type="hidden" name="agent_id" value="<?= $agent['user_id'] ?>">
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
