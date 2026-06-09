<?php
/**
 * Admin Payments Dashboard
 * Features analytics, transactional searches, refunds execution, and settlements log.
 */
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../config/PaymentGateway.php';

$gateway = new PaymentGateway($pdo);
$admin_id = $_SESSION['user_id'];

$success_msg = '';
$error_msg = '';

// Handle Refund POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'refund') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error_msg = "Security validation failed.";
    } else {
        $payment_ref = trim($_POST['payment_reference'] ?? '');
        $refund_amount = floatval($_POST['refund_amount'] ?? 0.00);
        $reason = trim($_POST['refund_reason'] ?? 'Customer Refund Request');

        if (empty($payment_ref) || $refund_amount <= 0) {
            $error_msg = "Invalid parameters for refund processing.";
        } else {
            $result = $gateway->processRefund($payment_ref, $refund_amount, $reason);
            if ($result['success']) {
                $success_msg = "Refund of ₹" . number_format($refund_amount, 2) . " processed successfully! Reference ID: " . $result['refund_reference'];
                log_activity($pdo, $admin_id, 'PAYMENT_REFUND_SUCCESS', "Processed refund for $payment_ref. Amount: ₹$refund_amount. Reason: $reason");
            } else {
                $error_msg = "Refund failed: " . $result['message'];
                log_activity($pdo, $admin_id, 'PAYMENT_REFUND_FAILED', "Failed refund for $payment_ref. Error: " . $result['message']);
            }
        }
    }
}

// Handle Log Settlement POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'log_settlement') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error_msg = "Security validation failed.";
    } else {
        $payment_id = intval($_POST['payment_id'] ?? 0);
        $settlement_ref = trim($_POST['settlement_reference'] ?? '');
        $settle_amount = floatval($_POST['settle_amount'] ?? 0.00);
        $settle_date = trim($_POST['settlement_date'] ?? date('Y-m-d'));

        if ($payment_id <= 0 || $settle_amount <= 0) {
            $error_msg = "Invalid parameters for settlement logging.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO settlement_logs (payment_id, settlement_reference, amount, settlement_date, raw_log)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$payment_id, $settlement_ref, $settle_amount, $settle_date, 'Logged manually by Operator Admin']);
                $success_msg = "Settlement reference $settlement_ref logged successfully.";
            } catch (Exception $e) {
                $error_msg = "Failed to log settlement: " . $e->getMessage();
            }
        }
    }
}

