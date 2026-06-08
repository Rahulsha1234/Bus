<?php
/**
 * Agent Monitor & Approval Panel
 */
require_once __DIR__ . '/header.php';

$error = '';
$success = '';

// Handle actions (Approve, Suspend)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = __('security_validation_failed', "Security token validation failed.");
    } else {
        $action = $_POST['action'] ?? '';
        $agent_user_id = intval($_POST['agent_id'] ?? 0);

        if ($agent_user_id > 0) {
            // Check status update
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
                        $success = __('agent_status_updated_prefix', "Agent status updated to ") . strtoupper($new_status) . __('successfully_suffix', " successfully!");
                        log_activity($pdo, $_SESSION['user_id'], 'AGENT_STATUS_CHANGE', "Updated status of agent user ID $agent_user_id to $new_status");
                    } else {
                        $error = __('agent_status_update_failed', "Failed to update agent status. Verify agent user exists.");
                    }
                } catch (PDOException $e) {
                    $error = __('agent_status_db_error', "Database error during write: ") . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Agents List with Pagination
try {
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'agent'");
    $total_records = intval($count_stmt->fetchColumn());

    $limit = 10;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;
    $total_pages = ceil($total_records / $limit);

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
        LEFT JOIN agent_profiles ap ON u.id = ap.user_id
        WHERE u.role = 'agent'
        ORDER BY u.created_at DESC
        LIMIT " . intval($limit) . " OFFSET " . intval($offset) . "
    ");
    $stmt->execute();
    $agents = $stmt->fetchAll();
} catch (PDOException $e) {
    $agents = [];
    $total_records = 0;
    $total_pages = 0;
    $page = 1;
    $offset = 0;
    $limit = 10;
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
    <h5 class="text-white fw-bold mb-4"><i class="fa-solid fa-users-gear text-indigo me-2"></i><?= __('agency_management_control_desk_hdr', 'Agency Management Control Desk') ?></h5>
    
    <?php if (count($agents) === 0): ?>
        <div class="text-center py-5 text-secondary small"><?= __('no_travel_agents_registered_yet', 'No travel agents registered on the platform yet.') ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle datatable-swift">
                <thead>
                    <tr>
                        <th><?= __('agency_name_col', 'Agency name') ?></th>
                        <th><?= __('user_credentials_col', 'User Credentials') ?></th>
                        <th><?= __('contact_email_col', 'Contact Email') ?></th>
                        <th><?= __('phone_number_col', 'Phone Number') ?></th>
                        <th><?= __('status_col', 'Status') ?></th>
                        <th><?= __('registered_date_col', 'Registered Date') ?></th>
                        <th class="text-end"><?= __('actions_col', 'Actions') ?></th>
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
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><?= __('status_approved_active', 'APPROVED / ACTIVE') ?></span>
                                <?php elseif ($agent['status'] === 'pending'): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1"><?= __('status_pending_approval', 'PENDING APPROVAL') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><?= __('status_suspended', 'SUSPENDED') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-secondary small"><?= date('d M Y', strtotime($agent['created_at'])) ?></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <?php if ($agent['status'] === 'pending'): ?>
                                        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="agent_id" value="<?= $agent['user_id'] ?>">
                                            <button type="submit" class="btn btn-success py-1 px-3 small" style="font-size:0.75rem;"><i class="fa-solid fa-circle-check me-1"></i><?= __('approve_btn', 'Approve') ?></button>
                                        </form>
                                    <?php elseif ($agent['status'] === 'approved'): ?>
                                        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="action" value="suspend">
                                            <input type="hidden" name="agent_id" value="<?= $agent['user_id'] ?>">
                                            <button type="submit" class="btn btn-danger py-1 px-3 small" style="font-size:0.75rem;" onclick="return confirm('<?= __('suspend_agency_confirm_q', 'Suspend operations for this agency? All agent panels will be locked.') ?>')"><i class="fa-solid fa-user-slash me-1"></i><?= __('suspend_btn', 'Suspend') ?></button>
                                        </form>
                                    <?php else: ?>
                                        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="action" value="reactivate">
                                            <input type="hidden" name="agent_id" value="<?= $agent['user_id'] ?>">
                                            <button type="submit" class="btn btn-indigo py-1 px-3 small" style="font-size:0.75rem;"><i class="fa-solid fa-user-check me-1"></i><?= __('reactivate_btn', 'Reactivate') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-secondary small">
                    <?= __('showing_label', 'Showing ') ?><?= $offset + 1 ?><?= __('to_mid_label', ' to ') ?><?= min($total_records, $offset + $limit) ?><?= __('of_mid_label', ' of ') ?><?= $total_records ?><?= __('entries_label', ' entries') ?>
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-swift mb-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
