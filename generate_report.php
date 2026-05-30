<?php
/**
 * SwiftBus Centralized PDF/Print Report Generator
 */
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/pdf_template.php';

// Force role protection for admins
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
    die("Access Denied: Administrative access required.");
}

$curr_user_id = intval($_SESSION['user_id']);
$role = $_SESSION['user_role'];

$type = $_GET['type'] ?? 'booking'; // Options: booking, settlement, revenue, commission
$report_period = $_GET['period'] ?? 'All Time';

try {
    $title = "SwiftBus Report";
    $subtitle = "";
    
    // Summary metrics placeholders
    $summary_count = 0;
    $summary_revenue = 0.00;
    $summary_commission = 0.00;
    
    $headers = [];
    $rows = [];
    
    if ($type === 'settlement') {
        $title = "Settlement Report";
        $subtitle = "Weekly Settlement History Log";
        
        if ($role === 'super_admin') {
            $stmt = $pdo->prepare("
                SELECT ws.*, u.username 
                FROM weekly_settlements ws
                JOIN users u ON ws.agent_id = u.id
                ORDER BY ws.week_end DESC
            ");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM weekly_settlements 
                WHERE agent_id = ? 
                ORDER BY week_end DESC
            ");
            $stmt->execute([$curr_user_id]);
        }
        $data = $stmt->fetchAll();
        
        $headers = $role === 'super_admin' ? ['Operator', 'Cycle Period', 'Total Sales', 'Commission Due', 'Status'] : ['Cycle Period', 'Total Sales', 'Commission Due', 'Status'];
        
        foreach ($data as $row) {
            $cycle = date('d M Y', strtotime($row['week_start'])) . ' to ' . date('d M Y', strtotime($row['week_end']));
            $sales = '₹' . number_format($row['total_sales'], 2);
            $comm = '₹' . number_format($row['commission_payable'], 2);
            $status = strtoupper($row['status']);
            
            $summary_count++;
            $summary_revenue += floatval($row['total_sales']);
            $summary_commission += floatval($row['commission_payable']);
            
            if ($role === 'super_admin') {
                $rows[] = [$row['username'], $cycle, $sales, $comm, $status];
            } else {
                $rows[] = [$cycle, $sales, $comm, $status];
            }
        }
        
    } elseif ($type === 'revenue' || $type === 'booking' || $type === 'commission') {
        if ($type === 'revenue') {
            $title = "Revenue Performance Report";
        } elseif ($type === 'commission') {
            $title = "Commission Summary Report";
        } else {
            $title = "Booking Transactions Report";
        }
        
        if ($role === 'super_admin') {
            $stmt = $pdo->prepare("
                SELECT b.*, t.departure_time, r.source, r.destination 
                FROM bookings b
                JOIN trips t ON b.trip_id = t.id
                JOIN routes r ON t.route_id = r.id
                WHERE b.payment_status = 'paid'
                ORDER BY b.created_at DESC
            ");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                SELECT b.*, t.departure_time, r.source, r.destination 
                FROM bookings b
                JOIN trips t ON b.trip_id = t.id
                JOIN buses bs ON t.bus_id = bs.id
                JOIN routes r ON t.route_id = r.id
                WHERE bs.admin_id = ? AND b.payment_status = 'paid'
                ORDER BY b.created_at DESC
            ");
            $stmt->execute([$curr_user_id]);
        }
        $data = $stmt->fetchAll();
        
        $headers = ['Date', 'Booking Reference', 'Route Voyage', 'Total Paid', 'Commission'];
        
        foreach ($data as $row) {
            $date = date('d M Y, H:i', strtotime($row['created_at']));
            $ref = $row['booking_reference'];
            $voyage = htmlspecialchars($row['source']) . ' ➔ ' . htmlspecialchars($row['destination']);
            $paid = '₹' . number_format($row['total_amount'], 2);
            $comm = '₹' . number_format($row['admin_commission'], 2);
            
            $summary_count++;
            $summary_revenue += floatval($row['total_amount']);
            $summary_commission += floatval($row['admin_commission']);
            
            $rows[] = [$date, $ref, $voyage, $paid, $comm];
        }
    }
    
} catch (Exception $e) {
    die("Report Generation Error: " . $e->getMessage());
}

// Render Standard PDF Document Template
render_pdf_head($title);
// Tool back button links to dashboards
$back_link = $role === 'super_admin' ? 'super_admin/dashboard.php' : 'admin/dashboard.php';
render_pdf_toolbar($back_link);
?>

<div class="pdf-container">
    <?php render_pdf_header($title, "Report Period", $report_period); ?>
    
    <!-- Report Metadata Details Card -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="pdf-info-card">
                <div class="info-label">Created By</div>
                <div class="info-value text-capitalize"><?= htmlspecialchars($_SESSION['user_role']) ?> Workspace</div>
            </div>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="pdf-info-card">
                <div class="info-label">Generated Timestamp</div>
                <div class="info-value"><?= date('d M Y, H:i:s') ?></div>
            </div>
        </div>
    </div>

    <!-- Summary Metrics - always 3 columns, print-safe -->
    <?php render_pdf_section_title("Summary Statistics", "fa-solid fa-chart-pie"); ?>
    <div class="stats-row">
        <div class="stat-card" style="border-top: 3px solid var(--pdf-primary);">
            <div class="info-label">Total Records</div>
            <div class="fs-4 fw-bold text-success mt-1"><?= $summary_count ?></div>
        </div>
        <div class="stat-card" style="border-top: 3px solid var(--pdf-gold);">
            <div class="info-label">Total Gross Sales</div>
            <div class="fs-4 fw-bold text-dark mt-1">₹<?= number_format($summary_revenue, 2) ?></div>
        </div>
        <div class="stat-card" style="border-top: 3px solid #db2777;">
            <div class="info-label">Admin Commission (2%)</div>
            <div class="fs-4 fw-bold mt-1" style="color: #4f46e5;">₹<?= number_format($summary_commission, 2) ?></div>
        </div>
    </div>


    <!-- Data Table component -->
    <?php render_pdf_section_title("Report Records / Log", "fa-solid fa-list-check"); ?>
    <table class="pdf-table">
        <thead>
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= count($headers) ?>" class="text-center py-4 text-muted">No report transaction entries found for the selected period.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= $cell ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php render_pdf_footer(); ?>
</div>
