<?php
/**
 * Safe Owner Emergency Control Panel
 */
require_once __DIR__ . '/header.php';

$error = '';
$success = '';

// Handle configurations update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token validation failed.";
    } else {
        $action = $_POST['action'] ?? '';

        // UPDATE SYSTEM TOGGLES
        if ($action === 'update_settings') {
            $m_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
            $a_suspend = isset($_POST['suspend_agent_panel']) ? '1' : '0';
            $notice = trim($_POST['custom_notice'] ?? '');

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
                $stmt->execute([$m_mode]);

                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'suspend_agent_panel'");
                $stmt->execute([$a_suspend]);

                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'custom_notice'");
                $stmt->execute([$notice]);

                $pdo->commit();
                
                // Refresh local copies
                $GLOBALS['custom_notice'] = $notice;
                
                $success = "Emergency controls and global notice updated successfully!";
                log_activity($pdo, $_SESSION['user_id'], 'OWNER_EMERGENCY_UPDATE', "Maintenance: $m_mode, Agent Suspend: $a_suspend, Notice: $notice");
                
                // Trigger page refresh to apply settings cleanly
                echo "<script>setTimeout(function(){ window.location.href = '" . BASE_URL . "/admin/owner_control.php'; }, 1000);</script>";

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Failed to update configurations: " . $e->getMessage();
            }
        }
    }
}

// Fetch current configurations
try {
    $m_mode = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'")->fetchColumn();
    $a_suspend = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'suspend_agent_panel'")->fetchColumn();
    $notice = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'custom_notice'")->fetchColumn();
} catch (PDOException $e) {
    $m_mode = '0';
    $a_suspend = '0';
    $notice = '';
}

// Fetch security audit logs (Limit 50)
try {
    $logs_stmt = $pdo->query("
        SELECT 
            al.*,
            u.username AS performer
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 50
    ");
    $audit_logs = $logs_stmt->fetchAll();
} catch (PDOException $e) {
    $audit_logs = [];
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

<div class="row g-4">
    <!-- Configuration Controls -->
    <div class="col-lg-6">
        <div class="glass-card p-5 h-100">
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-shield-halved text-indigo me-2"></i>System Controls</h4>
            <span class="text-secondary small d-block mb-4">Toggle system operational modes instantly. All controls require active logging and confirmations.</span>

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="update_settings">

                <!-- Maintenance mode toggle -->
                <div class="form-check form-switch mb-4 p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10">
                    <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" name="maintenance_mode" id="mModeToggle" value="1" <?= ($m_mode === '1') ? 'checked' : '' ?>>
                    <label class="form-check-label text-white fw-bold d-block" for="mModeToggle">
                        Emergency Maintenance Mode
                    </label>
                    <span class="text-secondary small">Redirects all customers to a gorgeous offline landing notice. Super Admin continues to enjoy full operational access.</span>
                </div>

                <!-- Suspend Agent toggle -->
                <div class="form-check form-switch mb-4 p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10">
                    <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" name="suspend_agent_panel" id="aSuspendToggle" value="1" <?= ($a_suspend === '1') ? 'checked' : '' ?>>
                    <label class="form-check-label text-white fw-bold d-block" for="aSuspendToggle">
                        Suspend Agent Panel Access
                    </label>
                    <span class="text-secondary small">Instantly locks out all travel agents. Active sessions are destroyed. Prevents any route edits, schedules, or commission changes.</span>
                </div>

                <!-- Global ticker notice broadcaster -->
                <div class="mb-4">
                    <label for="custom_notice" class="form-label text-secondary small fw-semibold">Global Broadcast Notice (Ticker)</label>
                    <textarea name="custom_notice" id="custom_notice" class="form-control form-control-swift" rows="3" placeholder="Enter marquee alert notice shown to all portal users..."><?= htmlspecialchars($notice) ?></textarea>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary-gradient py-3 text-uppercase fw-bold" onclick="return confirm('Apply changes to global system settings?')">Apply Configuration</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Safe Database Backups -->
    <div class="col-lg-6">
        <div class="glass-card p-5 h-100 d-flex flex-column justify-content-between">
            <div>
                <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-database text-pink me-2"></i>Database Backups</h4>
                <p class="text-secondary lead">Securely download a complete SQL backup of your MySQL database.</p>
                <p class="text-secondary small">Generates a complete SQL script dump containing both the relational table structure and seeded ticket booking database records. Highly recommended before running structural updates.</p>
            </div>

            <div class="p-4 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10 text-center my-4">
                <i class="fa-solid fa-server text-indigo mb-3" style="font-size: 3rem; color: #818cf8;"></i>
                <h6 class="text-white fw-bold">SwiftBus Core Server Connection</h6>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 rounded-pill mt-2"><i class="fa-solid fa-circle text-success me-2 animate-pulse" style="font-size:0.5rem;"></i>ONLINE & SECURE</span>
            </div>

            <div class="d-grid">
                <a href="<?= BASE_URL ?>/ajax/db_backup.php" class="btn btn-secondary-glass py-3 fw-bold text-uppercase" style="letter-spacing: 0.5px;"><i class="fa-solid fa-download me-2 text-pink"></i>Download Safe SQL Backup</a>
            </div>
        </div>
    </div>
</div>

<!-- COMPREHENSIVE AUDIT TRAIL LOGS -->
<div class="glass-card p-4 mt-5">
    <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-list-check text-indigo me-2"></i>Comprehensive Audit Trail Log (Latest 50 Actions)</h5>
    
    <?php if (count($audit_logs) === 0): ?>
        <div class="text-center py-4 text-secondary small">No audit log records found in database.</div>
    <?php else: ?>
        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-swift table-dark table-hover table-borderless align-middle" style="font-size:0.9rem;">
                <thead>
                    <tr style="position: sticky; top: 0; background: #0f172a; z-index:10;">
                        <th>Performer</th>
                        <th>Action Code</th>
                        <th>Details / Description</th>
                        <th>IP Origin</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit_logs as $log): ?>
                        <tr class="border-bottom border-secondary border-opacity-10">
                            <td>
                                <span class="fw-bold text-white small"><i class="fa-solid fa-user me-2 text-indigo"></i><?= htmlspecialchars($log['performer'] ?? 'SYSTEM') ?></span>
                            </td>
                            <td><span class="badge bg-secondary font-monospace" style="font-size: 0.75rem;"><?= htmlspecialchars($log['action_type']) ?></span></td>
                            <td class="text-secondary small" style="max-width: 400px; word-wrap: break-word;"><?= htmlspecialchars($log['details']) ?></td>
                            <td><span class="font-monospace text-secondary" style="font-size: 0.8rem;"><?= htmlspecialchars($log['ip_address']) ?></span></td>
                            <td class="text-secondary small"><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></td>
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
