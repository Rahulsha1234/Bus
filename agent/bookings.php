<?php
/**
 * Agent Portal: View agent's specific booking records
 */
require_once __DIR__ . '/header.php';

$agent_id = $_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');

try {
    $sql = "
        SELECT 
            b.booking_reference,
            b.customer_name,
            b.customer_email,
            b.customer_phone,
            b.total_amount,
            b.discount_applied,
            b.final_fare,
            b.payment_status,
            b.created_at,
            bs.bus_name,
            bs.bus_number,
            r.source,
            r.destination
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        WHERE b.agent_id = :agent_id
    ";

    $params = [':agent_id' => $agent_id];

    if (!empty($search)) {
        $sql .= " AND (b.booking_reference LIKE :search OR b.customer_name LIKE :search OR b.customer_phone LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $sql .= " ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $bookings = [];
}
?>

<!-- Search Toolbar -->
<div class="glass-card p-4 mb-4">
    <form action="" method="GET" class="row g-3 align-items-end">
        <div class="col-md-9">
            <label class="form-label text-secondary small fw-semibold">Search Bookings</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" placeholder="Search by Booking Reference, Customer Name or Phone..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-grid gap-2 d-md-flex">
                <button type="submit" class="btn btn-primary-gradient py-2 w-100"><i class="fa-solid fa-filter me-2"></i>Filter</button>
                <?php if (!empty($search)): ?>
                    <a href="bookings.php" class="btn btn-secondary-glass py-2"><i class="fa-solid fa-rotate-left"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Bookings Table Grid -->
<div class="glass-card p-4">
    <?php if (count($bookings) === 0): ?>
        <div class="text-center py-5 text-secondary small">No bookings found matching filters.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle">
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Fleet Detail</th>
                        <th>Passenger Info</th>
                        <th>Fare (Gross)</th>
                        <th>Discount Margin</th>
                        <th>Final Paid</th>
                        <th>Payment Status</th>
                        <th>Date & Time</th>
                        <th class="text-end">Ticket</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-indigo"><?= htmlspecialchars($b['booking_reference']) ?></td>
                            <td>
                                <span class="d-block fw-semibold text-white"><?= htmlspecialchars($b['bus_name']) ?></span>
                                <span class="text-secondary small"><?= htmlspecialchars($b['source']) ?> to <?= htmlspecialchars($b['destination']) ?></span>
                            </td>
                            <td>
                                <span class="d-block fw-semibold text-white"><?= htmlspecialchars($b['customer_name']) ?></span>
                                <span class="text-secondary small"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($b['customer_phone']) ?></span>
                            </td>
                            <td>₹<?= number_format($b['total_amount'] + $b['discount_applied'], 2) ?></td>
                            <td class="text-warning">₹<?= number_format($b['discount_applied'], 2) ?></td>
                            <td class="text-success fw-bold">₹<?= number_format($b['final_fare'], 2) ?></td>
                            <td>
                                <?php if ($b['payment_status'] === 'paid'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">PAID</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">FAILED</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-secondary small"><?= date('d M Y H:i', strtotime($b['created_at'])) ?></td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/ticket.php?ref=<?= $b['booking_reference'] ?>" target="_blank" class="btn btn-secondary-glass py-1 px-2 small" title="Print Ticket"><i class="fa-solid fa-print text-indigo"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
