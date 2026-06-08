<?php
/**
 * Admin Portal: Manage Agent Wallets (Credit, Debit, Freeze, Audits)
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('admin');

$page_title = "Manage Agent Wallets";
$admin_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error_msg = "Security token validation failed. Please refresh.";
    } else {
        $action = $_POST['action'];
        $wallet_id = intval($_POST['wallet_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0.00);
        $remarks = trim($_POST['remarks'] ?? '');

        try {
            // Fetch wallet and agent info
            $wallet_stmt = $pdo->prepare("
                SELECT w.*, u.username, u.email 
                FROM agent_wallets w
                JOIN users u ON w.agent_id = u.id
                JOIN agent_profiles ap ON u.id = ap.user_id
                WHERE w.id = ? AND ap.admin_id = ?
                LIMIT 1
            ");
            $wallet_stmt->execute([$wallet_id, $admin_id]);
            $wallet = $wallet_stmt->fetch();

            if (!$wallet) {
                $error_msg = "Wallet not found or unauthorized.";
            } else {
                $pdo->beginTransaction();
                $balance_before = floatval($wallet['balance']);

                if ($action === 'credit') {
                    if ($amount <= 0) {
                        throw new Exception("Credit amount must be greater than zero.");
                    }
                    $balance_after = $balance_before + $amount;
                    
                    // Update balance
                    $up_stmt = $pdo->prepare("UPDATE agent_wallets SET balance = ? WHERE id = ?");
                    $up_stmt->execute([$balance_after, $wallet_id]);

                    // Write to ledger
                    $ledger_stmt = $pdo->prepare("
                        INSERT INTO wallet_transactions (
                            wallet_id, transaction_type, amount, balance_before, balance_after, 
                            reference_type, reference_id, remarks, created_by
                        ) VALUES (?, 'admin_credit', ?, ?, ?, 'admin', NULL, ?, ?)
                    ");
                    $ledger_stmt->execute([$wallet_id, $amount, $balance_before, $balance_after, $remarks ?: "Manual Admin Credit", $admin_id]);
                    
                    log_activity($pdo, $admin_id, 'WALLET_ADMIN_CREDIT', "Credited ₹$amount to Agent wallet ID $wallet_id. Remarks: $remarks");
                    $success_msg = "Successfully credited ₹" . number_format($amount, 2) . " to Agent " . htmlspecialchars($wallet['username']) . "'s wallet.";

                } elseif ($action === 'debit') {
                    if ($amount <= 0) {
                        throw new Exception("Debit amount must be greater than zero.");
                    }
                    if ($balance_before < $amount) {
                        throw new Exception("Insufficient balance. Cannot debit ₹$amount from balance of ₹$balance_before.");
                    }
                    $balance_after = $balance_before - $amount;

                    // Update balance
                    $up_stmt = $pdo->prepare("UPDATE agent_wallets SET balance = ? WHERE id = ?");
                    $up_stmt->execute([$balance_after, $wallet_id]);

                    // Write to ledger
                    $ledger_stmt = $pdo->prepare("
                        INSERT INTO wallet_transactions (
                            wallet_id, transaction_type, amount, balance_before, balance_after, 
                            reference_type, reference_id, remarks, created_by
                        ) VALUES (?, 'admin_debit', ?, ?, ?, 'admin', NULL, ?, ?)
                    ");
                    $ledger_stmt->execute([$wallet_id, $amount, $balance_before, $balance_after, $remarks ?: "Manual Admin Debit", $admin_id]);

                    log_activity($pdo, $admin_id, 'WALLET_ADMIN_DEBIT', "Debited ₹$amount from Agent wallet ID $wallet_id. Remarks: $remarks");
                    $success_msg = "Successfully debited ₹" . number_format($amount, 2) . " from Agent " . htmlspecialchars($wallet['username']) . "'s wallet.";

                } elseif ($action === 'freeze') {
                    $up_stmt = $pdo->prepare("UPDATE agent_wallets SET status = 'frozen' WHERE id = ?");
                    $up_stmt->execute([$wallet_id]);
                    
                    log_activity($pdo, $admin_id, 'WALLET_FREEZE', "Froze Agent wallet ID $wallet_id");
                    $success_msg = "Agent wallet frozen successfully.";

                } elseif ($action === 'unfreeze') {
                    $up_stmt = $pdo->prepare("UPDATE agent_wallets SET status = 'active' WHERE id = ?");
                    $up_stmt->execute([$wallet_id]);

                    log_activity($pdo, $admin_id, 'WALLET_UNFREEZE', "Unfroze Agent wallet ID $wallet_id");
                    $success_msg = "Agent wallet unfrozen successfully.";
                }

                $pdo->commit();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = "Transaction failed: " . $e->getMessage();
        }
    }
}

// Fetch all wallets belonging to this Admin's partner agents
$wallets_stmt = $pdo->prepare("
    SELECT w.*, u.username, u.email, ap.agency_name
    FROM agent_wallets w
    JOIN users u ON w.agent_id = u.id
    JOIN agent_profiles ap ON u.id = ap.user_id
    WHERE ap.admin_id = ?
    ORDER BY w.id ASC
");
$wallets_stmt->execute([$admin_id]);
$wallets = $wallets_stmt->fetchAll();

// Compile Widgets/Statistics
$total_balance = 0.00;
$frozen_count = 0;
foreach ($wallets as $w) {
    $total_balance += floatval($w['balance']);
    if ($w['status'] === 'frozen') {
        $frozen_count++;
    }
}

// Fetch recent global transactions (ledger)
$transactions_stmt = $pdo->prepare("
    SELECT wt.*, w.agent_id, u.username as agent_name, ap.agency_name, u2.username as creator_name
    FROM wallet_transactions wt
    JOIN agent_wallets w ON wt.wallet_id = w.id
    JOIN users u ON w.agent_id = u.id
    JOIN agent_profiles ap ON u.id = ap.user_id
    LEFT JOIN users u2 ON wt.created_by = u2.id
    WHERE ap.admin_id = ?
    ORDER BY wt.created_at DESC
    LIMIT 50
");
$transactions_stmt->execute([$admin_id]);
$transactions = $transactions_stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>

<!-- Widgets Row -->
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Total Wallet Liability</span>
                <span class="metric-icon" style="color:#0dcaf0; border-color: rgba(13,202,240,0.2); background: rgba(13,202,240,0.1);"><i class="fa-solid fa-wallet"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1">₹<?= number_format($total_balance, 2) ?></h3>
            <span class="text-secondary small">Total balance held across all partner agent desks</span>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Total Active Wallets</span>
                <span class="metric-icon" style="color:#198754; border-color: rgba(25,135,84,0.2); background: rgba(25,135,84,0.1);"><i class="fa-solid fa-check-double"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1"><?= count($wallets) - $frozen_count ?></h3>
            <span class="text-secondary small">Agents active and booking tickets</span>
        </div>
    </div>

    <div class="col-md-4">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Frozen Wallets</span>
                <span class="metric-icon" style="color:#dc3545; border-color: rgba(220,53,69,0.2); background: rgba(220,53,69,0.1);"><i class="fa-solid fa-snowflake"></i></span>
            </div>
            <h3 class="fw-bold text-danger mb-1"><?= $frozen_count ?></h3>
            <span class="text-secondary small">Wallets frozen by operator controls</span>
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

    <ul class="nav nav-pills mb-4 gap-2" id="adminWalletTab" role="tablist">
        <li class="nav-item">
            <button class="btn btn-secondary-glass active px-4 py-2 border-0 text-white" id="wallets-tab" data-bs-toggle="tab" data-bs-target="#wallets-pane" type="button" role="tab">
                <i class="fa-solid fa-address-book me-2"></i>Partner Wallets
            </button>
        </li>
        <li class="nav-item">
            <button class="btn btn-secondary-glass px-4 py-2 border-0 text-white" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger-pane" type="button" role="tab">
                <i class="fa-solid fa-clock-rotate-left me-2"></i>Global Ledger Log
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminWalletTabContent">
        <!-- Partner Wallets Pane -->
        <div class="tab-pane fade show active" id="wallets-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-swift table-dark table-hover align-middle datatable-swift">
                    <thead>
                        <tr>
                            <th>Agency Name</th>
                            <th>Agent Username / Email</th>
                            <th>Current Balance</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wallets as $w): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-white"><?= htmlspecialchars($w['agency_name'] ?: 'N/A') ?></div>
                                    <span class="text-secondary small">Wallet ID: #<?= $w['id'] ?></span>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($w['username']) ?></div>
                                    <span class="text-secondary small" style="font-size:0.75rem;"><?= htmlspecialchars($w['email']) ?></span>
                                </td>
                                <td class="fw-bold fs-5 text-indigo">₹<?= number_format($w['balance'], 2) ?></td>
                                <td>
                                    <?php if ($w['status'] === 'frozen'): ?>
                                        <span class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25 px-2 py-1">Frozen</span>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2 py-1">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-success btn-sm rounded-2" data-bs-toggle="modal" data-bs-target="#creditModal<?= $w['id'] ?>">Credit</button>
                                        <button type="button" class="btn btn-warning-glass btn-sm rounded-2" data-bs-toggle="modal" data-bs-target="#debitModal<?= $w['id'] ?>">Debit</button>
                                        
                                        <?php if ($w['status'] === 'frozen'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to unfreeze this wallet?');">
                                                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                                <input type="hidden" name="wallet_id" value="<?= $w['id'] ?>">
                                                <input type="hidden" name="action" value="unfreeze">
                                                <button type="submit" class="btn btn-outline-success btn-sm rounded-2">Unfreeze</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to freeze this wallet?');">
                                                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                                <input type="hidden" name="wallet_id" value="<?= $w['id'] ?>">
                                                <input type="hidden" name="action" value="freeze">
                                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-2">Freeze</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Credit Modal -->
                                    <div class="modal fade text-start" id="creditModal<?= $w['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content glass-card text-white border-secondary border-opacity-20 p-3" style="background:#111111; border-radius: 20px;">
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                                    <input type="hidden" name="wallet_id" value="<?= $w['id'] ?>">
                                                    <input type="hidden" name="action" value="credit">
                                                    <div class="modal-header border-0 pb-0">
                                                        <h5 class="modal-title fw-bold">Manual Credit: <?= htmlspecialchars($w['agency_name']) ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body py-4">
                                                        <div class="mb-3">
                                                            <label class="form-label text-secondary small fw-semibold">Amount to Add (₹)</label>
                                                            <input type="number" name="amount" class="form-control form-control-swift" min="1" step="0.01" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label text-secondary small fw-semibold">Remarks / Reason</label>
                                                            <textarea name="remarks" class="form-control form-control-swift" rows="2" placeholder="e.g. Received offline payment, promo credit..." required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                                                        <button type="button" class="btn btn-secondary-glass rounded-3" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success px-4 rounded-3">Confirm Credit</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Debit Modal -->
                                    <div class="modal fade text-start" id="debitModal<?= $w['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content glass-card text-white border-secondary border-opacity-20 p-3" style="background:#111111; border-radius: 20px;">
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                                    <input type="hidden" name="wallet_id" value="<?= $w['id'] ?>">
                                                    <input type="hidden" name="action" value="debit">
                                                    <div class="modal-header border-0 pb-0">
                                                        <h5 class="modal-title fw-bold">Manual Debit: <?= htmlspecialchars($w['agency_name']) ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body py-4">
                                                        <div class="mb-3">
                                                            <label class="form-label text-secondary small fw-semibold">Amount to Deduct (₹)</label>
                                                            <input type="number" name="amount" class="form-control form-control-swift" min="1" max="<?= htmlspecialchars($w['balance']) ?>" step="0.01" required>
                                                            <div class="form-text text-secondary" style="font-size:0.75rem;">Max limit: ₹<?= number_format($w['balance'], 2) ?></div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label text-secondary small fw-semibold">Remarks / Reason</label>
                                                            <textarea name="remarks" class="form-control form-control-swift" rows="2" placeholder="e.g. Correction debit, charge adjustment..." required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                                                        <button type="button" class="btn btn-secondary-glass rounded-3" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-warning px-4 rounded-3 text-dark">Confirm Debit</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Global Ledger Log Pane -->
        <div class="tab-pane fade" id="ledger-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-swift table-dark table-hover align-middle datatable-swift">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Agent / Desk</th>
                            <th>Ledger Type</th>
                            <th>Amount</th>
                            <th>Balance Log</th>
                            <th>Remarks</th>
                            <th>Authorized By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="font-monospace small"><?= date('d-M-Y H:i:s', strtotime($tx['created_at'])) ?></td>
                                <td>
                                    <div class="fw-semibold text-white"><?= htmlspecialchars($tx['agency_name']) ?></div>
                                    <span class="text-secondary small">ID: #<?= $tx['agent_id'] ?></span>
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
    $('.datatable-swift').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search records..."
        }
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
