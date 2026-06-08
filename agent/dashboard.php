<?php
/**
 * Agent Portal Dashboard
 */
require_once __DIR__ . '/header.php';

$agent_id = $_SESSION['user_id'];

try {
    // Fetch Wallet balance
    $wallet_stmt = $pdo->prepare("SELECT balance, status FROM agent_wallets WHERE agent_id = ?");
    $wallet_stmt->execute([$agent_id]);
    $agent_wallet = $wallet_stmt->fetch() ?: ['balance' => 0.00, 'status' => 'active'];
    $agent_wallet_balance = floatval($agent_wallet['balance']);

    // 1. Calculate Earnings Metrics
    $today = date('Y-m-d');
    $start_of_week = date('Y-m-d', strtotime('monday this week'));
    $start_of_month = date('Y-m-d', strtotime('first day of this month'));

    // Today Sales for bookings made by this specific agent
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(b.total_amount), 0) AS total_sales,
            COALESCE(SUM(b.agent_net_earning), 0) AS agent_net
        FROM bookings b
        WHERE b.agent_id = :agent_id 
          AND DATE(b.created_at) = :today
          AND b.payment_status = 'paid'
    ");
    $stmt->execute([':agent_id' => $agent_id, ':today' => $today]);
    $today_metrics = $stmt->fetch();

    // Weekly Sales
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(b.total_amount), 0) AS total_sales,
            COALESCE(SUM(b.agent_net_earning), 0) AS agent_net
        FROM bookings b
        WHERE b.agent_id = :agent_id 
          AND DATE(b.created_at) >= :start_week
          AND b.payment_status = 'paid'
    ");
    $stmt->execute([':agent_id' => $agent_id, ':start_week' => $start_of_week]);
    $weekly_metrics = $stmt->fetch();

    // Monthly Sales
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(b.total_amount), 0) AS total_sales,
            COALESCE(SUM(b.agent_net_earning), 0) AS agent_net
        FROM bookings b
        WHERE b.agent_id = :agent_id 
          AND DATE(b.created_at) >= :start_month
          AND b.payment_status = 'paid'
    ");
    $stmt->execute([':agent_id' => $agent_id, ':start_month' => $start_of_month]);
    $monthly_metrics = $stmt->fetch();

    // Lifetime Sales & Commission
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(b.total_amount), 0) AS total_sales,
            COALESCE(SUM(b.agent_net_earning), 0) AS agent_net,
            COALESCE(SUM(b.discount_applied), 0) AS total_discounts
        FROM bookings b
        WHERE b.agent_id = :agent_id 
          AND b.payment_status = 'paid'
    ");
    $stmt->execute([':agent_id' => $agent_id]);
    $lifetime_metrics = $stmt->fetch();

    // 2. Fetch Earnings over the last 7 days for the chart using a single aggregated query
    $chart_days = [];
    $chart_earnings = [];
    $start_date = date('Y-m-d', strtotime('-6 days'));
    $end_date = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT 
            DATE(b.created_at) AS booking_date,
            SUM(b.agent_net_earning) AS total_earnings
        FROM bookings b
        WHERE b.agent_id = :agent_id 
          AND DATE(b.created_at) >= :start_date
          AND DATE(b.created_at) <= :end_date
          AND b.payment_status = 'paid'
        GROUP BY DATE(b.created_at)
    ");
    $stmt->execute([':agent_id' => $agent_id, ':start_date' => $start_date, ':end_date' => $end_date]);
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    for ($i = 6; $i >= 0; $i--) {
        $date_label = date('Y-m-d', strtotime("-$i days"));
        $display_label = date('D, d M', strtotime($date_label));
        $chart_days[] = $display_label;
        $chart_earnings[] = floatval($results[$date_label] ?? 0.00);
    }

    // 3. Fetch Recent Bookings of this Agent (Limit 5)
    $stmt = $pdo->prepare("
        SELECT 
            b.booking_reference,
            b.customer_name,
            b.total_amount,
            b.discount_applied,
            b.final_fare,
            b.created_at,
            bs.bus_name,
            r.source,
            r.destination
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        WHERE b.agent_id = :agent_id
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([':agent_id' => $agent_id]);
    $recent_bookings = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database stats computation failed: " . $e->getMessage());
}
?>

