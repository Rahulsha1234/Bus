<?php
/**
 * Agent Wallet History & Recharge Portal
 */
require_once __DIR__ . '/header.php';

$page_title = "My Wallet & Transactions";

// Fetch the wallet
$wallet_stmt = $pdo->prepare("SELECT * FROM agent_wallets WHERE agent_id = ?");
$wallet_stmt->execute([$user['id']]);
$wallet = $wallet_stmt->fetch();

$wallet_balance = $wallet ? floatval($wallet['balance']) : 0.00;
$wallet_status = $wallet ? $wallet['status'] : 'active';
$wallet_id = $wallet ? intval($wallet['id']) : 0;

// Fetch ledger transactions
$ledger_stmt = $pdo->prepare("
    SELECT wt.*, u.username as creator_name
    FROM wallet_transactions wt
    LEFT JOIN users u ON wt.created_by = u.id
    WHERE wt.wallet_id = ?
    ORDER BY wt.created_at DESC
");
$ledger_stmt->execute([$wallet_id]);
$ledger_transactions = $ledger_stmt->fetchAll();

// Fetch recharge logs
$recharges_stmt = $pdo->prepare("
    SELECT * 
    FROM wallet_recharges 
    WHERE wallet_id = ? 
    ORDER BY created_at DESC
");
$recharges_stmt->execute([$wallet_id]);
$recharge_records = $recharges_stmt->fetchAll();
?>

<style>
    .wallet-tab-btn {
        background: rgba(0, 0, 0, 0.04) !important;
        color: var(--text-secondary) !important;
        border: 1px solid var(--border-glass) !important;
        transition: all 0.3s ease;
    }
    .wallet-tab-btn:hover {
        background: rgba(0, 0, 0, 0.08) !important;
        color: var(--text-primary) !important;
    }
    .wallet-tab-btn.active {
        background: var(--accent-primary) !important;
        color: #ffffff !important;
        border-color: var(--accent-primary) !important;
        box-shadow: 0 4px 15px rgba(25, 135, 84, 0.2) !important;
    }
    [data-theme="dark"] .wallet-tab-btn {
        background: rgba(255, 255, 255, 0.04) !important;
    }
    [data-theme="dark"] .wallet-tab-btn:hover {
        background: rgba(255, 255, 255, 0.08) !important;
    }
</style>

<div class="row g-4">
    <!-- Wallet Summary Widget -->
    <div class="col-12">
        <div class="glass-card p-4 text-center" style="border-radius: 20px; border: 1px solid var(--border-glass);">
            <div class="d-flex justify-content-center mb-3">
                <span class="p-3 rounded-circle text-success" style="font-size: 2.5rem; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; background: rgba(25, 135, 84, 0.12);">
                    <i class="fa-solid fa-wallet"></i>
                </span>
            </div>
            <h4 class="text-secondary small fw-semibold text-uppercase mb-1" style="letter-spacing: 0.5px;">Available Wallet Balance</h4>
            <h2 class="fw-bold mb-2" style="font-size: 2.5rem; color: var(--text-primary);">₹<?= number_format($wallet_balance, 2) ?></h2>
            
            <div class="mb-4">
                <?php if ($wallet_status === 'frozen'): ?>
                    <span class="badge px-3 py-2" style="background: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.25); font-weight: 600; font-size: 0.85rem; border-radius: 30px;">
                        <i class="fa-solid fa-snowflake me-1"></i> Frozen
                    </span>
                <?php else: ?>
                    <span class="badge px-3 py-2" style="background: rgba(25, 135, 84, 0.15); color: #198754; border: 1px solid rgba(25, 135, 84, 0.25); font-weight: 600; font-size: 0.85rem; border-radius: 30px;">
                        <i class="fa-solid fa-circle-check me-1"></i> Active
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($wallet_balance < 1000 && $wallet_status === 'active'): ?>
                <div id="low-balance-alert-wallet" class="alert alert-warning alert-dismissible border-warning border-opacity-20 bg-warning bg-opacity-10 text-warning text-start mb-4 small rounded-3 fade show" role="alert" style="display: none !important;">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> Low Wallet Balance. Please recharge to continue booking.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" id="dismiss-low-balance-wallet" style="padding: 0.85rem; filter: var(--btn-close-filter);"></button>
                </div>
                <script>
                    if (localStorage.getItem('dismissed_low_balance_warning') !== 'true') {
                        document.getElementById('low-balance-alert-wallet').style.setProperty('display', 'block', 'important');
                    }
                    document.getElementById('dismiss-low-balance-wallet')?.addEventListener('click', function() {
                        localStorage.setItem('dismissed_low_balance_warning', 'true');
                    });
                </script>
            <?php endif; ?>
            
            <?php if ($wallet_balance >= 1000): ?>
                <script>
                    localStorage.removeItem('dismissed_low_balance_warning');
                </script>
            <?php endif; ?>

            <?php if ($wallet_status === 'active'): ?>
                <button type="button" class="btn btn-primary-gradient py-3 text-uppercase fw-bold mx-auto d-block" style="border-radius: 12px; letter-spacing: 0.5px; max-width: 320px; width: 100%;" data-bs-toggle="modal" data-bs-target="#rechargeModal">
                    <i class="fa-solid fa-plus me-2"></i>Recharge Wallet
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-secondary py-3 text-uppercase fw-bold disabled mx-auto d-block" style="border-radius: 12px; letter-spacing: 0.5px; max-width: 320px; width: 100%;" disabled>
                    <i class="fa-solid fa-ban me-2"></i>Recharge Disabled
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ledger & Recharge History Panel -->
    <div class="col-12">
        <div class="glass-card p-4" style="border-radius: 20px; border: 1px solid var(--border-glass);">
            <ul class="nav nav-pills mb-4 gap-2" id="walletTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="btn wallet-tab-btn active px-4 py-2" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger-pane" type="button" role="tab">
                        <i class="fa-solid fa-list-check me-2"></i>Account Ledger
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="btn wallet-tab-btn px-4 py-2" id="recharges-tab" data-bs-toggle="tab" data-bs-target="#recharges-pane" type="button" role="tab">
                        <i class="fa-solid fa-receipt me-2"></i>Recharge History
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="walletTabContent">
                <!-- Ledger Pane -->
                <div class="tab-pane fade show active" id="ledger-pane" role="tabpanel" tabindex="0">
                    <div class="table-responsive">
                        <table id="ledgerTable" class="table table-swift table-hover align-middle text-nowrap" style="width: 100%; min-width: 700px;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Bal Before</th>
                                    <th>Bal After</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ledger_transactions as $tx): ?>
                                    <tr>
                                        <td class="font-monospace small"><?= date('d-M-Y H:i', strtotime($tx['created_at'])) ?></td>
                                        <td>
                                            <?php
                                            $badge_class = 'bg-secondary';
                                            $tx_type = $tx['transaction_type'];
                                            if ($tx_type === 'recharge' || $tx_type === 'refund' || $tx_type === 'admin_credit') {
                                                $badge_style = 'background: rgba(25, 135, 84, 0.12); color: #198754; border: 1px solid rgba(25, 135, 84, 0.25);';
                                            } else {
                                                $badge_style = 'background: rgba(220, 53, 69, 0.12); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.25);';
                                            }
                                            ?>
                                            <span class="badge text-capitalize px-3 py-2" style="<?= $badge_style ?> font-size: 0.8rem; font-weight: 600; border-radius: 30px;"><?= str_replace('_', ' ', $tx_type) ?></span>
                                        </td>
                                        <td class="fw-bold <?= ($tx_type === 'recharge' || $tx_type === 'refund' || $tx_type === 'admin_credit') ? 'text-success' : 'text-danger' ?>">
                                            <?= ($tx_type === 'recharge' || $tx_type === 'refund' || $tx_type === 'admin_credit') ? '+' : '-' ?>₹<?= number_format($tx['amount'], 2) ?>
                                        </td>
                                        <td>₹<?= number_format($tx['balance_before'], 2) ?></td>
                                        <td class="fw-bold">₹<?= number_format($tx['balance_after'], 2) ?></td>
                                        <td class="small text-secondary"><?= htmlspecialchars($tx['remarks']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recharges Pane -->
                <div class="tab-pane fade" id="recharges-pane" role="tabpanel" tabindex="0">
                    <div class="table-responsive">
                        <table id="rechargesTable" class="table table-swift table-hover align-middle text-nowrap" style="width: 100%; min-width: 700px;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Payment ID</th>
                                    <th>Order ID</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recharge_records as $rec): ?>
                                    <tr>
                                        <td class="font-monospace small"><?= date('d-M-Y H:i', strtotime($rec['created_at'])) ?></td>
                                        <td class="fw-bold text-success">₹<?= number_format($rec['amount'], 2) ?></td>
                                        <td class="font-monospace small text-secondary"><?= htmlspecialchars($rec['razorpay_payment_id']) ?></td>
                                        <td class="font-monospace small text-secondary"><?= htmlspecialchars($rec['razorpay_order_id']) ?></td>
                                        <td>
                                            <?php if ($rec['status'] === 'success'): ?>
                                                <span class="badge px-3 py-2 text-capitalize" style="background: rgba(25, 135, 84, 0.12); color: #198754; border: 1px solid rgba(25, 135, 84, 0.25); font-size: 0.8rem; font-weight: 600; border-radius: 30px;">Success</span>
                                            <?php else: ?>
                                                <span class="badge px-3 py-2 text-capitalize" style="background: rgba(255, 193, 7, 0.12); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.25); font-size: 0.8rem; font-weight: 600; border-radius: 30px;"><?= htmlspecialchars($rec['status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CHOOSE RECHARGE AMOUNT MODAL -->
<div class="modal fade" id="rechargeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-secondary text-white shadow-2xl" style="border-radius: 20px; background: #121829;">
            <div class="modal-header border-secondary p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-plus text-success me-2"></i>Recharge Wallet Balance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="rechargeForm">
                    <input type="hidden" name="csrf_token" id="recharge_csrf_token" value="<?= get_csrf_token() ?>">
                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-semibold">Enter Amount to Recharge (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                            <input type="number" id="rechargeAmountVal" name="amount" class="form-control form-control-swift bg-dark text-white border-secondary" placeholder="e.g. 2000" min="1" required>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" id="btnSubmitRecharge" class="btn btn-primary-gradient py-3 fw-bold text-uppercase" style="border-radius: 12px;">Initiate Secure Recharge</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#ledgerTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search ledger..."
        }
    });

    $('#rechargesTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search recharges..."
        }
    });

    // Check if shortfall or recharge is passed in URL
    var urlParams = new URLSearchParams(window.location.search);
    var shortfall = urlParams.get('shortfall');
    if (shortfall) {
        var cleanShortfall = Math.ceil(parseFloat(shortfall));
        $('#rechargeAmountVal').val(cleanShortfall);
        $('#rechargeModal').modal('show');
    } else if (urlParams.get('recharge') === '1') {
        $('#rechargeModal').modal('show');
    }

    // Clean URL query parameters so refresh doesn't trigger modal again
    if (window.location.search) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    $('#rechargeForm').submit(function(e) {
        e.preventDefault();
        var amountVal = parseFloat($('#rechargeAmountVal').val());
        if (isNaN(amountVal) || amountVal <= 0) {
            alert("Please enter a valid amount.");
            return;
        }

        var origBtnText = $('#btnSubmitRecharge').html();
        $('#btnSubmitRecharge').html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing Secure Transaction...').addClass('disabled');

        $.ajax({
            url: '<?= BASE_URL ?>/ajax/initiate_recharge.php',
            type: 'POST',
            data: {
                csrf_token: $('#recharge_csrf_token').val(),
                amount: amountVal
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.redirect) {
                    window.location.href = response.url;
                } else {
                    alert("Recharge Error: " + response.message);
                    $('#btnSubmitRecharge').html(origBtnText).removeClass('disabled');
                }
            },
            error: function() {
                alert("CRITICAL ERROR: Failed to communicate with payment processor. Please check connection.");
                $('#btnSubmitRecharge').html(origBtnText).removeClass('disabled');
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
