<?php
/**
 * Super Admin Panel Dashboard
 */
require_once __DIR__ . '/header.php';

try {
    $today = date('Y-m-d');
    $start_of_week = date('Y-m-d', strtotime('monday this week'));
    $start_of_month = date('Y-m-d', strtotime('first day of this month'));

    // 1. Core System stats
    $total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'paid'")->fetchColumn();
    $total_agents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $active_agents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'approved'")->fetchColumn();

    // Today Sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0), COALESCE(SUM(admin_commission), 0) FROM bookings WHERE DATE(created_at) = ? AND payment_status = 'paid'");
    $stmt->execute([$today]);
    $today_metrics = $stmt->fetch(PDO::FETCH_NUM);

    // Weekly Sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0), COALESCE(SUM(admin_commission), 0) FROM bookings WHERE DATE(created_at) >= ? AND payment_status = 'paid'");
    $stmt->execute([$start_of_week]);
    $weekly_metrics = $stmt->fetch(PDO::FETCH_NUM);

    // Monthly Sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0), COALESCE(SUM(admin_commission), 0) FROM bookings WHERE DATE(created_at) >= ? AND payment_status = 'paid'");
    $stmt->execute([$start_of_month]);
    $monthly_metrics = $stmt->fetch(PDO::FETCH_NUM);

    // Lifetime Commission Metrics
    $lifetime_comm = $pdo->query("SELECT COALESCE(SUM(admin_commission), 0) FROM bookings WHERE payment_status = 'paid'")->fetchColumn();
    $paid_comm = $pdo->query("SELECT COALESCE(SUM(commission_payable), 0) FROM weekly_settlements WHERE status = 'paid'")->fetchColumn();
    $pending_comm = max(0, $lifetime_comm - $paid_comm);

    // 2. Chart 1: Sales trend over 7 days using a single aggregated query
    $chart_days = [];
    $chart_sales = [];
    $chart_comm = [];
    $start_date = date('Y-m-d', strtotime('-6 days'));
    $end_date = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) AS booking_date,
            SUM(total_amount) AS total_sales,
            SUM(admin_commission) AS total_comm
        FROM bookings
        WHERE DATE(created_at) >= ? 
          AND DATE(created_at) <= ?
          AND payment_status = 'paid'
        GROUP BY DATE(created_at)
    ");
    $stmt->execute([$start_date, $end_date]);
    $results = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    for ($i = 6; $i >= 0; $i--) {
        $date_label = date('Y-m-d', strtotime("-$i days"));
        $display_label = date('D, d M', strtotime($date_label));
        $chart_days[] = $display_label;

        $sales_val = isset($results[$date_label]) ? floatval($results[$date_label]['total_sales']) : 0.00;
        $comm_val = isset($results[$date_label]) ? floatval($results[$date_label]['total_comm']) : 0.00;

        $chart_sales[] = $sales_val;
        $chart_comm[] = $comm_val;
    }

    // 3. Chart 2: Admin/Operator-wise Performance (Gross sales comparison)
    $stmt = $pdo->query("
        SELECT 
            op.username AS agency_name,
            COALESCE(SUM(b.total_amount), 0) AS total_gross
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN users op ON bs.admin_id = op.id
        WHERE b.payment_status = 'paid'
        GROUP BY op.id, op.username
        ORDER BY total_gross DESC
        LIMIT 5
    ");
    $agent_performance = $stmt->fetchAll();
    
    $perf_labels = [];
    $perf_data = [];
    foreach ($agent_performance as $ap) {
        $perf_labels[] = $ap['agency_name'];
        $perf_data[] = floatval($ap['total_gross']);
    }

    // 4. Fetch System Recent bookings (Limit 5)
    $recent_bookings = $pdo->query("
        SELECT 
            b.booking_reference,
            b.customer_name,
            b.total_amount,
            b.admin_commission,
            b.agent_net_earning,
            b.created_at,
            op.username AS agency_name
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN users op ON bs.admin_id = op.id
        ORDER BY b.created_at DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    die("Admin dashboard statistics loading failed: " . $e->getMessage());
}
?>

<!-- Metrics row 1 -->
<div class="row g-4 mb-4">
    <!-- Gross Sales -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Today Gross Sale</span>
                <span class="metric-icon"><i class="fa-solid fa-coins"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1"><?= CURRENCY ?><?= number_format($today_metrics[0], 2) ?></h3>
            <span class="text-secondary small">Admin Comm: <?= CURRENCY ?><?= number_format($today_metrics[1], 2) ?></span>
        </div>
    </div>

    <!-- Weekly Gross -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Weekly Gross Sale</span>
                <span class="metric-icon" style="color: #db2777; border-color: rgba(219,39,119,0.2); background: rgba(219,39,119,0.1);"><i class="fa-solid fa-circle-dollar-to-slot"></i></span>
            </div>
            <h3 class="fw-bold text-white mb-1" style="background: linear-gradient(135deg, #f472b6 0%, #db2777 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?= CURRENCY ?><?= number_format($weekly_metrics[0], 2) ?></h3>
            <span class="text-secondary small">Admin Comm: <?= CURRENCY ?><?= number_format($weekly_metrics[1], 2) ?></span>
        </div>
    </div>

    <!-- Total Commission -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Admin Commission</span>
                <span class="metric-icon"><i class="fa-solid fa-piggy-bank"></i></span>
            </div>
            <h3 class="fw-bold text-indigo mb-1"><?= CURRENCY ?><?= number_format($lifetime_comm, 2) ?></h3>
            <span class="text-secondary small">Lifetime earned at 2% rate</span>
        </div>
    </div>

    <!-- Pending Settlements -->
    <div class="col-md-6 col-lg-3">
        <div class="glass-card p-4 metric-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-semibold text-uppercase">Unsettled Commission</span>
                <span class="metric-icon" style="color: #fbbf24; border-color: rgba(245,158,11,0.2); background: rgba(245,158,11,0.1);"><i class="fa-solid fa-scale-unbalanced"></i></span>
            </div>
            <h3 class="fw-bold text-warning mb-1"><?= CURRENCY ?><?= number_format($pending_comm, 2) ?></h3>
            <span class="text-secondary small">Paid: <?= CURRENCY ?><?= number_format($paid_comm, 2) ?></span>
        </div>
    </div>
</div>

<!-- Metrics row 2 -->
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="glass-card p-3 d-flex align-items-center gap-3">
            <span class="metric-icon"><i class="fa-solid fa-ticket"></i></span>
            <div>
                <span class="text-secondary small d-block">TOTAL TICKET BOOKINGS</span>
                <h5 class="fw-bold text-white mb-0"><?= number_format($total_bookings) ?> Paid</h5>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card p-3 d-flex align-items-center gap-3">
            <span class="metric-icon" style="color:#a5b4fc; border-color:rgba(165,180,252,0.2);"><i class="fa-solid fa-users"></i></span>
            <div>
                <span class="text-secondary small d-block">BUS OPERATORS REGISTERED</span>
                <h5 class="fw-bold text-white mb-0"><?= number_format($total_agents) ?> Operators</h5>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card p-3 d-flex align-items-center gap-3">
            <span class="metric-icon" style="color:#34d399; border-color:rgba(52,211,153,0.2);"><i class="fa-solid fa-user-check"></i></span>
            <div>
                <span class="text-secondary small d-block">ACTIVE BUS OPERATORS</span>
                <h5 class="fw-bold text-white mb-0"><?= number_format($active_agents) ?> Approved</h5>
            </div>
        </div>
    </div>
</div>

<!-- Charts Grid -->
<div class="row g-4 mb-5">
    <!-- Trend Chart -->
    <div class="col-lg-7">
        <div class="glass-card p-4 h-100">
            <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-chart-area text-indigo me-2"></i>Global System Sales Trend (7 Days)</h5>
            <div style="height: 300px; position: relative;">
                <canvas id="systemTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Agent Comparison Chart -->
    <div class="col-lg-5">
        <div class="glass-card p-4 h-100">
            <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-chart-bar text-pink me-2"></i>Operator Performance (Gross Sold)</h5>
            <div style="height: 300px; position: relative;">
                <canvas id="agentPerformanceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent bookings -->
<div class="glass-card p-4">
    <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-receipt text-indigo me-2"></i>System-wide Recent Bookings</h5>
    <?php if (count($recent_bookings) === 0): ?>
        <div class="text-center py-4 text-secondary small">No bookings registered in the system yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle">
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Operating Agency</th>
                        <th>Customer</th>
                        <th>Ticket Gross</th>
                        <th>Admin Commission (2%)</th>
                        <th>Agent Net</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $b): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-indigo"><?= htmlspecialchars($b['booking_reference']) ?></td>
                            <td><span class="fw-semibold text-white"><?= htmlspecialchars($b['agency_name']) ?></span></td>
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
    // 1. Global Sales Trend Chart
    var trendCtx = document.getElementById('systemTrendChart').getContext('2d');
    var trendChart = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_days) ?>,
            datasets: [
                {
                    label: 'Gross Ticket Sales (₹)',
                    data: <?= json_encode($chart_sales) ?>,
                    borderColor: '#8b5cf6',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: false,
                    pointBackgroundColor: '#8b5cf6'
                },
                {
                    label: 'Admin Commission (₹)',
                    data: <?= json_encode($chart_comm) ?>,
                    borderColor: '#f59e0b',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: false,
                    pointBackgroundColor: '#f59e0b'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#94a3b8' } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });

    // 2. Agent Performance Bar Chart
    var perfCtx = document.getElementById('agentPerformanceChart').getContext('2d');
    var perfChart = new Chart(perfCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($perf_labels) ?>,
            datasets: [{
                label: 'Gross Sold (₹)',
                data: <?= json_encode($perf_data) ?>,
                backgroundColor: 'rgba(236, 72, 153, 0.25)',
                borderColor: '#ec4899',
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#94a3b8' } },
                y: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
