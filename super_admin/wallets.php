<?php
/**
 * Super Admin Portal: Global Wallets Manager, Audits & Freeze Controls
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('super_admin');

$page_title = "Global Agent Wallets & Audits";
$super_admin_id = $_SESSION['user_id'];
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Handle Freeze / Unfreeze actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $_SESSION['error_msg'] = "Security token validation failed. Please refresh.";
    } else {
        $action = $_POST['action'];
        $wallet_id = intval($_POST['wallet_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            if ($action === 'freeze') {
                $up_stmt = $pdo->prepare("UPDATE agent_wallets SET status = 'frozen' WHERE id = ?");
                $up_stmt->execute([$wallet_id]);
                
                log_activity($pdo, $super_admin_id, 'SUPER_ADMIN_WALLET_FREEZE', "Super Admin froze wallet ID $wallet_id");
                $_SESSION['success_msg'] = "Successfully froze agent wallet.";
            } elseif ($action === 'unfreeze') {
                $up_stmt = $pdo->prepare("UPDATE agent_wallets SET status = 'active' WHERE id = ?");
                $up_stmt->execute([$wallet_id]);
                
                log_activity($pdo, $super_admin_id, 'SUPER_ADMIN_WALLET_UNFREEZE', "Super Admin unfroze wallet ID $wallet_id");
                $_SESSION['success_msg'] = "Successfully unfroze agent wallet.";
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error_msg'] = "Failed to update wallet: " . $e->getMessage();
        }
    }

    if (!headers_sent()) {
        header("Location: " . $_SERVER['PHP_SELF']);
    } else {
        echo "<script>window.location.replace('" . $_SERVER['PHP_SELF'] . "');</script>";
    }
    exit();
}

// Fetch all wallets in the system
$wallets_stmt = $pdo->query("
    SELECT w.*, u.username, u.email, ap.agency_name, op.username as operator_name
    FROM agent_wallets w
    JOIN users u ON w.agent_id = u.id
    JOIN agent_profiles ap ON u.id = ap.user_id
    LEFT JOIN users op ON ap.admin_id = op.id
    ORDER BY w.balance DESC
");
$wallets = $wallets_stmt->fetchAll();

// Statistics
$total_balance = 0.00;
$frozen_count = 0;
foreach ($wallets as $w) {
    $total_balance += floatval($w['balance']);
    if ($w['status'] === 'frozen') {
        $frozen_count++;
    }
}

// Fetch total recharges sum
$recharges_sum = floatval($pdo->query("SELECT SUM(amount) FROM wallet_recharges WHERE status = 'success'")->fetchColumn() ?: 0.00);

// Fetch total refunds sum
$refunds_sum = floatval($pdo->query("SELECT SUM(amount) FROM wallet_transactions WHERE transaction_type = 'refund'")->fetchColumn() ?: 0.00);

// Fetch global transaction ledger
$ledger_stmt = $pdo->query("
    SELECT wt.*, w.agent_id, u.username as agent_name, ap.agency_name, u2.username as creator_name
    FROM wallet_transactions wt
    JOIN agent_wallets w ON wt.wallet_id = w.id
    JOIN users u ON w.agent_id = u.id
    JOIN agent_profiles ap ON u.id = ap.user_id
    LEFT JOIN users u2 ON wt.created_by = u2.id
    ORDER BY wt.created_at DESC
    LIMIT 100
");
$ledger = $ledger_stmt->fetchAll();

// Fetch recharge transactions report
$recharges_report = $pdo->query("
    SELECT wr.*, u.username as agent_name, ap.agency_name
    FROM wallet_recharges wr
    JOIN agent_wallets w ON wr.wallet_id = w.id
    JOIN users u ON w.agent_id = u.id
    JOIN agent_profiles ap ON u.id = ap.user_id
    ORDER BY wr.created_at DESC
    LIMIT 100
")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<!-- Widgets Row -->
<div class="row g-4 mb-5">
    <div class="col-md-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Total System Liability</span>
                <span class="metric-icon" style="color:#0dcaf0; border-color: rgba(13,202,240,0.2); background: rgba(13,202,240,0.1);"><i class="fa-solid fa-wallet"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1">₹<?= number_format($total_balance, 2) ?></h3>
            <span class="text-secondary small">Total balance across all agent wallets</span>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Total Recharges</span>
                <span class="metric-icon" style="color:#198754; border-color: rgba(25,135,84,0.2); background: rgba(25,135,84,0.1);"><i class="fa-solid fa-arrow-up-right-dots"></i></span>
            </div>
            <h3 class="fw-bold text-success mb-1">₹<?= number_format($recharges_sum, 2) ?></h3>
            <span class="text-secondary small">Cumulative Razorpay deposits</span>
        </div>
    </div>

    <div class="col-md-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Total Refunds Credited</span>
                <span class="metric-icon" style="color:#0d6efd; border-color: rgba(13,110,253,0.2); background: rgba(13,110,253,0.1);"><i class="fa-solid fa-rotate-left"></i></span>
            </div>
            <h3 class="fw-bold text-info mb-1">₹<?= number_format($refunds_sum, 2) ?></h3>
            <span class="text-secondary small">Credits via booking cancellations</span>
        </div>
    </div>

    <div class="col-md-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Frozen Wallets</span>
                <span class="metric-icon" style="color:#dc3545; border-color: rgba(220,53,69,0.2); background: rgba(220,53,69,0.1);"><i class="fa-solid fa-snowflake"></i></span>
            </div>
            <h3 class="fw-bold text-danger mb-1"><?= $frozen_count ?></h3>
            <span class="text-secondary small">Locked agent desk wallets</span>
        </div>
    </div>
</div>

<div class="glass-card p-4 mb-5" style="border-radius: 20px;">
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-3 mb-4"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-3 mb-4"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-4 gap-2" id="superWalletTab" role="tablist">
        <li class="nav-item">
            <button class="btn btn-secondary-glass active px-4 py-2 border-0 text-white" id="all-wallets-tab" data-bs-toggle="tab" data-bs-target="#all-wallets-pane" type="button" role="tab">
                <i class="fa-solid fa-table-list me-2"></i>Global Wallets
            </button>
        </li>
        <li class="nav-item">
            <button class="btn btn-secondary-glass px-4 py-2 border-0 text-white" id="all-recharges-tab" data-bs-toggle="tab" data-bs-target="#all-recharges-pane" type="button" role="tab">
                <i class="fa-solid fa-arrow-up-wide-short me-2"></i>Recharges Report
            </button>
        </li>
        <li class="nav-item">
            <button class="btn btn-secondary-glass px-4 py-2 border-0 text-white" id="all-ledger-tab" data-bs-toggle="tab" data-bs-target="#all-ledger-pane" type="button" role="tab">
                <i class="fa-solid fa-clock-rotate-left me-2"></i>Audit Log Ledger
            </button>
        </li>
    </ul>

    <div class="tab-content" id="superWalletTabContent">
        <!-- Global Wallets Pane -->
        <div class="tab-pane fade show active" id="all-wallets-pane" role="tabpanel">
            <div class="table-responsive">
                <table id="superWalletsTable" class="table table-swift table-dark table-hover align-middle text-nowrap" style="width: 100%; min-width: 800px;">
                    <thead>
                        <tr>
                            <th>Wallet ID</th>
                            <th>Agency Name</th>
                            <th>Agent Details</th>
                            <th>Linked Operator</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th class="text-end">Controls</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wallets as $w): ?>
                            <tr>
                                <td class="font-monospace">#<?= $w['id'] ?></td>
                                <td><div class="fw-bold text-white"><?= htmlspecialchars($w['agency_name'] ?: 'N/A') ?></div></td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($w['username']) ?></div>
                                    <span class="text-secondary small" style="font-size: 0.75rem;"><?= htmlspecialchars($w['email']) ?></span>
                                </td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($w['operator_name'] ?: 'N/A') ?></span></td>
                                <td class="fw-bold fs-5 text-indigo">₹<?= number_format($w['balance'], 2) ?></td>
                                <td>
                                    <?php if ($w['status'] === 'frozen'): ?>
                                        <span class="badge px-3 py-2" style="background: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.25); font-weight: 600; font-size: 0.8rem; border-radius: 30px;">Frozen</span>
                                    <?php else: ?>
                                        <span class="badge px-3 py-2" style="background: rgba(25, 135, 84, 0.15); color: #198754; border: 1px solid rgba(25, 135, 84, 0.25); font-weight: 600; font-size: 0.8rem; border-radius: 30px;">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($w['status'] === 'frozen'): ?>
                                        <form method="POST" onsubmit="return confirm('Unfreeze this wallet?');" style="display:inline-block;">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="wallet_id" value="<?= $w['id'] ?>">
                                            <input type="hidden" name="action" value="unfreeze">
                                            <button type="submit" class="btn btn-outline-success btn-sm rounded-2">Unfreeze</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" onsubmit="return confirm('Freeze this wallet?');" style="display:inline-block;">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="wallet_id" value="<?= $w['id'] ?>">
                                            <input type="hidden" name="action" value="freeze">
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-2">Freeze</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recharges Report Pane -->
        <div class="tab-pane fade" id="all-recharges-pane" role="tabpanel">
            <div class="table-responsive">
                <table id="superRechargesTable" class="table table-swift table-dark table-hover align-middle text-nowrap" style="width: 100%; min-width: 900px;">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Agency Name</th>
                            <th>Amount</th>
                            <th>Razorpay Payment ID</th>
                            <th>Razorpay Order ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recharges_report as $rec): ?>
                            <tr>
                                <td class="font-monospace small"><?= date('d-M-Y H:i:s', strtotime($rec['created_at'])) ?></td>
                                <td>
                                    <div class="fw-bold text-white"><?= htmlspecialchars($rec['agency_name']) ?></div>
                                    <span class="text-secondary small"><?= htmlspecialchars($rec['agent_name']) ?></span>
                                </td>
                                <td class="fw-bold text-success">₹<?= number_format($rec['amount'], 2) ?></td>
                                <td class="font-monospace small text-secondary"><?= htmlspecialchars($rec['razorpay_payment_id']) ?></td>
                                <td class="font-monospace small text-secondary"><?= htmlspecialchars($rec['razorpay_order_id']) ?></td>
                                <td>
                                    <?php if ($rec['status'] === 'success'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20">Success</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-20"><?= htmlspecialchars($rec['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Audit Log Ledger Pane -->
        <div class="tab-pane fade" id="all-ledger-pane" role="tabpanel">
            <div class="table-responsive">
                <table id="superLedgerTable" class="table table-swift table-dark table-hover align-middle text-nowrap" style="width: 100%; min-width: 1000px;">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Agent / Agency</th>
                            <th>Transaction Type</th>
                            <th>Amount</th>
                            <th>Balance Log</th>
                            <th>Remarks</th>
                            <th>Action Performed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ledger as $tx): ?>
                            <tr>
                                <td class="font-monospace small"><?= date('d-M-Y H:i:s', strtotime($tx['created_at'])) ?></td>
                                <td>
                                    <div class="fw-semibold text-white"><?= htmlspecialchars($tx['agency_name']) ?></div>
                                    <span class="text-secondary small"><?= htmlspecialchars($tx['agent_name']) ?> (ID: #<?= $tx['agent_id'] ?>)</span>
                                </td>
                                <td>
                                    <?php
                                    $badge = 'bg-secondary';
                                    if ($tx['transaction_type'] === 'recharge' || $tx['transaction_type'] === 'refund' || $tx['transaction_type'] === 'admin_credit') {
                                        $badge = 'bg-success bg-opacity-10 text-success border border-success border-opacity-20';
                                    } else {
                                        $badge = 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-20';
                                    }
                                    ?>
                                    <span class="badge <?= $badge ?> text-capitalize"><?= str_replace('_', ' ', $tx['transaction_type']) ?></span>
                                </td>
                                <td class="fw-bold <?= ($tx['transaction_type'] === 'recharge' || $tx['transaction_type'] === 'refund' || $tx['transaction_type'] === 'admin_credit') ? 'text-success' : 'text-danger' ?>">
                                    <?= ($tx['transaction_type'] === 'recharge' || $tx['transaction_type'] === 'refund' || $tx['transaction_type'] === 'admin_credit') ? '+' : '-' ?>₹<?= number_format($tx['amount'], 2) ?>
                                </td>
                                <td class="small font-monospace">
                                    <span class="text-secondary">₹<?= number_format($tx['balance_before'], 2) ?></span>
                                    <i class="fa-solid fa-arrow-right mx-1 text-secondary opacity-50"></i>
                                    <span class="text-white fw-bold">₹<?= number_format($tx['balance_after'], 2) ?></span>
                                </td>
                                <td class="small text-secondary"><?= htmlspecialchars($tx['remarks']) ?></td>
                                <td class="small text-white"><?= htmlspecialchars($tx['creator_name'] ?: 'System') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#superWalletsTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 10,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search global records..."
        }
    });
    $('#superRechargesTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search recharges..."
        }
    });
    $('#superLedgerTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search global records..."
        }
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
