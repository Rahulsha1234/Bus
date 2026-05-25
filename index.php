<?php
/**
 * Customer Homepage - Search Buses
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$page_title = "Book Bus Tickets";

// Fetch unique sources from active routes only
try {
    $sources_stmt = $pdo->query("SELECT DISTINCT source FROM routes WHERE status = 'active' ORDER BY source ASC");
    $sources = $sources_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $sources = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner Section -->
<div class="row align-items-center mb-5 mt-4 py-4">
    <div class="col-lg-7 text-center text-lg-start mb-5 mb-lg-0">
        <h1 class="display-3 fw-bold text-white mb-3" style="line-height: 1.1;">
            Travel In <span class="text-gradient">Premium Comfort</span>
        </h1>
        <p class="lead text-secondary mb-4" style="max-width: 600px;">
            Book tickets across the largest network of premium luxury AC sleepers, seaters, and semi-sleepers. Zero booking fees, secure payments, and instant seat confirmation.
        </p>
        <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-4 text-secondary small">
            <div><i class="fa-solid fa-circle-check text-indigo me-2"></i>30,000+ Daily Trips</div>
            <div><i class="fa-solid fa-circle-check text-indigo me-2"></i>Verified Travel Agents</div>
            <div><i class="fa-solid fa-circle-check text-indigo me-2"></i>24/7 Premium Support</div>
        </div>
    </div>
    
    <!-- Hero Bus Mockup Image generated using CSS/Icons -->
    <div class="col-lg-5 text-center">
        <div class="position-relative d-inline-block">
            <div class="glass-card p-5 d-flex align-items-center justify-content-center" style="width: 320px; height: 320px; border-radius: 50%; background: linear-gradient(135deg, rgba(99,102,241,0.15) 0%, rgba(236,72,153,0.15) 100%);">
                <i class="fa-solid fa-bus-simple text-gradient-purple" style="font-size: 8rem; filter: drop-shadow(0 15px 25px rgba(99,102,241,0.4));"></i>
            </div>
            <!-- absolute indicators -->
            <div class="glass-card py-2 px-3 position-absolute top-10 start-0 d-flex align-items-center gap-2 small animate-bounce" style="border-radius: 30px; animation: float 3s ease-in-out infinite;">
                <i class="fa-solid fa-shield text-success"></i> Secure Booking
            </div>
            <div class="glass-card py-2 px-3 position-absolute bottom-10 end-0 d-flex align-items-center gap-2 small" style="border-radius: 30px; animation: float 4s ease-in-out infinite 1s;">
                <i class="fa-solid fa-star text-warning"></i> 4.8 Rating
            </div>
        </div>
    </div>
</div>

<!-- Search Panel Section -->
<div class="row justify-content-center mb-5">
    <div class="col-lg-11">
        <div class="glass-card p-5" style="border-radius: 24px; box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.6);">
            <h3 class="fw-bold mb-4 text-white"><i class="fa-solid fa-magnifying-glass me-2 text-indigo"></i>Find Your Destination</h3>
            
            <form action="<?= BASE_URL ?>/search.php" method="GET" class="row g-4 align-items-end">
                
                <!-- Source dropdown -->
                <div class="col-md-3">
                    <label for="source" class="form-label text-secondary small fw-semibold">Leaving From</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-location-dot"></i></span>
                        <select name="source" id="source" class="form-select form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" required>
                            <option value="">Select Origin...</option>
                            <?php foreach ($sources as $src): ?>
                                <option value="<?= htmlspecialchars($src) ?>"><?= htmlspecialchars($src) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Swap Button -->
                <div class="col-md-1 text-center mb-2 d-none d-md-block">
                    <button type="button" id="swapCities" class="btn btn-secondary-glass p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-right-left"></i>
                    </button>
                </div>

                <!-- Destination dropdown (populated dynamically) -->
                <div class="col-md-3">
                    <label for="destination" class="form-label text-secondary small fw-semibold">Going To</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-location-crosshairs"></i></span>
                        <select name="destination" id="destination" class="form-select form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" required disabled>
                            <option value="">Select Origin first...</option>
                        </select>
                    </div>
                    <div id="dest-loading" class="small text-muted mt-1" style="display:none;"><i class="fa-solid fa-spinner fa-spin me-1"></i>Loading routes...</div>
                    <div id="dest-empty" class="small text-warning mt-1" style="display:none;"><i class="fa-solid fa-triangle-exclamation me-1"></i>No routes from this origin yet.</div>
                </div>

                <!-- Date Picker -->
                <div class="col-md-3">
                    <label for="date" class="form-label text-secondary small fw-semibold">Travel Date</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-calendar-days"></i></span>
                        <input type="date" name="date" id="date" class="form-control form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                    </div>
                </div>

                <!-- Search Button -->
                <div class="col-md-2">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary-gradient py-3 text-uppercase fw-bold" style="letter-spacing: 0.5px;">Search Buses</button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- Extra styling for floating icons -->
<style>
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
</style>

<script>
$(document).ready(function() {

    // Dynamically load destinations when source changes
    $('#source').on('change', function() {
        var source = $(this).val();
        var $dest = $('#destination');
        var $loading = $('#dest-loading');
        var $empty = $('#dest-empty');

        // Reset
        $dest.prop('disabled', true).html('<option value="">Select Destination...</option>');
        $loading.hide();
        $empty.hide();

        if (!source) {
            $dest.html('<option value="">Select Origin first...</option>');
            return;
        }

        $loading.show();

        $.getJSON('<?= BASE_URL ?>/ajax/get_destinations.php', { source: source }, function(data) {
            $loading.hide();
            $dest.html('<option value="">Select Destination...</option>');

            if (data.length === 0) {
                $empty.show();
                return;
            }

            $.each(data, function(i, dest) {
                $dest.append($('<option>', { value: dest, text: dest }));
            });

            $dest.prop('disabled', false);
        }).fail(function() {
            $loading.hide();
            $dest.html('<option value="">Error loading routes</option>');
        });
    });

    // City Swapper - swap source <-> destination and reload destinations
    $('#swapCities').on('click', function() {
        var srcVal = $('#source').val();
        var destVal = $('#destination').val();

        // Only swap if destination is valid
        if (!destVal) return;

        // Set source to old destination value
        $('#source').val(destVal).trigger('change');

        // After AJAX loads, set destination to old source value
        // Use a small delay to wait for AJAX
        setTimeout(function() {
            $('#destination').val(srcVal);
        }, 600);
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
