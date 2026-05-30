<?php
/**
 * Super Admin Weekly Settlement Processor
 */
require_once __DIR__ . '/header.php';

$error = '';
$success = '';

// Handle actions (Generate, Mark Paid)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token validation failed.";
    } else {
        $action = $_POST['action'] ?? '';

        // GENERATE PENDING WEEKLY SETTLEMENTS
        if ($action === 'generate') {
            try {
                // Find all Bus Operators (Admins)
                $admins_stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'admin' AND status = 'approved'");
                $approved_admins = $admins_stmt->fetchAll();

                $generated_count = 0;
                $today_date = date('Y-m-d');

                foreach ($approved_admins as $ad) {
                    $admin_id = $ad['id'];

                    // Find the date range of uncaptured bookings for this Admin (operator)
                    // We look at bookings that are 'paid' but not associated with any week cycle yet.
                    $range_stmt = $pdo->prepare("
                        SELECT 
                            MIN(DATE(b.created_at)) AS start_date,
                            MAX(DATE(b.created_at)) AS end_date,
                            SUM(b.total_amount) AS total_sales,
                            SUM(b.admin_commission) AS total_comm
                        FROM bookings b
                        JOIN trips t ON b.trip_id = t.id
                        JOIN buses bs ON t.bus_id = bs.id
                        WHERE bs.admin_id = :admin_id
                          AND b.payment_status = 'paid'
                          AND NOT EXISTS (
                              SELECT 1 FROM weekly_settlements ws 
                              WHERE ws.agent_id = :admin_id 
                                AND DATE(b.created_at) BETWEEN ws.week_start AND ws.week_end
                          )
                    ");
                    $range_stmt->execute([':admin_id' => $admin_id]);
                    $uncaptured = $range_stmt->fetch();

                    if ($uncaptured && !empty($uncaptured['start_date']) && floatval($uncaptured['total_sales']) > 0) {
                        // Insert new settlement record
                        $ins = $pdo->prepare("
                            INSERT INTO weekly_settlements (agent_id, week_start, week_end, total_sales, commission_payable, status)
                            VALUES (?, ?, ?, ?, ?, 'pending')
                        ");
                        $ins->execute([
                            $admin_id, 
                            $uncaptured['start_date'], 
                            $uncaptured['end_date'], 
                            $uncaptured['total_sales'], 
                            $uncaptured['total_comm']
                        ]);
                        $generated_count++;
                    }
                }

                if ($generated_count > 0) {
                    $success = "Weekly settlements generated successfully for $generated_count bus operators!";
                    log_activity($pdo, $_SESSION['user_id'], 'SETTLEMENT_GENERATE', "Generated settlements for $generated_count operators.");
                } else {
                    $error = "All operator bookings are already accounted for in existing settlement cycles.";
                }

            } catch (Exception $e) {
                $error = "Failed to run settlement engine: " . $e->getMessage();
            }
        }

        // MARK SETTLEMENT AS PAID / COMMISSION RECEIVED
        elseif ($action === 'mark_paid') {
            $settlement_id = intval($_POST['settlement_id'] ?? 0);
            $admin_user_id = $_SESSION['user_id'];

            if ($settlement_id > 0) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE weekly_settlements 
                        SET status = 'paid', marked_paid_at = NOW(), marked_paid_by = ? 
                        WHERE id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$admin_user_id, $settlement_id]);

                    if ($stmt->rowCount() > 0) {
                        $success = "Settlement marked as PAID! Commission marked as received.";
                        log_activity($pdo, $admin_user_id, 'SETTLEMENT_PAY_CONFIRM', "Confirmed commission payment received for Settlement ID: $settlement_id");
                    } else {
                        $error = "Settlement already processed or invalid ID.";
                    }
                } catch (PDOException $e) {
                    $error = "Database write error: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Settlements with Pagination
try {
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM weekly_settlements");
    $total_records = intval($count_stmt->fetchColumn());

    $limit = 10;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;
    $total_pages = ceil($total_records / $limit);

    $stmt = $pdo->prepare("
        SELECT 
            ws.*,
            op.username AS agency_name
        FROM weekly_settlements ws
        JOIN users op ON ws.agent_id = op.id
        ORDER BY ws.status ASC, ws.week_end DESC
        LIMIT " . intval($limit) . " OFFSET " . intval($offset) . "
    ");
    $stmt->execute();
    $settlements = $stmt->fetchAll();
} catch (PDOException $e) {
    $settlements = [];
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

<!-- Actions Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="text-white fw-bold mb-1">Weekly Settlements Desk</h4>
        <span class="text-secondary small">Tally weekly travel agency gross seat sales and process 2% admin fee collections</span>
    </div>
    
    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <input type="hidden" name="action" value="generate">
        <button type="submit" class="btn btn-primary-gradient"><i class="fa-solid fa-calculator me-2"></i>Execute Settlement Engine</button>
    </form>
</div>

<!-- Settlements Grid Table -->
<div class="glass-card p-4">
    <?php if (count($settlements) === 0): ?>
        <div class="text-center py-5 text-secondary small">
            <i class="fa-solid fa-wallet mb-3 d-block" style="font-size: 3rem; color: #475569;"></i>
            No weekly settlement logs found. Tap the button above to generate settlements for uncaptured ticket sales.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle datatable-swift">
                <thead>
                    <tr>
                        <th>Cycle Period</th>
                        <th>Travel Operator</th>
                        <th>Total Gross Sales</th>
                        <th>Commission Receivable (2%)</th>
                        <th>Agent Share Kept</th>
                        <th>Settlement Status</th>
                        <th>Processed Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settlements as $s): 
                        $agent_net = floatval($s['total_sales']) - floatval($s['commission_payable']);
                    ?>
                        <tr>
                            <td>
                                <span class="fw-semibold text-white d-block">Weekly Cycle</span>
                                <span class="text-secondary small font-monospace"><?= date('d M Y', strtotime($s['week_start'])) ?> to <?= date('d M Y', strtotime($s['week_end'])) ?></span>
                            </td>
                            <td><span class="fw-semibold text-white"><?= htmlspecialchars($s['agency_name']) ?></span></td>
                            <td>₹<?= number_format($s['total_sales'], 2) ?></td>
                            <td class="text-warning fw-bold">₹<?= number_format($s['commission_payable'], 2) ?></td>
                            <td class="text-success fw-bold">₹<?= number_format($agent_net, 2) ?></td>
                            <td>
                                <?php if ($s['status'] === 'paid'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">PAID / RECEIVED</span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">PENDING CLEARANCE</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-secondary small">
                                <?= !empty($s['marked_paid_at']) ? date('d M Y H:i', strtotime($s['marked_paid_at'])) : '--' ?>
                            </td>
                            <td class="text-end">
                                <?php if ($s['status'] === 'pending'): ?>
                                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                        <input type="hidden" name="action" value="mark_paid">
                                        <input type="hidden" name="settlement_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-success py-1 px-3 small font-monospace" style="font-size:0.75rem;"><i class="fa-solid fa-circle-dollar-to-slot me-1"></i>Confirm Paid</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-secondary small"><i class="fa-solid fa-circle-check text-success me-1"></i>Settled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-secondary small">
                    Showing <?= $offset + 1 ?> to <?= min($total_records, $offset + $limit) ?> of <?= $total_records ?> entries
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