// Compute Metrics
try {
    // Volume & counts
    $stmt = $pdo->query("
        SELECT 
            COUNT(id) AS total_txns,
            COALESCE(SUM(amount), 0.00) AS total_volume,
            COUNT(CASE WHEN status = 'success' THEN 1 END) AS success_count,
            COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0.00 END), 0.00) AS success_volume,
            COALESCE(SUM(refunded_amount), 0.00) AS total_refunded,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) AS failed_count,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled_count
        FROM payments
    ");
    $metrics = $stmt->fetch();

    $total_txns = intval($metrics['total_txns']);
    $success_count = intval($metrics['success_count']);
    $success_rate = $total_txns > 0 ? round(($success_count / $total_txns) * 100, 1) : 0.0;

    // Load recent payments
    $stmt = $pdo->query("
        SELECT 
            p.*, 
            b.customer_name AS booking_cust, 
            b.customer_email AS booking_email,
            b.customer_phone AS booking_phone,
            w.amount AS rech_amount,
            u.username AS rech_agent
        FROM payments p
        LEFT JOIN bookings b ON p.booking_reference = b.booking_reference
        LEFT JOIN wallet_recharges w ON p.wallet_recharge_id = w.id
        LEFT JOIN agent_wallets aw ON w.wallet_id = aw.id
        LEFT JOIN users u ON aw.agent_id = u.id
        ORDER BY p.created_at DESC
    ");
    $payments_list = $stmt->fetchAll();

    // Fetch settlements list
    $settlements_stmt = $pdo->query("
        SELECT sl.*, p.payment_reference, p.booking_reference
        FROM settlement_logs sl
        JOIN payments p ON sl.payment_id = p.id
        ORDER BY sl.settlement_date DESC
    ");
    $settlement_records = $settlements_stmt->fetchAll();

} catch (PDOException $e) {
    die("Metrics computation failed: " . $e->getMessage());
}

$page_title = "Payments & Settlement Dashboard";
?>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-3 alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="filter: var(--btn-close-filter);"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="filter: var(--btn-close-filter);"></button>
    </div>
<?php endif; ?>

<!-- Analytics Overview Row -->
<div class="row g-4 mb-5">
    <!-- Success Volume -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Processed Volume</span>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10"><i class="fa-solid fa-indian-rupee-sign"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1">₹<?= number_format($metrics['success_volume'], 2) ?></h3>
            <span class="text-secondary small">Success Flow (<?= $success_count ?> txns)</span>
        </div>
    </div>

    <!-- Refund Volume -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Total Refunded</span>
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10"><i class="fa-solid fa-undo"></i></span>
            </div>
            <h3 class="fw-bold text-danger mb-1">₹<?= number_format($metrics['total_refunded'], 2) ?></h3>
            <span class="text-secondary small">Refund processing log</span>
        </div>
    </div>

    <!-- Success Rate -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Conversion Rate</span>
                <span class="badge bg-indigo bg-opacity-10 text-indigo border border-indigo border-opacity-10"><i class="fa-solid fa-percent"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1" style="color:#818cf8 !important;"><?= $success_rate ?>%</h3>
            <span class="text-secondary small">Total transactions: <?= $total_txns ?></span>
        </div>
    </div>

    <!-- Failed & Cancelled -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Failed / Cancelled</span>
                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-10"><i class="fa-solid fa-ban"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1"><?= $metrics['failed_count'] ?> / <?= $metrics['cancelled_count'] ?></h3>
            <span class="text-secondary small">Discarded checkout sessions</span>
        </div>
    </div>
</div>

<div class="glass-card p-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h5 class="fw-bold text-white mb-0"><i class="fa-solid fa-list text-indigo me-2"></i>Transactions Ledger (All Gateway Invoices)</h5>
    </div>

    <div class="table-responsive">
        <table id="paymentsTable" class="table table-swift table-dark table-hover table-borderless align-middle" style="font-size:0.9rem;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Payment Ref ID</th>
                    <th>Type</th>
                    <th>Payer Details</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Gateway Mode</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments_list as $p): ?>
                    <?php
                        $type = $p['booking_reference'] ? 'Booking' : ($p['wallet_recharge_id'] ? 'Wallet Recharge' : 'Generic');
                        $payer = 'System / Internal';
                        if ($p['booking_reference']) {
                            $payer = htmlspecialchars($p['booking_cust'] . " (" . $p['booking_phone'] . ")");
                        } elseif ($p['wallet_recharge_id']) {
                            $payer = htmlspecialchars("Agent: " . $p['rech_agent']);
                        }

                        $status_badge = 'bg-secondary';
                        if ($p['status'] === 'success') $status_badge = 'bg-success';
                        elseif ($p['status'] === 'failed') $status_badge = 'bg-danger';
                        elseif ($p['status'] === 'refunded') $status_badge = 'bg-info text-dark';
                        elseif ($p['status'] === 'cancelled') $status_badge = 'bg-dark border border-secondary';
                    ?>
                    <tr class="border-bottom border-secondary border-opacity-10">
                        <td class="small font-monospace"><?= date('d M Y H:i', strtotime($p['created_at'])) ?></td>
                        <td>
                            <span class="fw-bold text-white font-monospace"><?= htmlspecialchars($p['payment_reference']) ?></span>
                            <?php if ($p['booking_reference']): ?>
                                <span class="d-block small text-secondary">Book Ref: <a href="<?= BASE_URL ?>/ticket.php?ref=<?= urlencode($p['booking_reference']) ?>" class="font-monospace text-indigo" target="_blank"><?= htmlspecialchars($p['booking_reference']) ?></a></span>
                            <?php elseif ($p['wallet_recharge_id']): ?>
                                <span class="d-block small text-secondary">Recharge ID: <span class="font-monospace text-warning">#<?= htmlspecialchars($p['wallet_recharge_id']) ?></span></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary font-semibold"><?= $type ?></span></td>
                        <td><span class="small text-secondary"><?= $payer ?></span></td>
                        <td class="fw-bold text-white">₹<?= number_format($p['amount'], 2) ?></td>
                        <td><span class="badge <?= $status_badge ?> text-capitalize px-3 py-1.5"><?= htmlspecialchars($p['status']) ?></span></td>
                        <td>
                            <span class="badge bg-dark border border-secondary text-uppercase" style="font-size:0.75rem;">
                                <?= $p['is_mock'] ? 'Mock Mode' : 'Live Gateway' ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <?php if ($p['status'] === 'success'): ?>
                                    <button class="btn btn-danger btn-sm py-1 px-2 font-semibold trigger-refund" data-ref="<?= htmlspecialchars($p['payment_reference']) ?>" data-max="<?= floatval($p['amount'] - $p['refunded_amount']) ?>"><i class="fa-solid fa-undo me-1"></i>Refund</button>
                                <?php endif; ?>
                                <button class="btn btn-secondary-glass btn-sm py-1 px-2 trigger-settle" data-id="<?= $p['id'] ?>" data-ref="<?= htmlspecialchars($p['payment_reference']) ?>" data-amt="<?= $p['amount'] ?>"><i class="fa-solid fa-check-double me-1"></i>Settle</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Settlements Logs Panel -->
