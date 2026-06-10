<?php
/**
 * Customer Review Submission System
 */
require_once __DIR__ . '/includes/auth_middleware.php';

if (!is_logged_in()) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$customer_id = $_SESSION['user_id'];
$booking_id = intval($_GET['booking_id'] ?? 0);

// Fetch and verify booking ownership and trip completion
try {
    $stmt = $pdo->prepare("
        SELECT b.*, t.bus_id, t.departure_time, bus.bus_name
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bus ON t.bus_id = bus.id
        WHERE b.id = ? AND b.customer_id = ? AND b.payment_status = 'paid' AND b.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$booking_id, $customer_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        die("Invalid booking reference or review not authorized.");
    }

    // Verify trip is completed (departure has passed or trip status is COMPLETED)
    $dep_time = strtotime($booking['departure_time']);
    if ($dep_time > time()) {
        die("You can only review a bus after the trip has departed and completed.");
    }

    // Check if review already submitted
    $chk = $pdo->prepare("SELECT id FROM bus_reviews WHERE booking_id = ? LIMIT 1");
    $chk->execute([$booking_id]);
    if ($chk->fetchColumn()) {
        $_SESSION['success_msg'] = "You have already submitted a review for this booking.";
        header("Location: " . BASE_URL . "/bookings.php");
        exit();
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = "Security validation failed. Please refresh.";
    } else {
        $cleanliness = intval($_POST['cleanliness'] ?? 5);
        $staff = intval($_POST['staff_behaviour'] ?? 5);
        $punctuality = intval($_POST['punctuality'] ?? 5);
        $comfort = intval($_POST['comfort'] ?? 5);
        $safety = intval($_POST['safety'] ?? 5);
        $text = trim($_POST['review_text'] ?? '');

        // Calculate average rating
        $rating = ($cleanliness + $staff + $punctuality + $comfort + $safety) / 5.0;

        if ($cleanliness < 1 || $cleanliness > 5 || $staff < 1 || $staff > 5 || $punctuality < 1 || $punctuality > 5 || $comfort < 1 || $comfort > 5 || $safety < 1 || $safety > 5) {
            $error = "Ratings must be between 1 and 5 stars.";
        } else {
            try {
                $ins_stmt = $pdo->prepare("
                    INSERT INTO bus_reviews (booking_id, customer_id, bus_id, cleanliness, staff_behaviour, punctuality, comfort, safety, rating, review_text, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')
                ");
                $ins_stmt->execute([
                    $booking_id,
                    $customer_id,
                    $booking['bus_id'],
                    $cleanliness,
                    $staff,
                    $punctuality,
                    $comfort,
                    $safety,
                    $rating,
                    $text
                ]);

                $_SESSION['success_msg'] = "Thank you! Your review has been submitted successfully.";
                header("Location: " . BASE_URL . "/bookings.php");
                exit();
            } catch (PDOException $e) {
                $error = "Failed to save review: " . $e->getMessage();
            }
        }
    }
}

$page_title = "Submit Bus Review";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="glass-card p-5 text-white" style="border-radius: 20px;">
                <h3 class="fw-bold mb-1"><i class="fa-solid fa-star text-warning me-2"></i>Rate Your Journey</h3>
                <p class="text-secondary small mb-4">Please share your experience for trip on <strong><?= htmlspecialchars($booking['bus_name']) ?></strong></p>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-3 mb-4"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

                    <!-- Cleanliness -->
                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-semibold">Cleanliness</label>
                        <div class="rating-stars" data-name="cleanliness">
                            <input type="hidden" name="cleanliness" id="cleanliness_val" value="5">
                            <div class="d-flex gap-2 fs-4 text-warning">
                                <i class="fa-solid fa-star star-btn active" data-val="1"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="2"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="3"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="4"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Behaviour -->
                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-semibold">Staff Behaviour</label>
                        <div class="rating-stars" data-name="staff_behaviour">
                            <input type="hidden" name="staff_behaviour" id="staff_behaviour_val" value="5">
                            <div class="d-flex gap-2 fs-4 text-warning">
                                <i class="fa-solid fa-star star-btn active" data-val="1"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="2"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="3"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="4"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Punctuality -->
                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-semibold">Punctuality</label>
                        <div class="rating-stars" data-name="punctuality">
                            <input type="hidden" name="punctuality" id="punctuality_val" value="5">
                            <div class="d-flex gap-2 fs-4 text-warning">
                                <i class="fa-solid fa-star star-btn active" data-val="1"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="2"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="3"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="4"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Comfort -->
                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-semibold">Comfort</label>
                        <div class="rating-stars" data-name="comfort">
                            <input type="hidden" name="comfort" id="comfort_val" value="5">
                            <div class="d-flex gap-2 fs-4 text-warning">
                                <i class="fa-solid fa-star star-btn active" data-val="1"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="2"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="3"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="4"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Safety -->
                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-semibold">Safety</label>
                        <div class="rating-stars" data-name="safety">
                            <input type="hidden" name="safety" id="safety_val" value="5">
                            <div class="d-flex gap-2 fs-4 text-warning">
                                <i class="fa-solid fa-star star-btn active" data-val="1"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="2"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="3"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="4"></i>
                                <i class="fa-solid fa-star star-btn active" data-val="5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Review Comment -->
                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-semibold">Comments / Review Text</label>
                        <textarea name="review_text" class="form-control form-control-swift" rows="4" placeholder="Share specific details about the bus cleanliness, staff behaviour, comfort, and safety..."></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="bookings.php" class="btn btn-secondary-glass rounded-3">Cancel</a>
                        <button type="submit" class="btn btn-primary-gradient px-5 rounded-3">Submit Review</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.star-btn').click(function() {
        var star = $(this);
        var container = star.closest('.rating-stars');
        var val = star.data('val');
        
        container.find('#' + container.data('name') + '_val').val(val);
        
        container.find('.star-btn').each(function() {
            var s = $(this);
            if (s.data('val') <= val) {
                s.addClass('active').removeClass('fa-regular').addClass('fa-solid');
            } else {
                s.removeClass('active').removeClass('fa-solid').addClass('fa-regular');
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
