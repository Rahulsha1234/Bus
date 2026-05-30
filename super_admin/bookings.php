<?php
/**
 * Super Admin Bookings explorer
 */
require_once __DIR__ . '/header.php';

$search = trim($_GET['search'] ?? '');

try {
    $count_sql = "
        SELECT COUNT(*) 
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        JOIN users op ON bs.admin_id = op.id
    ";
    $params = [];
    if (!empty($search)) {
        $count_sql .= " WHERE b.booking_reference LIKE :search 
                   OR b.customer_name LIKE :search 
                   OR op.username LIKE :search
                   OR b.customer_phone LIKE :search";
        $params[':search'] = "%$search%";
    }

    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = intval($count_stmt->fetchColumn());

    $limit = 10;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;
    $total_pages = ceil($total_records / $limit);

    $sql = "
        SELECT 
            b.booking_reference,
            b.customer_name,
            b.customer_phone,
            b.total_amount,
            b.admin_commission,
            b.agent_net_earning,
            b.payment_status,
            b.created_at,
            t.departure_time,
            bs.bus_name,
            op.username AS agency_name,
            r.source,
            r.destination
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        JOIN users op ON bs.admin_id = op.id
    ";

    if (!empty($search)) {
        $sql .= " WHERE b.booking_reference LIKE :search 
                   OR b.customer_name LIKE :search 
                   OR op.username LIKE :search
                   OR b.customer_phone LIKE :search";
    }

    $sql .= " ORDER BY b.created_at DESC LIMIT " . intval($limit) . " OFFSET " . intval($offset);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $bookings = [];
    $total_records = 0;
    $total_pages = 0;
    $page = 1;
    $offset = 0;
    $limit = 10;
}
?>

<!-- Search Toolbar -->
<div class="glass-card p-4 mb-4">
    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET" class="row g-3 align-items-end">
        <div class="col-md-9">
            <label class="form-label text-secondary small fw-semibold">Global Booking Search</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" placeholder="Search reference, customer name, mobile, or agency name..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-grid gap-2 d-md-flex">
                <button type="submit" class="btn btn-primary-gradient py-2 w-100"><i class="fa-solid fa-filter me-2"></i>Filter</button>
                <a href="<?= BASE_URL ?>/generate_report.php?type=booking" target="_blank" class="btn btn-secondary-glass py-2" title="Export PDF Report"><i class="fa-solid fa-file-pdf"></i></a>
                <?php if (!empty($search)): ?>
                    <a href="<?= BASE_URL ?>/super_admin/bookings.php" class="btn btn-secondary-glass py-2"><i class="fa-solid fa-rotate-left"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Bookings Grid Table -->
<div class="glass-card p-4">
    <?php if (count($bookings) === 0): ?>
        <div class="text-center py-5 text-secondary small">No system-wide bookings found matching filters.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle datatable-swift">
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Operating Agency</th>
                        <th>Fleet Details</th>
                        <th>Customer / Mobile</th>
                        <th>Gross Value</th>
                        <th>Admin Fee (2%)</th>
                        <th>Agent Net</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-indigo"><?= htmlspecialchars($b['booking_reference']) ?></td>
                            <td><span class="fw-semibold text-white"><?= htmlspecialchars($b['agency_name']) ?></span></td>
                            <td>
                                <span class="d-block fw-semibold text-white small"><?= htmlspecialchars($b['bus_name']) ?></span>
                                <span class="text-secondary small font-monospace" style="font-size:0.75rem;"><?= htmlspecialchars($b['source']) ?> to <?= htmlspecialchars($b['destination']) ?></span>
                            </td>
                            <td>
                                <span class="d-block fw-semibold text-white"><?= htmlspecialchars($b['customer_name']) ?></span>
                                <span class="text-secondary small font-monospace" style="font-size:0.75rem;"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($b['customer_phone']) ?></span>
                            </td>
                            <td>₹<?= number_format($b['total_amount'], 2) ?></td>
                            <td class="text-warning">₹<?= number_format($b['admin_commission'], 2) ?></td>
                            <td class="text-success fw-bold">₹<?= number_format($b['agent_net_earning'], 2) ?></td>
                            <td class="text-secondary small"><?= date('d M Y H:i', strtotime($b['created_at'])) ?></td>
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
