<?php
/**
 * Admin Panel: Activity & Audit Log Viewer
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('admin');

$page_title = "Activity & Audit Logs";

// Filters from request
$filter_agent = intval($_GET['agent_id'] ?? 0);
$filter_action = trim($_GET['action_type'] ?? '');
$filter_bus = trim($_GET['bus_query'] ?? '');
$filter_route = trim($_GET['route_query'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

// Fetch all active/suspended agents for dropdown filter
try {
    $agents_stmt = $pdo->prepare("SELECT id, username FROM users WHERE role = 'agent' ORDER BY username ASC");
    $agents_stmt->execute();
    $agents = $agents_stmt->fetchAll();

    // Fetch unique action types for filter
    $actions_stmt = $pdo->prepare("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type ASC");
    $actions_stmt->execute();
    $action_types = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Build log query
    $query = "
        SELECT l.*, u.username, u.role AS current_user_role
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE 1=1
    ";
    $params = [];

    if ($filter_agent > 0) {
        $query .= " AND l.user_id = ?";
        $params[] = $filter_agent;
    }
    if (!empty($filter_action)) {
        $query .= " AND l.action_type = ?";
        $params[] = $filter_action;
    }
    if (!empty($filter_bus)) {
        $query .= " AND l.details LIKE ?";
        $params[] = '%' . $filter_bus . '%';
    }
    if (!empty($filter_route)) {
        $query .= " AND l.details LIKE ?";
        $params[] = '%' . $filter_route . '%';
    }
    if (!empty($start_date)) {
        $query .= " AND DATE(l.created_at) >= ?";
        $params[] = $start_date;
    }
    if (!empty($end_date)) {
        $query .= " AND DATE(l.created_at) <= ?";
        $params[] = $end_date;
    }

    $query .= " ORDER BY l.created_at DESC LIMIT 200"; // Limit to latest 200 for speed

    $log_stmt = $pdo->prepare($query);
    $log_stmt->execute($params);
    $logs = $log_stmt->fetchAll();

} catch (PDOException $e) {
    die("Error retrieving audit logs: " . $e->getMessage());
}

require_once __DIR__ . '/header.php';
?>

<div class="glass-card p-4 mb-4" style="border-radius: 20px;">
    <h5 class="text-white mb-3 fw-bold"><i class="fa-solid fa-filter text-indigo me-2"></i>Filter Audit Logs</h5>
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label class="form-label text-secondary small fw-semibold">Agent / User</label>
            <select name="agent_id" class="form-select form-control-swift">
                <option value="">All Users / Agents</option>
                <?php foreach ($agents as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= ($filter_agent === intval($a['id'])) ? 'selected' : '' ?>><?= htmlspecialchars($a['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary small fw-semibold">Action Type</label>
            <select name="action_type" class="form-select form-control-swift">
                <option value="">All Actions</option>
                <?php foreach ($action_types as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= ($filter_action === $act) ? 'selected' : '' ?>><?= htmlspecialchars($act) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary small fw-semibold">Bus Search</label>
            <input type="text" name="bus_query" class="form-control form-control-swift" placeholder="e.g. Sleeper Coach" value="<?= htmlspecialchars($filter_bus) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary small fw-semibold">Route Search</label>
            <input type="text" name="route_query" class="form-control form-control-swift" placeholder="e.g. Ranchi" value="<?= htmlspecialchars($filter_route) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary small fw-semibold">Start Date</label>
            <input type="date" name="start_date" class="form-control form-control-swift" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary small fw-semibold">End Date</label>
            <input type="date" name="end_date" class="form-control form-control-swift" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="col-md-6 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary-gradient px-4 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Logs</button>
            <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="btn btn-secondary-glass px-4 py-2">Reset</a>
        </div>
    </form>
</div>

<div class="glass-card p-4" style="border-radius: 20px;">
    <h5 class="text-white mb-4 fw-bold"><i class="fa-solid fa-clock-rotate-left text-indigo me-2"></i>System Trail Logs</h5>
    
    <?php if (empty($logs)): ?>
        <div class="text-center py-5">
            <span class="text-secondary" style="font-size: 3rem;"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <h5 class="text-white mt-3 fw-bold">No Audit Records Found</h5>
            <p class="text-secondary small">Try adjusting your filters.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover align-middle small" style="background: transparent;">
                <thead>
                    <tr class="border-bottom border-secondary border-opacity-25 text-secondary">
                        <th>User (Role)</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Prev Value</th>
                        <th>New Value</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr class="border-bottom border-secondary border-opacity-15">
                            <td>
                                <span class="fw-semibold text-white"><?= htmlspecialchars($log['username'] ?? 'System / Deleted') ?></span>
                                <div class="text-secondary" style="font-size:0.75rem;">(<?= htmlspecialchars($log['user_role'] ?: ($log['current_user_role'] ?: 'system')) ?>)</div>
                            </td>
                            <td>
                                <span class="badge bg-indigo text-uppercase" style="font-size: 0.7rem; background:#5252ff;"><?= htmlspecialchars($log['action_type']) ?></span>
                            </td>
                            <td>
                                <span class="text-white-50"><?= htmlspecialchars($log['details']) ?></span>
                            </td>
                            <td>
                                <span class="text-secondary font-monospace"><?= htmlspecialchars($log['previous_value'] ?? '-') ?></span>
                            </td>
                            <td>
                                <span class="text-success font-monospace"><?= htmlspecialchars($log['new_value'] ?? '-') ?></span>
                            </td>
                            <td>
                                <span class="text-secondary font-monospace"><?= htmlspecialchars($log['ip_address']) ?></span>
                            </td>
                            <td>
                                <span class="text-white-50"><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></span>
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