<div class="glass-card p-4">
    <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-money-bill-transfer text-pink me-2"></i>Settlement Logs (Received from Provider)</h5>
    <?php if (count($settlement_records) === 0): ?>
        <div class="text-center py-4 text-secondary small">No settlement reconciliation records found. Click 'Settle' on transactions to log.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle" style="font-size:0.9rem;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Settlement Ref</th>
                        <th>Transaction Ref</th>
                        <th>Type / Booking</th>
                        <th>Amount Settled</th>
                        <th>Logged At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settlement_records as $s): ?>
                        <tr class="border-bottom border-secondary border-opacity-10">
                            <td class="font-monospace small"><?= date('d M Y', strtotime($s['settlement_date'])) ?></td>
                            <td><span class="text-white fw-bold font-monospace"><?= htmlspecialchars($s['settlement_reference'] ?: 'N/A') ?></span></td>
                            <td><span class="font-monospace small text-secondary"><?= htmlspecialchars($s['payment_reference']) ?></span></td>
                            <td>
                                <?php if ($s['booking_reference']): ?>
                                    <span class="badge bg-secondary font-monospace">Booking: <?= htmlspecialchars($s['booking_reference']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-dark border border-secondary text-secondary">Wallet Recharge</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-success">₹<?= number_format($s['amount'], 2) ?></td>
                            <td class="small text-secondary"><?= date('d M Y H:i', strtotime($s['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- REFUND MODAL -->
<div class="modal fade" id="refundModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-secondary text-white" style="border-radius: 20px; background:#121829;">
            <form action="?action=refund" method="POST">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="payment_reference" id="refund_payment_ref">
                <div class="modal-header border-secondary p-4">
                    <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-undo text-danger me-2"></i>Initiate Gateway Refund</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Payment Reference</label>
                        <input type="text" id="refund_payment_ref_disp" class="form-control form-control-swift bg-dark border-secondary text-white font-monospace" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="refund_amount" class="form-label text-secondary small fw-semibold">Refund Amount (₹) - Max <span id="refund_max_disp">0.00</span></label>
                        <input type="number" name="refund_amount" id="refund_amount" class="form-control form-control-swift" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="refund_reason" class="form-label text-secondary small fw-semibold">Reason for Refund</label>
                        <textarea name="refund_reason" id="refund_reason" class="form-control form-control-swift" rows="3" required placeholder="e.g. Customer Cancelled Ticket"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary p-3">
                    <button type="submit" class="btn btn-danger-gradient fw-bold text-uppercase px-4 w-100 py-3 rounded-3" onclick="return confirm('Confirm gateway refund transaction? This action is irreversible.')">Process Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SETTLEMENT MODAL -->
<div class="modal fade" id="settlementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-secondary text-white" style="border-radius: 20px; background:#121829;">
            <form action="?action=log_settlement" method="POST">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="payment_id" id="settle_payment_id">
                <div class="modal-header border-secondary p-4">
                    <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-check-double text-success me-2"></i>Log Payment Settlement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Payment Reference</label>
                        <input type="text" id="settle_payment_ref_disp" class="form-control form-control-swift bg-dark border-secondary text-white font-monospace" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="settlement_reference" class="form-label text-secondary small fw-semibold">Settlement UTN / Ref ID</label>
                        <input type="text" name="settlement_reference" id="settlement_reference" class="form-control form-control-swift" required placeholder="e.g. SETL129384910">
                    </div>
                    <div class="mb-3">
                        <label for="settle_amount" class="form-label text-secondary small fw-semibold">Amount Received (₹)</label>
                        <input type="number" name="settle_amount" id="settle_amount" class="form-control form-control-swift" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="settlement_date" class="form-label text-secondary small fw-semibold">Settlement Date</label>
                        <input type="date" name="settlement_date" id="settlement_date" class="form-control form-control-swift" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary p-3">
                    <button type="submit" class="btn btn-primary-gradient fw-bold text-uppercase px-4 w-100 py-3 rounded-3">Record Settlement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#paymentsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search payments..."
        }
    });

    // Refund Modal Triggers
    $('.trigger-refund').click(function() {
        var ref = $(this).data('ref');
        var maxAmount = parseFloat($(this).data('max'));

        $('#refund_payment_ref').val(ref);
        $('#refund_payment_ref_disp').val(ref);
        $('#refund_max_disp').text(maxAmount.toFixed(2));
        $('#refund_amount').attr('max', maxAmount).val(maxAmount.toFixed(2));

        $('#refundModal').modal('show');
    });

    // Settle Modal Triggers
    $('.trigger-settle').click(function() {
        var id = $(this).data('id');
        var ref = $(this).data('ref');
        var amount = parseFloat($(this).data('amt'));

        $('#settle_payment_id').val(id);
        $('#settle_payment_ref_disp').val(ref);
        $('#settle_amount').val(amount.toFixed(2));

        $('#settlementModal').modal('show');
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
