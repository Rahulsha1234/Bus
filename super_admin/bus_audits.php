<?php
/**
 * Bus experience verification dashboard and audit page for Super Admin
 */
require_once __DIR__ . '/header.php';
require_role('super_admin');

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf)) {
        $error_msg = "Security validation failed. Please refresh.";
    } else {
        if ($action === 'verify_bus') {
            $bus_id = intval($_POST['bus_id'] ?? 0);
            if ($bus_id > 0) {
                try {
                    $pdo->beginTransaction();
                    
                    // Insert or update verification
                    $stmt = $pdo->prepare("
                        INSERT INTO bus_verifications (bus_id, is_verified, verified_at, verified_by, notes)
                        VALUES (?, 1, NOW(), ?, 'Verified by Super Admin')
                        ON DUPLICATE KEY UPDATE is_verified = 1, verified_at = NOW(), verified_by = ?, notes = 'Verified by Super Admin'
                    ");
                    $stmt->execute([$bus_id, $_SESSION['user_id'], $_SESSION['user_id']]);
                    
                    // Log action
                    log_activity('verify_bus', "Verified bus ID: $bus_id", null, 'Verified');
                    
                    $pdo->commit();
                    $success_msg = "Bus has been verified successfully.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_msg = "Verification failed: " . $e->getMessage();
                }
            }
        } elseif ($action === 'unverify_bus') {
            $bus_id = intval($_POST['bus_id'] ?? 0);
            if ($bus_id > 0) {
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO bus_verifications (bus_id, is_verified, verified_at, verified_by, notes)
                        VALUES (?, 0, NOW(), ?, 'Rejected / Unverified by Super Admin')
                        ON DUPLICATE KEY UPDATE is_verified = 0, verified_at = NOW(), verified_by = ?, notes = 'Rejected / Unverified by Super Admin'
                    ");
                    $stmt->execute([$bus_id, $_SESSION['user_id'], $_SESSION['user_id']]);
                    
                    log_activity('unverify_bus', "Unverified bus ID: $bus_id", null, 'Unverified');
                    
                    $pdo->commit();
                    $success_msg = "Bus has been set to unverified status.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_msg = "Operation failed: " . $e->getMessage();
                }
            }
        } elseif ($action === 'bulk_verify') {
            $bus_ids = $_POST['bus_ids'] ?? [];
            if (!empty($bus_ids)) {
                try {
                    $pdo->beginTransaction();
                    foreach ($bus_ids as $bus_id) {
                        $bus_id = intval($bus_id);
                        $stmt = $pdo->prepare("
                            INSERT INTO bus_verifications (bus_id, is_verified, verified_at, verified_by, notes)
                            VALUES (?, 1, NOW(), ?, 'Bulk verified by Super Admin')
                            ON DUPLICATE KEY UPDATE is_verified = 1, verified_at = NOW(), verified_by = ?, notes = 'Bulk verified by Super Admin'
                        ");
                        $stmt->execute([$bus_id, $_SESSION['user_id'], $_SESSION['user_id']]);
                        log_activity('verify_bus', "Bulk verified bus ID: $bus_id", null, 'Verified');
                    }
                    $pdo->commit();
                    $success_msg = "Successfully verified selected buses.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_msg = "Bulk verification failed: " . $e->getMessage();
                }
            }
        } elseif ($action === 'delete_review') {
            $review_id = intval($_POST['review_id'] ?? 0);
            if ($review_id > 0) {
                try {
                    $pdo->beginTransaction();
                    
                    // Get review details for logging
                    $stmt = $pdo->prepare("SELECT * FROM bus_reviews WHERE id = ?");
                    $stmt->execute([$review_id]);
                    $review = $stmt->fetch();
                    
                    if ($review) {
                        $stmt = $pdo->prepare("DELETE FROM bus_reviews WHERE id = ?");
                        $stmt->execute([$review_id]);
                        
                        log_activity('delete_review', "Deleted review ID: $review_id on bus: {$review['bus_id']}", json_encode($review), null);
                        $success_msg = "Review deleted successfully.";
                    }
                    
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_msg = "Failed to delete review: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all buses with verification, media count, amenities count, average rating
try {
    $buses_stmt = $pdo->query("
        SELECT 
            b.id AS bus_id,
            b.bus_name,
            b.bus_number,
            b.bus_type,
            u.username AS operator_name,
            COALESCE(v.is_verified, 0) AS is_verified,
            v.verified_at,
            (SELECT COUNT(*) FROM bus_media WHERE bus_id = b.id) AS media_count,
            (SELECT COUNT(*) FROM bus_amenities WHERE bus_id = b.id) AS amenity_count,
            (SELECT COUNT(*) FROM bus_policies WHERE bus_id = b.id) AS policy_count,
            (SELECT COUNT(*) FROM bus_specifications WHERE bus_id = b.id) AS spec_count,
            (SELECT AVG(rating) FROM bus_reviews WHERE bus_id = b.id) AS avg_rating,
            (SELECT COUNT(*) FROM bus_reviews WHERE bus_id = b.id) AS review_count
        FROM buses b
        LEFT JOIN users u ON b.admin_id = u.id
        LEFT JOIN bus_verifications v ON b.id = v.bus_id
        ORDER BY b.bus_name ASC
    ");
    $buses = $buses_stmt->fetchAll();

    // Fetch reviews for moderation
    $reviews_stmt = $pdo->query("
        SELECT r.*, b.bus_name, b.bus_number, u.username AS customer_name
        FROM bus_reviews r
        JOIN buses b ON r.bus_id = b.id
        JOIN users u ON r.customer_id = u.id
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $reviews = $reviews_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database audit load failed: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-3 mb-4"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-3 mb-4"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Tabs Nav -->
    <ul class="nav nav-tabs border-secondary border-opacity-25 mb-4" id="auditTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link text-white active" id="buses-tab" data-bs-toggle="tab" data-bs-target="#buses-panel" type="button" role="tab"><i class="fa-solid fa-bus me-2"></i>Bus Verification & Media Check</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-white" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews-panel" type="button" role="tab"><i class="fa-solid fa-star me-2"></i>Reviews & Ratings Moderation</button>
        </li>
    </ul>

    <div class="tab-content" id="auditTabsContent">
        <!-- Buses Panel -->
        <div class="tab-pane fade show active" id="buses-panel" role="tabpanel">
            <form id="bulkForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="bulk_verify">

                <div class="glass-card p-4" style="border-radius: 20px;">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                        <h5 class="text-white fw-bold mb-0">Buses Fleet Quality Audit</h5>
                        <button type="submit" class="btn btn-success rounded-3"><i class="fa-solid fa-check-double me-2"></i>Bulk Verify Selected</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-swift table-dark table-hover align-middle">
                            <thead>
                                <tr class="text-secondary small border-bottom border-secondary border-opacity-25">
                                    <th width="40"><input type="checkbox" id="selectAll"></th>
                                    <th>Bus Details</th>
                                    <th>Operator</th>
                                    <th>Media Stats</th>
                                    <th>Amenities</th>
                                    <th>Reviews</th>
                                    <th>Verification</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($buses as $bus): ?>
                                    <tr class="border-bottom border-secondary border-opacity-15">
                                        <td>
                                            <input type="checkbox" name="bus_ids[]" value="<?= $bus['bus_id'] ?>" class="bus-checkbox">
                                        </td>
                                        <td>
                                            <span class="text-white fw-semibold"><?= htmlspecialchars($bus['bus_name']) ?></span>
                                            <div class="text-secondary small font-monospace"><?= htmlspecialchars($bus['bus_number']) ?> (<?= htmlspecialchars($bus['bus_type']) ?>)</div>
                                        </td>
                                        <td><span class="text-white-50"><?= htmlspecialchars($bus['operator_name'] ?: 'N/A') ?></span></td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $bus['media_count'] ?> Photos</span>
                                            <?php if ($bus['spec_count'] > 0): ?>
                                                <span class="badge bg-info">Specs</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">No Specs</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $bus['amenity_count'] ?> Amenities</span>
                                            <?php if ($bus['policy_count'] > 0): ?>
                                                <span class="badge bg-indigo">Policies</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">No Policies</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($bus['review_count'] > 0): ?>
                                                <div class="d-flex align-items-center text-warning gap-1">
                                                    <i class="fa-solid fa-star"></i>
                                                    <span class="fw-bold"><?= number_format($bus['avg_rating'], 1) ?></span>
                                                    <span class="text-secondary small">(<?= $bus['review_count'] ?>)</span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-secondary small">No ratings</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($bus['is_verified']): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="fa-solid fa-circle-check me-1"></i>Verified</span>
                                                <div class="text-secondary" style="font-size:0.7rem;"><?= date('d M Y', strtotime($bus['verified_at'])) ?></div>
                                            <?php else: ?>
                                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25"><i class="fa-solid fa-circle-exclamation me-1"></i>Unverified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group gap-2">
                                                <?php if (!$bus['is_verified']): ?>
                                                    <button type="button" class="btn btn-success-glass btn-sm verify-btn" data-id="<?= $bus['bus_id'] ?>" title="Approve & Verify Bus">
                                                        <i class="fa-solid fa-check"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-danger-glass btn-sm unverify-btn" data-id="<?= $bus['bus_id'] ?>" title="Reject & Unverify Bus">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>

        <!-- Reviews Panel -->
        <div class="tab-pane fade" id="reviews-panel" role="tabpanel">
            <div class="glass-card p-4" style="border-radius: 20px;">
                <h5 class="text-white fw-bold mb-4">Passenger Experience Reviews</h5>
                <div class="table-responsive">
                    <table class="table table-swift table-dark table-hover align-middle small">
                        <thead>
                            <tr class="text-secondary small border-bottom border-secondary border-opacity-25">
                                <th>Passenger</th>
                                <th>Bus / Vehicle</th>
                                <th>Cleanliness</th>
                                <th>Staff</th>
                                <th>Punctuality</th>
                                <th>Comfort</th>
                                <th>Safety</th>
                                <th>Comment</th>
                                <th>Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $rev): ?>
                                <tr class="border-bottom border-secondary border-opacity-15">
                                    <td>
                                        <span class="text-white fw-semibold"><?= htmlspecialchars($rev['customer_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-white"><?= htmlspecialchars($rev['bus_name']) ?></span>
                                        <div class="text-secondary font-monospace" style="font-size:0.75rem;"><?= htmlspecialchars($rev['bus_number']) ?></div>
                                    </td>
                                    <td><span class="text-warning"><i class="fa-solid fa-star me-1"></i><?= $rev['cleanliness'] ?></span></td>
                                    <td><span class="text-warning"><i class="fa-solid fa-star me-1"></i><?= $rev['staff_behaviour'] ?></span></td>
                                    <td><span class="text-warning"><i class="fa-solid fa-star me-1"></i><?= $rev['punctuality'] ?></span></td>
                                    <td><span class="text-warning"><i class="fa-solid fa-star me-1"></i><?= $rev['comfort'] ?></span></td>
                                    <td><span class="text-warning"><i class="fa-solid fa-star me-1"></i><?= $rev['safety'] ?></span></td>
                                    <td>
                                        <p class="text-white-50 mb-0" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($rev['review_text']) ?>">
                                            <?= htmlspecialchars($rev['review_text']) ?>
                                        </p>
                                    </td>
                                    <td><span class="text-secondary"><?= date('d M Y', strtotime($rev['created_at'])) ?></span></td>
                                    <td class="text-end">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this review?');">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete_review">
                                            <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                            <button type="submit" class="btn btn-danger-glass btn-sm" title="Remove / Delete Review">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
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

<!-- Dummy action handlers -->
<form id="actionForm" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
    <input type="hidden" name="action" id="actionInput">
    <input type="hidden" name="bus_id" id="busIdInput">
</form>

<script>
$(document).ready(function() {
    $('#selectAll').change(function() {
        $('.bus-checkbox').prop('checked', $(this).prop('checked'));
    });

    $('.verify-btn').click(function() {
        var id = $(this).data('id');
        $('#actionInput').val('verify_bus');
        $('#busIdInput').val(id);
        $('#actionForm').submit();
    });

    $('.unverify-btn').click(function() {
        var id = $(this).data('id');
        $('#actionInput').val('unverify_bus');
        $('#busIdInput').val(id);
        $('#actionForm').submit();
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
