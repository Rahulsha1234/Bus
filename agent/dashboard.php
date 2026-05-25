<?php
/**
 * Agent Panel Dashboard
 */
require_once __DIR__ . '/header.php';

$agent_id = $_SESSION['user_id'];

// 1. Calculate Earnings Metrics
try {
    // Current Dates
    $today = date('Y-m-d');
    $start_of_week = date('Y-m-d', strtotime('monday this week'));
    $start_of_month = date('Y-m-d', strtotime('first day of this month'));

    // Today Sales
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(b.total_amount), 0) AS total_sales,
            COALESCE(SUM(b.admin_commission), 0) AS admin_comm,
            COALESCE(SUM(b.agent_net_earning), 0) AS agent_net
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        WHERE bs.agent_id = :agent_id 
          AND DATE(b.created_at) = :today
          AND b.payment_status = 'paid'
    ");
    $stmt->execute([':agent_id' => $agent_id, ':today' => $today]);
    $today_metrics = $stmt->fetch();

    // Weekly Sales (Current Week starting Monday)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(b.total_amount), 0) AS total_sales,
            COALESCE(SUM(b.admin_commission), 0) AS admin_comm,
            COALESCE(SUM(b.agent_net_earning), 0) AS agent_net
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        WHERE bs.agent_id = :agent_id 
          AND DATE(b.created_at) >= :start_week
          AND b.payment_status = 'paid'
    ");
    $stmt->execute([':agent_id' => $agent_id, ':start_week' => $start_of_week]);
    $weekly_metrics = $stmt->fetch();

    // Monthly Sales (Current Month)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(b.total_amount), 0) AS total_sales,
            COALESCE(SUM(b.admin_commission), 0) AS admin_comm,
            COALESCE(SUM(b.agent_net_earning), 0) AS agent_net
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        WHERE bs.agent_id = :agent_id 
          AND DATE(b.created_at) >= :start_month
          AND b.payment_status = 'paid'
    ");
    $stmt->execute([':agent_id' => $agent_id, ':start_month' => $start_of_month]);
    $monthly_metrics = $stmt->fetch();

    // Total Lifetime Sales & Commission Payable
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(b.total_amount), 0) AS total_sales,
            COALESCE(SUM(b.admin_commission), 0) AS admin_comm,
            COALESCE(SUM(b.agent_net_earning), 0) AS agent_net
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        WHERE bs.agent_id = :agent_id 
          AND b.payment_status = 'paid'
    ");
    $stmt->execute([':agent_id' => $agent_id]);
    $lifetime_metrics = $stmt->fetch();

    // Commission Paid through settlements
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(commission_payable), 0) 
        FROM weekly_settlements 
        WHERE agent_id = :agent_id AND status = 'paid'
    ");
    $stmt->execute([':agent_id' => $agent_id]);
    $commission_paid = floatval($stmt->fetchColumn());
    
    // Net Payable Commission = Lifetime Admin Comm - Commission already paid through settlements
    $payable_commission = max(0, floatval($lifetime_metrics['admin_comm']) - $commission_paid);

    // 2. Fetch Earnings over the last 7 days for the chart
    $chart_days = [];
    $chart_earnings = [];
    for ($i = 6; $i >= 0; $i--) {
        $date_label = date('Y-m-d', strtotime("-$i days"));
        $display_label = date('D, d M', strtotime($date_label));
        $chart_days[] = $display_label;

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(b.agent_net_earning), 0) 
            FROM bookings b
            JOIN trips t ON b.trip_id = t.id
            JOIN buses bs ON t.bus_id = bs.id
            WHERE bs.agent_id = :agent_id 
              AND DATE(b.created_at) = :date_label
              AND b.payment_status = 'paid'
        ");
        $stmt->execute([':agent_id' => $agent_id, ':date_label' => $date_label]);
        $chart_earnings[] = floatval($stmt->fetchColumn());
    }

    // 3. Fetch Recent bookings (Limit 5)
    $stmt = $pdo->prepare("
        SELECT 
            b.booking_reference,
            b.customer_name,
            b.total_amount,
            b.admin_commission,
            b.agent_net_earning,
            b.created_at,
            bs.bus_name,
            r.source,
            r.destination
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        WHERE bs.agent_id = :agent_id
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([':agent_id' => $agent_id]);
    $recent_bookings = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database stats computation failed: " . $e->getMessage());
}
?>

