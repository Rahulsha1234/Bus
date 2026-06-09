<?php
/**
 * Agent Bookings View Panel
 */
require_once __DIR__ . '/header.php';

$admin_id = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');

try {
    $count_sql = "
        SELECT COUNT(*) 
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        WHERE bs.admin_id = :admin_id
    ";
    $params = [':admin_id' => $admin_id];

    if (!empty($search)) {
        $count_sql .= " AND (b.booking_reference LIKE :search OR b.customer_name LIKE :search OR b.customer_phone LIKE :search)";
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
            b.customer_email,
            b.customer_phone,
            b.total_amount,
            b.admin_commission,
            b.agent_net_earning,
            b.payment_status,
            b.created_at,
            b.booking_source,
            bs.bus_name,
            bs.bus_number,
            r.source,
            r.destination,
            u.username AS agent_username
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        LEFT JOIN users u ON b.agent_id = u.id
        WHERE bs.admin_id = :admin_id
    ";

    if (!empty($search)) {
        $sql .= " AND (b.booking_reference LIKE :search OR b.customer_name LIKE :search OR b.customer_phone LIKE :search)";
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
            <label class="form-label text-secondary small fw-semibold"><?= __('search_bookings_lbl', 'Search Bookings') ?></label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" placeholder="<?= __('search_bookings_placeholder', 'Search by Booking Reference, Customer Name or Phone...') ?>" value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-grid gap-2 d-md-flex">
                <button type="submit" class="btn btn-primary-gradient py-2 w-100"><i class="fa-solid fa-filter me-2"></i><?= __('filter_btn', 'Filter') ?></button>
                <a href="<?= BASE_URL ?>/generate_report.php?type=booking" target="_blank" class="btn btn-secondary-glass py-2" title="<?= __('export_pdf_report_btn', 'Export PDF Report') ?>"><i class="fa-solid fa-file-pdf"></i></a>
                <?php if (!empty($search)): ?>
                    <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn btn-secondary-glass py-2"><i class="fa-solid fa-rotate-left"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Bookings Table Grid -->
<div class="glass-card p-4">
    <?php if (count($bookings) === 0): ?>
        <div class="text-center py-5 text-secondary small"><?= __('no_bookings_matched_filter', 'No bookings matched your search filter criteria.') ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle datatable-swift">
                <thead>
                    <tr>
                        <th><?= __('booking_ref_col', 'Booking Ref') ?></th>
                        <th><?= __('fleet_detail_col', 'Fleet Detail') ?></th>
                        <th><?= __('customer_contact_col', 'Customer / Contact') ?></th>
                        <th><?= __('sold_price_col', 'Sold Price') ?></th>
                        <th><?= __('admin_fee_col', 'Admin Fee (2%)') ?></th>
                        <th><?= __('net_yield_col', 'Net Yield') ?></th>
                        <th><?= __('payment_status_col', 'Payment Status') ?></th>
                        <th><?= __('date_time_col', 'Date & Time') ?></th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td>
                                <span class="font-monospace fw-bold text-indigo d-block"><?= htmlspecialchars($b['booking_reference']) ?></span>
                                <?php if ($b['booking_source'] === 'agent' && !empty($b['agent_username'])): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-0.5 small mt-1 font-sans" style="font-size:0.7rem; font-family:var(--bs-font-sans-serif);">
                                        <i class="fa-solid fa-user-tie me-1"></i>Agent: <?= htmlspecialchars($b['agent_username']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-0.5 small mt-1 font-sans" style="font-size:0.7rem; font-family:var(--bs-font-sans-serif);">
                                        <i class="fa-solid fa-user me-1"></i>Direct
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="d-block fw-semibold text-white"><?= htmlspecialchars($b['bus_name']) ?></span>
                                <span class="text-secondary small"><?= htmlspecialchars($b['source']) ?> <?= __('to_label', 'to') ?> <?= htmlspecialchars($b['destination']) ?></span>
                            </td>
                            <td>
                                <span class="d-block fw-semibold text-white"><?= htmlspecialchars($b['customer_name']) ?></span>
                                <span class="text-secondary small"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($b['customer_phone']) ?></span>
                            </td>
                            <td>₹<?= number_format($b['total_amount'], 2) ?></td>
                            <td class="text-warning">₹<?= number_format($b['admin_commission'], 2) ?></td>
                            <td class="text-success fw-bold">₹<?= number_format($b['agent_net_earning'], 2) ?></td>
                            <td>
                                <?php if ($b['payment_status'] === 'paid'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><?= __('status_paid', 'PAID') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><?= __('status_failed', 'FAILED') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-secondary small"><?= date('d M Y H:i', strtotime($b['created_at'])) ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>/ticket_pdf.php?ref=<?= urlencode($b['booking_reference']) ?>&view=customer" target="_blank" class="btn btn-secondary-glass btn-sm py-1 px-2 rounded-3 text-indigo" title="Preview PDF E-Ticket">
                                    <i class="fa-solid fa-file-pdf me-1"></i>Preview
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-secondary small">
                    <?= __('showing_label', 'Showing ') ?><?= $offset + 1 ?><?= __('to_mid_label', ' to ') ?><?= min($total_records, $offset + $limit) ?><?= __('of_mid_label', ' of ') ?><?= $total_records ?><?= __('entries_label', ' entries') ?>
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
