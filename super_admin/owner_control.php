<?php
/**
 * Safe Owner Emergency Control Panel
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('super_admin');

$page_title = __('owner_emergency_control_panel_title', "Owner Emergency Controls");
require_once __DIR__ . '/header.php';

$error = '';
$success = '';

// Handle configurations update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = __('security_validation_failed', "Security token validation failed.");
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
                
                $success = __('emergency_controls_notice_updated', "Emergency controls and global notice updated successfully!");
                log_activity($pdo, $_SESSION['user_id'], 'OWNER_EMERGENCY_UPDATE', "Maintenance: $m_mode, Agent Suspend: $a_suspend, Notice: $notice");
                
                // Trigger page refresh to apply settings cleanly
                echo "<script>setTimeout(function(){ window.location.href = '" . BASE_URL . "/super_admin/owner_control.php'; }, 1000);</script>";

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = __('failed_to_update_configurations', "Failed to update configurations: ") . $e->getMessage();
            }
        } elseif ($action === 'update_gst') {
            $g_rate = floatval($_POST['gst_rate'] ?? 5.00);
            $g_status = isset($_POST['gst_status']) ? '1' : '0';
            $g_name = trim($_POST['gst_name'] ?? 'GST');
            $g_effective_date = trim($_POST['gst_effective_date'] ?? date('Y-m-d'));

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'gst_rate'");
                $stmt->execute([$g_rate]);

                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'gst_status'");
                $stmt->execute([$g_status]);

                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'gst_name'");
                $stmt->execute([$g_name]);

                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'gst_effective_date'");
                $stmt->execute([$g_effective_date]);

                $pdo->commit();
                
                $success = "GST Settings updated successfully!";
                log_activity($pdo, $_SESSION['user_id'], 'OWNER_GST_UPDATE', "Rate: $g_rate, Status: $g_status, Name: $g_name, Date: $g_effective_date");
                
                echo "<script>setTimeout(function(){ window.location.href = '" . BASE_URL . "/super_admin/owner_control.php'; }, 1000);</script>";

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Failed to update GST settings: " . $e->getMessage();
            }
        }
    }
}

// Fetch current configurations
try {
    $m_mode = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'")->fetchColumn();
    $a_suspend = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'suspend_agent_panel'")->fetchColumn();
    $notice = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'custom_notice'")->fetchColumn();
    $gst_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'gst_rate'")->fetchColumn() ?: '5.00';
    $gst_status = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'gst_status'")->fetchColumn() ?: '1';
    $gst_name = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'gst_name'")->fetchColumn() ?: 'GST';
    $gst_effective_date = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'gst_effective_date'")->fetchColumn() ?: date('Y-m-d');
} catch (PDOException $e) {
    $m_mode = '0';
    $a_suspend = '0';
    $notice = '';
    $gst_rate = '5.00';
    $gst_status = '1';
    $gst_name = 'GST';
    $gst_effective_date = date('Y-m-d');
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
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-shield-halved text-indigo me-2"></i><?= __('system_controls_hdr', 'System Controls') ?></h4>
            <span class="text-secondary small d-block mb-4"><?= __('system_controls_subtitle', 'Toggle system operational modes instantly. All controls require active logging and confirmations.') ?></span>

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="update_settings">

                <!-- Maintenance mode toggle -->
                <div class="form-check form-switch mb-4 p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10">
                    <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" name="maintenance_mode" id="mModeToggle" value="1" <?= ($m_mode === '1') ? 'checked' : '' ?>>
                    <label class="form-check-label text-white fw-bold d-block" for="mModeToggle">
                        <?= __('emergency_maintenance_mode_lbl', 'Emergency Maintenance Mode') ?>
                    </label>
                    <span class="text-secondary small"><?= __('emergency_maintenance_mode_desc', 'Redirects all customers to a gorgeous offline landing notice. Super Admin continues to enjoy full operational access.') ?></span>
                </div>

                <!-- Suspend Agent toggle -->
                <div class="form-check form-switch mb-4 p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10">
                    <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" name="suspend_agent_panel" id="aSuspendToggle" value="1" <?= ($a_suspend === '1') ? 'checked' : '' ?>>
                    <label class="form-check-label text-white fw-bold d-block" for="aSuspendToggle">
                        <?= __('suspend_agent_panel_access_lbl', 'Suspend Agent Panel Access') ?>
                    </label>
                    <span class="text-secondary small"><?= __('suspend_agent_panel_access_desc', 'Instantly locks out all travel agents. Active sessions are destroyed. Prevents any route edits, schedules, or commission changes.') ?></span>
                </div>

                <!-- Global ticker notice broadcaster -->
                <div class="mb-4">
                    <label for="custom_notice" class="form-label text-secondary small fw-semibold"><?= __('global_broadcast_notice_lbl', 'Global Broadcast Notice (Ticker)') ?></label>
                    <textarea name="custom_notice" id="custom_notice" class="form-control form-control-swift" rows="3" placeholder="<?= __('global_broadcast_notice_placeholder', 'Enter marquee alert notice shown to all portal users...') ?>"><?= htmlspecialchars($notice) ?></textarea>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary-gradient py-3 text-uppercase fw-bold" onclick="return confirm('<?= __('apply_configuration_confirm_q', 'Apply changes to global system settings?') ?>')"><?= __('apply_configuration_btn', 'Apply Configuration') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Safe Database Backups -->
    <div class="col-lg-6">
        <div class="glass-card p-5 h-100 d-flex flex-column justify-content-between">
            <div>
                <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-database text-pink me-2"></i><?= __('database_backups_hdr', 'Database Backups') ?></h4>
                <p class="text-secondary lead"><?= __('secure_download_sql_backup_desc', 'Securely download a complete SQL backup of your MySQL database.') ?></p>
                <p class="text-secondary small"><?= __('sql_backup_additional_desc', 'Generates a complete SQL script dump containing both the relational table structure and seeded ticket booking database records. Highly recommended before running structural updates.') ?></p>
            </div>

            <div class="p-4 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10 text-center my-4">
                <i class="fa-solid fa-server text-indigo mb-3" style="font-size: 3rem; color: #818cf8;"></i>
                <h6 class="text-white fw-bold"><?= __('core_server_connection_lbl', 'SwiftBus Core Server Connection') ?></h6>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 rounded-pill mt-2"><i class="fa-solid fa-circle text-success me-2 animate-pulse" style="font-size:0.5rem;"></i><?= __('online_secure_lbl', 'ONLINE & SECURE') ?></span>
            </div>

            <div class="d-grid">
                <a href="<?= BASE_URL ?>/ajax/db_backup.php" class="btn btn-secondary-glass py-3 fw-bold text-uppercase" style="letter-spacing: 0.5px;"><i class="fa-solid fa-download me-2 text-pink"></i><?= __('download_safe_sql_backup_btn', 'Download Safe SQL Backup') ?></a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <!-- GST Configuration Card -->
    <div class="col-lg-6">
        <div class="glass-card p-5 h-100">
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-calculator text-indigo me-2"></i>GST Configuration</h4>
            <span class="text-secondary small d-block mb-4">Dynamically adjust global tax settings. Calculations are processed and verified securely on backend.</span>
            
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="update_gst">
                
                <div class="form-check form-switch mb-4 p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10">
                    <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" name="gst_status" id="gstStatusToggle" value="1" <?= ($gst_status === '1') ? 'checked' : '' ?>>
                    <label class="form-check-label text-white fw-bold d-block" for="gstStatusToggle">Enable Tax System</label>
                    <span class="text-secondary small">Activate or deactivate tax calculation globally across the booking pipeline.</span>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="gst_name" class="form-label text-secondary small fw-semibold">Tax Name</label>
                        <input type="text" name="gst_name" id="gst_name" class="form-control form-control-swift" value="<?= htmlspecialchars($gst_name) ?>" placeholder="e.g. GST" required>
                    </div>
                    <div class="col-md-6">
                        <label for="gst_rate" class="form-label text-secondary small fw-semibold">GST Rate (%)</label>
                        <input type="number" name="gst_rate" id="gst_rate" class="form-control form-control-swift" value="<?= htmlspecialchars($gst_rate) ?>" min="0" max="100" step="0.01" placeholder="e.g. 5.00" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="gst_effective_date" class="form-label text-secondary small fw-semibold">Effective Date</label>
                    <input type="date" name="gst_effective_date" id="gst_effective_date" class="form-control form-control-swift" value="<?= htmlspecialchars($gst_effective_date) ?>" required>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary-gradient py-3 text-uppercase fw-bold" onclick="return confirm('Apply changes to global GST settings?')">Save GST Configuration</button>
                </div>
            </form>
        </div>
    </div>

    <!-- GST Collection Report Card -->
    <div class="col-lg-6">
        <div class="glass-card p-5 h-100">
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-chart-line text-pink me-2"></i>GST Collection Report</h4>
            <span class="text-secondary small d-block mb-4">View summary metrics of GST collection filtered by period.</span>
            
            <?php
            // Calculate tax collections based on filter
            $filter = $_GET['report_filter'] ?? 'today';
            $date_cond = "1=1";
            if ($filter === 'today') {
                $date_cond = "DATE(created_at) = CURDATE()";
            } elseif ($filter === 'week') {
                $date_cond = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            } elseif ($filter === 'month') {
                $date_cond = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            }
            
            $gst_rep_stmt = $pdo->query("
                SELECT 
                    COUNT(*) AS total_bookings,
                    COALESCE(SUM(base_fare), 0.00) AS total_base,
                    COALESCE(SUM(gst_amount), 0.00) AS total_gst,
                    COALESCE(SUM(total_amount), 0.00) AS total_revenue
                FROM bookings 
                WHERE status != 'cancelled' AND $date_cond
            ");
            $gst_rep = $gst_rep_stmt->fetch();
            ?>
            
            <form method="GET" class="d-flex gap-2 mb-4">
                <select name="report_filter" class="form-select form-control-swift" onchange="this.form.submit()">
                    <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="week" <?= $filter === 'week' ? 'selected' : '' ?>>This Week</option>
                    <option value="month" <?= $filter === 'month' ? 'selected' : '' ?>>This Month</option>
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Time</option>
                </select>
            </form>
            
            <div class="row g-3">
                <div class="col-6">
                    <div class="p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10">
                        <span class="text-secondary small d-block mb-1">Total Bookings</span>
                        <h4 class="text-white fw-bold font-monospace"><?= $gst_rep['total_bookings'] ?></h4>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10">
                        <span class="text-secondary small d-block mb-1">Base Fare</span>
                        <h4 class="text-success fw-bold font-monospace">₹<?= number_format($gst_rep['total_base'], 2) ?></h4>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10">
                        <span class="text-secondary small d-block mb-1">GST Collected</span>
                        <h4 class="text-indigo fw-bold font-monospace">₹<?= number_format($gst_rep['total_gst'], 2) ?></h4>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10">
                        <span class="text-secondary small d-block mb-1">Total Revenue</span>
                        <h4 class="text-white fw-bold font-monospace">₹<?= number_format($gst_rep['total_revenue'], 2) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- COMPREHENSIVE AUDIT TRAIL LOGS -->
<div class="glass-card p-4 mt-5">
    <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-list-check text-indigo me-2"></i><?= __('comprehensive_audit_trail_logs_hdr', 'Comprehensive Audit Trail Log (Latest 50 Actions)') ?></h5>
    
    <?php if (count($audit_logs) === 0): ?>
        <div class="text-center py-4 text-secondary small"><?= __('no_audit_logs_found', 'No audit log records found in database.') ?></div>
    <?php else: ?>
        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-swift table-dark table-hover table-borderless align-middle" style="font-size:0.9rem;">
                <thead>
                    <tr style="position: sticky; top: 0; background: #111111; z-index:10;">
                        <th><?= __('performer_col', 'Performer') ?></th>
                        <th><?= __('action_code_col', 'Action Code') ?></th>
                        <th><?= __('details_description_col', 'Details / Description') ?></th>
                        <th><?= __('ip_origin_col', 'IP Origin') ?></th>
                        <th><?= __('timestamp_col', 'Timestamp') ?></th>
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