<?php if ($agent_wallet_balance < 1000 && $agent_wallet['status'] === 'active'): ?>
<div id="low-balance-alert-dashboard" class="alert alert-warning alert-dismissible border-warning border-opacity-20 bg-warning bg-opacity-10 text-warning d-flex align-items-center mb-4 rounded-4 p-3 shadow-lg fade show" role="alert" style="display: none !important;">
    <i class="fa-solid fa-triangle-exclamation fs-4 me-3"></i>
    <div class="flex-grow-1">
        <strong class="d-block">Low Wallet Balance Warning</strong>
        <span class="small">Your wallet balance is ₹<?= number_format($agent_wallet_balance, 2) ?>. Please <a href="wallet_history.php?recharge=1" class="text-warning fw-bold text-decoration-underline">recharge your wallet</a> to continue booking tickets.</span>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" id="dismiss-low-balance-dashboard" style="filter: var(--btn-close-filter);"></button>
</div>
<script>
    if (localStorage.getItem('dismissed_low_balance_warning') !== 'true') {
        document.getElementById('low-balance-alert-dashboard').style.setProperty('display', 'flex', 'important');
    }
    document.getElementById('dismiss-low-balance-dashboard')?.addEventListener('click', function() {
        localStorage.setItem('dismissed_low_balance_warning', 'true');
    });
</script>
<?php endif; ?>

<?php if ($agent_wallet_balance >= 1000): ?>
<script>
    localStorage.removeItem('dismissed_low_balance_warning');
</script>
<?php endif; ?>

