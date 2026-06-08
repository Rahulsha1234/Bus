<?php
/**
 * Agent Panel: Cancellation Requests & Refund Approvals Manager
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('admin');

$page_title = __('manage_cancellations_title', "Manage Cancellations");
$admin_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle POST actions for Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error_msg = __('security_validation_failed_refresh', "Security token validation failed. Please refresh.");
    } else {
        $action = $_POST['action'];
        $request_id = intval($_POST['request_id'] ?? 0);
        $refund_val = floatval($_POST['refund_amount'] ?? 0.00);

        try {
            // Validate request belongs to this agent's buses
            $chk_stmt = $pdo->prepare("
                SELECT cr.id, cr.booking_id, cr.request_number, b.booking_reference, b.trip_id, b.total_amount, cr.status
                FROM cancellation_requests cr
                JOIN bookings b ON cr.booking_id = b.id
                JOIN trips t ON b.trip_id = t.id
                JOIN buses bu ON t.bus_id = bu.id
                WHERE cr.id = ? AND bu.admin_id = ?
                LIMIT 1
            ");
            $chk_stmt->execute([$request_id, $admin_id]);
            $request = $chk_stmt->fetch();

            if (!$request) {
                $error_msg = __('cancel_req_not_found_unauth', "Cancellation request not found or unauthorized.");
            } elseif ($request['status'] !== 'pending') {
                $error_msg = __('cancel_req_already_processed', "This request has already been processed.");
            } else {
                $pdo->beginTransaction();

                if ($action === 'approve') {
                    // 1. Update cancellation request
                    $up_stmt = $pdo->prepare("
                        UPDATE cancellation_requests 
                        SET status = 'approved', refund_amount = ?, processed_at = NOW(), processed_by = ? 
                        WHERE id = ?
                    ");
                    $up_stmt->execute([$refund_val, $admin_id, $request_id]);

                    // 2. Set booking status to 'cancelled'
                    $bk_stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
                    $bk_stmt->execute([$request['booking_id']]);

                    // 3. Fetch seats associated with this booking
                    $seats_stmt = $pdo->prepare("SELECT seat_number FROM booking_seats WHERE booking_id = ?");
                    $seats_stmt->execute([$request['booking_id']]);
                    $booked_seats = $seats_stmt->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($booked_seats)) {
                        // 4. Reset those seats to 'available' in trip_seats
                        $placeholders = implode(',', array_fill(0, count($booked_seats), '?'));
                        $rst_stmt = $pdo->prepare("
                            UPDATE trip_seats 
                            SET status = 'available', hold_expires_at = NULL, locked_by_session = NULL, locked_at = NULL
                            WHERE trip_id = ? AND seat_number IN ($placeholders)
                        ");
                        $rst_stmt->execute(array_merge([$request['trip_id']], $booked_seats));
                    }

                    // 5. Notify Customer (we'd insert into notifications or alert, let's notify the customer)
                    // Get customer ID
                    $cust_stmt = $pdo->prepare("SELECT customer_id FROM bookings WHERE id = ? LIMIT 1");
                    $cust_stmt->execute([$request['booking_id']]);
                    $customer_id = $cust_stmt->fetchColumn();
                    if ($customer_id) {
                        $notif = $pdo->prepare("
                            INSERT INTO system_notifications (user_id, user_role, message) 
                            VALUES (?, 'customer', ?)
                        ");
                    }

                    // Notify admin of refund processing
                    $notif_admin = $pdo->prepare("
                        INSERT INTO system_notifications (user_id, user_role, message) 
                        VALUES (NULL, 'admin', ?)
                    ");
                    $notif_admin->execute([__('cancellation_approved_for_booking', "Cancellation Approved for Booking ") . $request['booking_reference'] . __('refund_processed_val', ". Refund processed: ₹") . number_format($refund_val, 2)]);

                    log_activity($pdo, $admin_id, 'CANCELLATION_APPROVE', "Approved cancellation request " . $request['request_number'] . " for Booking " . $request['booking_reference'] . ". Refunded: ₹$refund_val", "pending", "approved");
                    $success_msg = __('cancel_approved_success_seats_released', "Cancellation approved successfully and seats released.");

                } elseif ($action === 'reject') {
                    // Update request to rejected
                    $up_stmt = $pdo->prepare("
                        UPDATE cancellation_requests 
                        SET status = 'rejected', processed_at = NOW(), processed_by = ? 
                        WHERE id = ?
                    ");
                    $up_stmt->execute([$admin_id, $request_id]);

                    log_activity($pdo, $admin_id, 'CANCELLATION_REJECT', "Rejected cancellation request " . $request['request_number'] . " for Booking " . $request['booking_reference'], "pending", "rejected");
                    $success_msg = __('cancel_req_rejected_success', "Cancellation request has been rejected.");
                }

                $pdo->commit();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = __('error_processing_request', "Error processing request: ") . $e->getMessage();
        }
    }
}

// Fetch all cancellation requests for the agent's bookings
try {
    $list_stmt = $pdo->prepare("
        SELECT 
            cr.id AS request_id,
            cr.request_number,
            cr.refund_amount,
            cr.status AS request_status,
            cr.created_at AS requested_at,
            b.booking_reference,
            b.total_amount,
            b.customer_name,
            b.customer_phone,
            bs.bus_name,
            t.departure_time,
            r.source,
            r.destination
        FROM cancellation_requests cr
        JOIN bookings b ON cr.booking_id = b.id
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        WHERE bs.admin_id = ?
        ORDER BY cr.created_at DESC
    ");
    $list_stmt->execute([$admin_id]);
    $requests = $list_stmt->fetchAll();

} catch (PDOException $e) {
    die(__('database_query_error', "Database query error: ") . $e->getMessage());
}

require_once __DIR__ . '/header.php';
?>

<div class="glass-card p-4" style="border-radius: 20px;">
    <div class="mb-4">
        <h4 class="fw-bold text-white"><i class="fa-solid fa-ban text-indigo me-2"></i><?= __('cancellation_refund_requests_hdr', 'Cancellation & Refund Requests') ?></h4>
        <p class="text-secondary small"><?= __('cancellation_refund_requests_subtitle', 'Approve or reject customer refund/cancellation claims for bookings made on your fleet.') ?></p>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-3 mb-4"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-3 mb-4"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if (empty($requests)): ?>
        <div class="text-center py-5">
            <span class="text-secondary" style="font-size: 3rem;"><i class="fa-solid fa-ban"></i></span>
            <h5 class="text-white mt-3 fw-bold"><?= __('no_cancellation_requests_found', 'No Cancellation Requests Found') ?></h5>
            <p class="text-secondary small"><?= __('no_cancellation_requests_desc', 'Customers have not requested any cancellations yet.') ?></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover align-middle datatable-swift" style="background: transparent;">
                <thead>
                    <tr class="border-bottom border-secondary border-opacity-25 text-secondary small">
                        <th><?= __('request_id_col', 'Request ID') ?></th>
                        <th><?= __('booking_passenger_col', 'Booking / Passenger') ?></th>
                        <th><?= __('voyage_schedule_col', 'Voyage / Schedule') ?></th>
                        <th><?= __('ticket_price_col', 'Ticket Price') ?></th>
                        <th><?= __('status_col', 'Status') ?></th>
                        <th class="text-end"><?= __('actions_col', 'Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr class="border-bottom border-secondary border-opacity-15">
                            <td>
                                <span class="font-monospace fw-bold text-indigo" style="color: #818cf8;"><?= htmlspecialchars($r['request_number']) ?></span>
                                <div class="text-secondary small" style="font-size: 0.75rem;"><?= __('filed_at_label', 'Filed:') ?> <?= date('d M Y, H:i', strtotime($r['requested_at'])) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-white"><?= htmlspecialchars($r['customer_name']) ?></div>
                                <span class="font-monospace text-secondary small" style="font-size: 0.75rem;"><?= __('ref_label', 'Ref:') ?> <?= htmlspecialchars($r['booking_reference']) ?></span>
                            </td>
                            <td>
                                <div class="text-white small fw-bold"><?= htmlspecialchars($r['source']) ?> <i class="fa-solid fa-arrow-right mx-1 text-secondary" style="font-size:0.7rem;"></i> <?= htmlspecialchars($r['destination']) ?></div>
                                <div class="text-secondary small mt-1 font-monospace" style="font-size: 0.75rem;"><?= htmlspecialchars($r['bus_name']) ?> | <?= date('d M Y, H:i', strtotime($r['departure_time'])) ?></div>
                            </td>
                            <td>
                                <div class="text-white fw-bold">₹<?= number_format($r['total_amount'], 2) ?></div>
                            </td>
                            <td>
                                <?php if ($r['request_status'] === 'pending'): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25"><?= __('status_pending', 'PENDING') ?></span>
                                <?php elseif ($r['request_status'] === 'approved'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" title="<?= __('refunded_label', 'Refunded:') ?> ₹<?= $r['refund_amount'] ?>"><?= __('status_approved', 'APPROVED') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><?= __('status_rejected', 'REJECTED') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($r['request_status'] === 'pending'): ?>
                                    <div class="d-flex justify-content-end gap-2">
                                        <!-- Approve Action Button -->
                                        <button type="button" class="btn btn-success btn-sm px-3 rounded-2" data-bs-toggle="modal" data-bs-target="#approveModal<?= $r['request_id'] ?>">
                                            <?= __('approve_btn', 'Approve') ?>
                                        </button>
                                        <!-- Reject Action Form -->
                                        <form method="POST" onsubmit="return confirm('<?= __('reject_cancellation_confirm_q', 'Are you sure you want to reject this cancellation request?') ?>');" style="display:inline-block;">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger-glass btn-sm px-3 rounded-2"><?= __('reject_btn', 'Reject') ?></button>
                                        </form>
                                    </div>

                                    <!-- Approve Modal -->
                                    <div class="modal fade" id="approveModal<?= $r['request_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content glass-card text-white border-secondary border-opacity-20 shadow-2xl p-3" style="background:#111111; border-radius: 20px;">
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <div class="modal-header border-0 pb-0">
                                                        <h5 class="modal-title fw-bold"><?= __('approve_cancellation_title', 'Approve Cancellation') ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body py-4">
                                                        <p class="text-secondary small"><?= __('approve_cancellation_desc', 'Please verify and specify the refund amount to initiate payment back to customer.') ?></p>
                                                        <div class="mb-3">
                                                            <label class="form-label text-secondary small fw-semibold"><?= __('refund_amount_label', 'Refund Amount (₹)') ?></label>
                                                            <input type="number" name="refund_amount" class="form-control form-control-swift" value="<?= htmlspecialchars($r['total_amount']) ?>" min="0" max="<?= htmlspecialchars($r['total_amount']) ?>" step="0.01" required>
                                                            <div class="form-text text-secondary" style="font-size:0.75rem;"><?= __('max_limit_val', 'Max limit: ₹') ?><?= number_format($r['total_amount'], 2) ?></div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                                                        <button type="button" class="btn btn-secondary-glass rounded-3" data-bs-dismiss="modal"><?= __('cancel', 'Cancel') ?></button>
                                                        <button type="submit" class="btn btn-success px-4 rounded-3"><?= __('confirm_approval_btn', 'Confirm Approval') ?></button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                <?php else: ?>
                                    <span class="text-secondary small font-monospace"><?= __('no_actions', 'No Actions') ?></span>
                                <?php endif; ?>
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