<!-- Metrics Row -->
<div class="row g-4 mb-5">
    <!-- Today Earnings -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Today Net Earning</span>
                <span class="metric-icon"><i class="fa-solid fa-calendar-day"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1"><?= CURRENCY ?><?= number_format($today_metrics['agent_net'], 2) ?></h3>
            <span class="text-secondary small">Gross Sold: <?= CURRENCY ?><?= number_format($today_metrics['total_sales'], 2) ?></span>
        </div>
    </div>

    <!-- Weekly Earnings -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Weekly Net Earning</span>
                <span class="metric-icon" style="color: #db2777; border-color: rgba(219,39,119,0.2); background: rgba(219,39,119,0.1);"><i class="fa-solid fa-calendar-week"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1" style="background: linear-gradient(135deg, #f472b6 0%, #db2777 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?= CURRENCY ?><?= number_format($weekly_metrics['agent_net'], 2) ?></h3>
            <span class="text-secondary small">Gross Sold: <?= CURRENCY ?><?= number_format($weekly_metrics['total_sales'], 2) ?></span>
        </div>
    </div>

    <!-- Monthly Earnings -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Monthly Net Earning</span>
                <span class="metric-icon"><i class="fa-solid fa-chart-line"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1"><?= CURRENCY ?><?= number_format($monthly_metrics['agent_net'], 2) ?></h3>
            <span class="text-secondary small">Gross Sold: <?= CURRENCY ?><?= number_format($monthly_metrics['total_sales'], 2) ?></span>
        </div>
    </div>

    <!-- Commission Payable -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Payable Commission</span>
                <span class="metric-icon" style="color: #fbbf24; border-color: rgba(245,158,11,0.2); background: rgba(245,158,11,0.1);"><i class="fa-solid fa-wallet"></i></span>
            </div>
            <h3 class="fw-bold text-warning mb-1"><?= CURRENCY ?><?= number_format($payable_commission, 2) ?></h3>
            <span class="text-secondary small">Super Admin Fee (2% rate)</span>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Chart Column -->
    <div class="col-lg-8">
        <div class="glass-card p-4 h-100">
            <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-chart-area text-indigo me-2"></i>Daily Earnings Trend (7 Days)</h5>
            <div style="height: 300px; position: relative;">
                <canvas id="agentEarningsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Right Summary Column -->
    <div class="col-lg-4">
        <div class="glass-card p-4 h-100">
            <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-circle-check text-pink me-2"></i>Agency Commission Tally</h5>
            <div class="p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-25 mb-4">
                <div class="d-flex justify-content-between text-secondary small mb-2"><span>Total Seats Gross Sales</span><span class="text-white fw-bold">₹<?= number_format($lifetime_metrics['total_sales'], 2) ?></span></div>
                <div class="d-flex justify-content-between text-secondary small mb-2"><span>Total Admin Comm (2%)</span><span class="text-white fw-bold text-warning">₹<?= number_format($lifetime_metrics['admin_comm'], 2) ?></span></div>
                <div class="d-flex justify-content-between text-secondary small mb-3"><span>Settled Commission Payout</span><span class="text-white fw-bold text-success">₹<?= number_format($commission_paid, 2) ?></span></div>
                <div class="d-flex justify-content-between text-white fw-bold fs-5 pt-3 border-top border-secondary border-opacity-30">
                    <span>Payable Balance</span>
                    <span class="text-warning">₹<?= number_format($payable_commission, 2) ?></span>
                </div>
            </div>
            <p class="small text-secondary"><i class="fa-solid fa-circle-exclamation me-1"></i> Weekly settlements are processed automatically by the Super Admin at the end of every week cycle.</p>
        </div>
    </div>
</div>

<!-- Recent Bookings Table -->
<div class="glass-card p-4">
    <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-clock-rotate-left text-indigo me-2"></i>Recent Bookings</h5>
    <?php if (count($recent_bookings) === 0): ?>
        <div class="text-center py-4 text-secondary small">No bookings received yet. Sechedule trips to receive bookings.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle">
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Bus / Route</th>
                        <th>Customer</th>
                        <th>Sold Amount</th>
                        <th>Admin Comm (2%)</th>
                        <th>Net Earning</th>
                        <th>Date</th>
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
                            <td>₹<?= number_format($b['total_amount'], 2) ?></td>
                            <td class="text-warning">₹<?= number_format($b['admin_commission'], 2) ?></td>
                            <td class="text-success fw-bold">₹<?= number_format($b['agent_net_earning'], 2) ?></td>
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
    
    // Chart Gradient
    var gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_days) ?>,
            datasets: [{
                label: 'Net Earnings (₹)',
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