<!-- Metrics Row -->
<div class="row g-4 mb-5">
    <!-- Wallet Balance -->
    <div class="col-md-6 col-lg-3 col-xl-2-5">
        <div class="glass-card p-4 metric-card h-100 border border-info border-opacity-20">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Wallet Balance</span>
                <span class="metric-icon" style="color: #0dcaf0; border-color: rgba(13,202,240,0.2); background: rgba(13,202,240,0.1);"><i class="fa-solid fa-wallet"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1">₹<?= number_format($agent_wallet_balance, 2) ?></h3>
            <span class="text-secondary small">
                Status: 
                <?php if ($agent_wallet['status'] === 'frozen'): ?>
                    <span class="text-danger fw-bold">Frozen</span>
                <?php else: ?>
                    <span class="text-success fw-bold">Active</span>
                <?php endif; ?>
                &middot; <a href="wallet_history.php" class="text-indigo small text-decoration-none">Recharge &rarr;</a>
            </span>
        </div>
    </div>

    <!-- Today Sales -->
    <div class="col-md-6 col-lg-3 col-xl-2-5">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase"><?= __('today_sales', 'Today Sales') ?></span>
                <span class="metric-icon"><i class="fa-solid fa-calendar-day"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1"><?= CURRENCY ?><?= number_format($today_metrics['total_sales'] ?? 0, 2) ?></h3>
            <span class="text-secondary small"><?= __('net_yield', 'Net Yield') ?>: <?= CURRENCY ?><?= number_format($today_metrics['agent_net'] ?? 0, 2) ?></span>
        </div>
    </div>

    <!-- Weekly Sales -->
    <div class="col-md-6 col-lg-3 col-xl-2-5">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase"><?= __('weekly_sales', 'Weekly Sales') ?></span>
                <span class="metric-icon" style="color: #db2777; border-color: rgba(219,39,119,0.2); background: rgba(219,39,119,0.1);"><i class="fa-solid fa-calendar-week"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1" style="background: linear-gradient(135deg, #f472b6 0%, #db2777 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?= CURRENCY ?><?= number_format($weekly_metrics['total_sales'] ?? 0, 2) ?></h3>
            <span class="text-secondary small"><?= __('net_yield', 'Net Yield') ?>: <?= CURRENCY ?><?= number_format($weekly_metrics['agent_net'] ?? 0, 2) ?></span>
        </div>
    </div>

    <!-- Monthly Sales -->
    <div class="col-md-6 col-lg-3 col-xl-2-5">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase"><?= __('monthly_sales', 'Monthly Sales') ?></span>
                <span class="metric-icon"><i class="fa-solid fa-chart-line"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1"><?= CURRENCY ?><?= number_format($monthly_metrics['total_sales'] ?? 0, 2) ?></h3>
            <span class="text-secondary small"><?= __('net_yield', 'Net Yield') ?>: <?= CURRENCY ?><?= number_format($monthly_metrics['agent_net'] ?? 0, 2) ?></span>
        </div>
    </div>

    <!-- Total Discounts Earned/Applied -->
    <div class="col-md-6 col-lg-3 col-xl-2-5">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase"><?= __('total_discounts_received', 'Total Discounts Received') ?></span>
                <span class="metric-icon" style="color: #fbbf24; border-color: rgba(245,158,11,0.2); background: rgba(245,158,11,0.1);"><i class="fa-solid fa-tags"></i></span>
            </div>
            <h3 class="fw-bold text-warning mb-1"><?= CURRENCY ?><?= number_format($lifetime_metrics['total_discounts'] ?? 0, 2) ?></h3>
            <span class="text-secondary small"><?= __('operator_margin_desc', 'Direct fleet operator margin') ?></span>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Chart Column -->
    <div class="col-lg-8">
        <div class="glass-card p-4 h-100">
            <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-chart-area text-indigo me-2"></i><?= __('daily_earnings_trend', 'Daily Earnings Trend (7 Days)') ?></h5>
            <div style="height: 300px; position: relative;">
                <canvas id="agentEarningsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Right Summary Column -->
    <div class="col-lg-4">
        <div class="glass-card p-4 h-100">
            <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-circle-check text-pink me-2"></i><?= __('partner_desk_details', 'Partner Desk Details') ?></h5>
            <div class="p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-25 mb-4">
                <div class="d-flex justify-content-between text-secondary small mb-2"><span><?= __('agency_name', 'Agency Name') ?></span><span class="text-white fw-bold"><?= htmlspecialchars($agent_profile['agency_name'] ?? 'N/A') ?></span></div>
                <div class="d-flex justify-content-between text-secondary small mb-2"><span><?= __('commission_rate', 'Commission Rate') ?></span><span class="text-white fw-bold text-warning"><?= htmlspecialchars($agent_profile['commission_rate'] ?? '2.00') ?>%</span></div>
                <div class="d-flex justify-content-between text-secondary small mb-3"><span><?= __('lifetime_gross_sales', 'Lifetime Gross Sales') ?></span><span class="text-white fw-bold text-success">₹<?= number_format($lifetime_metrics['total_sales'] ?? 0, 2) ?></span></div>
                <div class="d-flex justify-content-between text-white fw-bold fs-5 pt-3 border-top border-secondary border-opacity-30">
                    <span><?= __('net_margin_saved', 'Net Margin Saved') ?></span>
                    <span class="text-warning">₹<?= number_format($lifetime_metrics['total_discounts'] ?? 0, 2) ?></span>
                </div>
            </div>
            <p class="small text-secondary"><i class="fa-solid fa-circle-exclamation me-1"></i> <?= __('agent_portal_discounts_desc', 'Agent portal allows booking with direct discounts pre-configured per bus by the operator.') ?></p>
        </div>
    </div>
</div>

<!-- Recent Bookings Table -->
<div class="glass-card p-4">
    <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-clock-rotate-left text-indigo me-2"></i><?= __('recent_bookings', 'Recent Bookings') ?></h5>
    <?php if (count($recent_bookings) === 0): ?>
        <div class="text-center py-4 text-secondary small"><?= __('no_bookings_registered', 'No bookings registered yet. Search trips to make a booking.') ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle">
                <thead>
                    <tr>
                        <th><?= __('booking_ref', 'Booking Ref') ?></th>
                        <th><?= __('bus_route', 'Bus / Route') ?></th>
                        <th><?= __('passenger_name', 'Passenger Name') ?></th>
                        <th><?= __('original_fare', 'Original Fare') ?></th>
                        <th><?= __('discount_applied', 'Discount Applied') ?></th>
                        <th><?= __('final_paid', 'Final Paid') ?></th>
                        <th><?= __('date', 'Date') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $b): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-indigo"><?= htmlspecialchars($b['booking_reference']) ?></td>
                            <td>
                                <span class="d-block fw-semibold text-white"><?= htmlspecialchars($b['bus_name']) ?></span>
                                <span class="text-secondary small"><?= htmlspecialchars($b['source']) ?> <i class="fa-solid fa-arrow-right mx-1"></i> <?= htmlspecialchars($b['destination']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($b['customer_name']) ?></td>
                            <td>₹<?= number_format($b['total_amount'] + $b['discount_applied'], 2) ?></td>
                            <td class="text-warning">₹<?= number_format($b['discount_applied'], 2) ?></td>
                            <td class="text-success fw-bold">₹<?= number_format($b['final_fare'], 2) ?></td>
                            <td class="text-secondary small"><?= date('d M Y H:i', strtotime($b['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    var ctx = document.getElementById('agentEarningsChart').getContext('2d');
    
    var gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_days) ?>,
            datasets: [{
                label: '<?= __('net_earnings_chart', 'Net Earnings (₹)') ?>',
                data: <?= json_encode($chart_earnings) ?>,
                backgroundColor: gradient,
                borderColor: '#6366f1',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#8b5cf6',
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#94a3b8', font: { family: 'Outfit' } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8', font: { family: 'Outfit' } }
                }
            }
        }
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
