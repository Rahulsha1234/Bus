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

</div> <!-- Close the header container to make the hero section true full-width -->

<!-- Hero Banner Section with Video Background & Integrated Search Panel (Full Width) -->
<div class="position-relative overflow-hidden hero-video-section shadow-lg" style="height: 90vh; min-height: 600px; background-color: #0c2016; margin-top: -5rem;">
    <!-- Background Video -->
    <video autoplay muted loop playsinline class="position-absolute w-100 h-100 object-fit-cover top-0 start-0 z-0" style="opacity: 0.5; filter: brightness(0.75);">
        <source src="https://storage.googleapis.com/gtv-videos-bucket/sample/SubaruOutbackOnStreetAndDirt.mp4" type="video/mp4">
        <source src="https://assets.mixkit.co/videos/preview/mixkit-cars-on-a-highway-at-sunset-40068-large.mp4" type="video/mp4">
        <source src="https://vjs.zencdn.net/v/oceans.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>
    
    <!-- Hero Content Overlay -->
    <div class="position-relative z-1 container px-4 d-flex flex-column justify-content-center align-items-center text-center" style="height: 100%; min-height: 600px; padding-top: 5rem;">
        <h1 class="display-4 fw-bold text-white-fixed mb-3" style="line-height: 1.1; font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -1px; max-width: 800px; color: #ffffff !important;">
            Travel Across India<br><span style="background: linear-gradient(135deg, #2ecc71, #198754); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Comfortably & Reliably</span>
        </h1>
        <p class="lead text-white-fixed mb-4" style="max-width: 600px; font-weight: 400; opacity: 0.9; color: rgba(255, 255, 255, 0.9) !important;">
            Book buses, choose seats, track bookings, and travel with confidence.
        </p>
        
        <div class="d-flex flex-wrap gap-3 mb-3 justify-content-center">
            <a href="#search-panel" class="btn btn-primary-gradient px-4 py-3 text-uppercase fw-bold text-white-fixed" style="font-size: 0.9rem; color: #ffffff !important;">Search Buses</a>
            <a href="#trust-section" class="btn btn-secondary-glass px-4 py-3 text-uppercase fw-bold" style="font-size: 0.9rem; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); color: #ffffff !important; border-color: rgba(255,255,255,0.4) !important;">View Features</a>
        </div>
    </div>
</div>

<div class="container position-relative" style="margin-top: 0 !important; z-index: 10;"> <!-- Re-open the container for the rest of the page contents -->
    <!-- Integrated Search Panel (Overlaps Hero by Half) -->
    <div id="search-panel" class="w-100" style="max-width: 950px; margin: -110px auto 50px auto;">
        <div class="bg-white text-dark p-4 shadow-lg rounded-4 text-start border border-light" style="background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px);">
            <h5 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2" style="font-family: 'Plus Jakarta Sans', sans-serif;">
                <i class="fa-solid fa-magnifying-glass text-success"></i> Find Your Destination
            </h5>
            <form action="<?= BASE_URL ?>/search.php" method="GET" class="row g-3 align-items-end">
                <!-- Source dropdown -->
                <div class="col-md-3">
                    <label for="source" class="form-label text-secondary small fw-bold">Leaving From</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-location-dot"></i></span>
                        <select name="source" id="source" class="form-select border-start-0 bg-light" style="border-radius: 0 12px 12px 0; padding: 0.75rem;" required>
                            <option value="">Select Origin...</option>
                            <?php foreach ($sources as $src): ?>
                                <option value="<?= htmlspecialchars($src) ?>"><?= htmlspecialchars($src) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Swap Button -->
                <div class="col-md-1 text-center mb-2 d-none d-md-block">
                    <button type="button" id="swapCities" class="btn btn-light p-3 rounded-circle border" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="fa-solid fa-right-left text-success"></i>
                    </button>
                </div>

                <!-- Destination dropdown -->
                <div class="col-md-3">
                    <label for="destination" class="form-label text-secondary small fw-bold">Going To</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-location-crosshairs"></i></span>
                        <select name="destination" id="destination" class="form-select border-start-0 bg-light" style="border-radius: 0 12px 12px 0; padding: 0.75rem;" required disabled>
                            <option value="">Select Origin first...</option>
                        </select>
                    </div>
                    <div id="dest-loading" class="small text-muted mt-1" style="display:none;"><i class="fa-solid fa-spinner fa-spin me-1"></i>Loading...</div>
                    <div id="dest-empty" class="small text-warning mt-1" style="display:none;"><i class="fa-solid fa-triangle-exclamation me-1"></i>No routes.</div>
                </div>

                <!-- Date Picker -->
                <div class="col-md-3">
                    <label for="date" class="form-label text-secondary small fw-bold">Travel Date</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-calendar-days"></i></span>
                        <input type="date" name="date" id="date" class="form-control border-start-0 bg-light" style="border-radius: 0 12px 12px 0; padding: 0.75rem;" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                    </div>
                </div>

                <!-- Search Button -->
                <div class="col-md-2">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary-gradient py-3 text-uppercase fw-bold text-white-fixed" style="letter-spacing: 0.5px; color: #ffffff !important;">Search</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<!-- Trust Indicators Section -->
<div id="trust-section" class="py-5 my-4">
    <div class="container text-center">
        <h2 class="fw-bold mb-5" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.5px;">Why Travelers Trust SwiftBus</h2>
        <div class="row g-4 justify-content-center">
            <div class="col-6 col-md-3">
                <div class="p-4 rounded-4 bg-light border shadow-sm">
                    <div class="mb-3 text-success fs-1">
                        <i class="fa-solid fa-ticket"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1" style="font-family: monospace;">10,000+</h3>
                    <p class="text-secondary mb-0 small fw-bold text-uppercase tracking-wider" style="font-size: 0.75rem;">Bookings Completed</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-4 rounded-4 bg-light border shadow-sm">
                    <div class="mb-3 text-success fs-1">
                        <i class="fa-solid fa-route"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1" style="font-family: monospace;">500+</h3>
                    <p class="text-secondary mb-0 small fw-bold text-uppercase tracking-wider" style="font-size: 0.75rem;">Active Routes</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-4 rounded-4 bg-light border shadow-sm">
                    <div class="mb-3 text-success fs-1">
                        <i class="fa-solid fa-face-smile"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1" style="font-family: monospace;">99%</h3>
                    <p class="text-secondary mb-0 small fw-bold text-uppercase tracking-wider" style="font-size: 0.75rem;">Satisfaction</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-4 rounded-4 bg-light border shadow-sm">
                    <div class="mb-3 text-success fs-1">
                        <i class="fa-solid fa-headset"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1" style="font-family: monospace;">24/7</h3>
                    <p class="text-secondary mb-0 small fw-bold text-uppercase tracking-wider" style="font-size: 0.75rem;">Customer Support</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scroll reveal and Mesh Blob style keyframes -->
<style>
    .reveal-on-scroll {
        opacity: 0;
        transform: translateY(40px);
        transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .reveal-on-scroll.visible {
        opacity: 1;
        transform: translateY(0);
    }
    .hover-card-premium {
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        border: 1px solid var(--border-glass) !important;
        background: var(--bg-card) !important;
    }
    .hover-card-premium:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px rgba(25, 135, 84, 0.15) !important;
        border-color: var(--accent-primary) !important;
    }
    /* Animated Mesh Gradient Blobs */
    .bg-doodles-wrapper {
        background: var(--bg-primary);
        transition: background 0.3s ease;
    }
    .mesh-blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(100px);
        opacity: 0.15;
        z-index: 0;
        pointer-events: none;
        animation: floatBlob 25s infinite alternate ease-in-out;
    }
    .mesh-blob-1 {
        width: 450px;
        height: 450px;
        background: radial-gradient(circle, #2ecc71 0%, transparent 70%);
        top: -10%;
        left: -10%;
    }
    .mesh-blob-2 {
        width: 550px;
        height: 550px;
        background: radial-gradient(circle, #198754 0%, transparent 70%);
        bottom: -15%;
        right: -10%;
        animation-delay: -7s;
        animation-duration: 30s;
    }
    .mesh-blob-3 {
        width: 350px;
        height: 350px;
        background: radial-gradient(circle, #20c997 0%, transparent 70%);
        top: 40%;
        left: 30%;
        animation-delay: -12s;
        animation-duration: 20s;
    }
    @keyframes floatBlob {
        0% {
            transform: translate(0, 0) scale(1);
        }
        50% {
            transform: translate(120px, 90px) scale(1.15) rotate(45deg);
        }
        100% {
            transform: translate(-60px, 140px) scale(0.9) rotate(90deg);
        }
    }
</style>

<!-- Popular Routes, Testimonials, and FAQs Container with Premium Mesh Gradient Background -->
<div class="position-relative overflow-hidden rounded-5 p-4 p-md-5 my-5 bg-doodles-wrapper border shadow-sm">
    <!-- Animated Color Blobs -->
    <div class="mesh-blob mesh-blob-1"></div>
    <div class="mesh-blob mesh-blob-2"></div>
    <div class="mesh-blob mesh-blob-3"></div>

    <div class="position-relative z-1">
        <!-- Popular Routes Section -->
        <div class="py-4 my-4 reveal-on-scroll">
            <div class="text-center mb-5">
                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill uppercase tracking-wider mb-2" style="font-size: 0.8rem; font-weight: 700;">Explore India</span>
                <h2 class="fw-bold display-6" style="font-family: 'Plus Jakarta Sans', sans-serif;">Popular Routes & Fares</h2>
                <p class="text-secondary mx-auto" style="max-width: 600px;">Book tickets for our most frequent and highly-rated routes at unbeatable prices.</p>
            </div>
            <div class="row g-4">
                <!-- Route 1 -->
                <div class="col-md-4">
                    <div class="p-4 rounded-4 hover-card-premium shadow-sm h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-success bg-opacity-25 text-success">Daily Service</span>
                                <span class="fw-bold text-success text-monospace" style="font-size: 1.1rem;">₹499 onwards</span>
                            </div>
                            <h5 class="fw-bold mb-1 text-dark" style="font-family: 'Plus Jakarta Sans', sans-serif;">Mumbai &harr; Pune</h5>
                            <p class="text-secondary small mb-3"><i class="fa-solid fa-clock me-1 text-success"></i> Approx. 3h 30m | AC Multi-Axle</p>
                        </div>
                        <a href="#search-panel" class="btn btn-outline-success btn-sm w-100 rounded-3 py-2 fw-bold text-uppercase">Check Seats</a>
                    </div>
                </div>
                <!-- Route 2 -->
                <div class="col-md-4">
                    <div class="p-4 rounded-4 hover-card-premium shadow-sm h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-success bg-opacity-25 text-success">High Demand</span>
                                <span class="fw-bold text-success text-monospace" style="font-size: 1.1rem;">₹799 onwards</span>
                            </div>
                            <h5 class="fw-bold mb-1 text-dark" style="font-family: 'Plus Jakarta Sans', sans-serif;">Delhi &harr; Jaipur</h5>
                            <p class="text-secondary small mb-3"><i class="fa-solid fa-clock me-1 text-success"></i> Approx. 5h 15m | Luxury Sleeper</p>
                        </div>
                        <a href="#search-panel" class="btn btn-outline-success btn-sm w-100 rounded-3 py-2 fw-bold text-uppercase">Check Seats</a>
                    </div>
                </div>
                <!-- Route 3 -->
                <div class="col-md-4">
                    <div class="p-4 rounded-4 hover-card-premium shadow-sm h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-success bg-opacity-25 text-success">Premium Route</span>
                                <span class="fw-bold text-success text-monospace" style="font-size: 1.1rem;">₹649 onwards</span>
                            </div>
                            <h5 class="fw-bold mb-1 text-dark" style="font-family: 'Plus Jakarta Sans', sans-serif;">Bangalore &harr; Chennai</h5>
                            <p class="text-secondary small mb-3"><i class="fa-solid fa-clock me-1 text-success"></i> Approx. 6h 00m | Scania Multi-Axle</p>
                        </div>
                        <a href="#search-panel" class="btn btn-outline-success btn-sm w-100 rounded-3 py-2 fw-bold text-uppercase">Check Seats</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Testimonials Section -->
        <div class="py-5 my-5 reveal-on-scroll bg-white rounded-5 p-5 border shadow-sm">
            <div class="text-center mb-5">
                <h2 class="fw-bold" style="font-family: 'Plus Jakarta Sans', sans-serif;">What Our Travelers Say</h2>
                <p class="text-secondary">Verified reviews from passengers who travel with SwiftBus regularly.</p>
            </div>
            <div class="row g-4">
                <!-- Review 1 -->
                <div class="col-md-6">
                    <div class="p-4 rounded-4 bg-light bg-opacity-50 border h-100 shadow-sm">
                        <div class="text-warning mb-2">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                        </div>
                        <p class="text-dark mb-3 italic">"Extremely clean buses and very polite drivers. The online seat selection matched the bus layout perfectly. Will book again!"</p>
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold" style="width: 42px; height: 42px;">AS</div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Aarav Sharma</h6>
                                <span class="text-secondary small">Verified Passenger</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Review 2 -->
                <div class="col-md-6">
                    <div class="p-4 rounded-4 bg-light bg-opacity-50 border h-100 shadow-sm">
                        <div class="text-warning mb-2">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                        </div>
                        <p class="text-dark mb-3 italic">"The live notification feature kept me updated. Ticket refund and cancellation is super simple compared to other portals."</p>
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold" style="width: 42px; height: 42px;">RP</div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Riya Patel</h6>
                                <span class="text-secondary small">Frequent Traveler</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Accordion Section -->
        <div class="py-4 my-4 reveal-on-scroll">
            <div class="text-center mb-5">
                <h2 class="fw-bold" style="font-family: 'Plus Jakarta Sans', sans-serif;">Frequently Asked Questions</h2>
                <p class="text-secondary">Have doubts? We've got answers to the most common queries.</p>
            </div>
            <div class="accordion accordion-flush mx-auto shadow-sm border rounded-4 overflow-hidden" id="faqAccordion" style="max-width: 800px; background: var(--bg-card);">
                <div class="accordion-item" style="background: transparent; border-bottom: 1px solid var(--border-glass);">
                    <h2 class="accordion-header" id="faq-headingOne">
                        <button class="accordion-button collapsed fw-bold text-dark py-3" style="background: transparent;" type="button" data-bs-toggle="collapse" data-bs-target="#faq-collapseOne" aria-expanded="false" aria-controls="faq-collapseOne">
                            How do I book tickets online?
                        </button>
                    </h2>
                    <div id="faq-collapseOne" class="accordion-collapse collapse" aria-labelledby="faq-headingOne" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary small">
                            Simply enter your departure city, destination, and select your travel date on the homepage. Click search, select your preferred bus service, pick your seat, fill passenger details, and finish the booking.
                        </div>
                    </div>
                </div>
                <div class="accordion-item" style="background: transparent; border-bottom: 1px solid var(--border-glass);">
                    <h2 class="accordion-header" id="faq-headingTwo">
                        <button class="accordion-button collapsed fw-bold text-dark py-3" style="background: transparent;" type="button" data-bs-toggle="collapse" data-bs-target="#faq-collapseTwo" aria-expanded="false" aria-controls="faq-collapseTwo">
                            Can I cancel or reschedule my ticket?
                        </button>
                    </h2>
                    <div id="faq-collapseTwo" class="accordion-collapse collapse" aria-labelledby="faq-headingTwo" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary small">
                            Yes, you can cancel tickets easily through the 'My Bookings' section in your dashboard. Refund policies will apply depending on how many hours are left before the scheduled departure.
                        </div>
                    </div>
                </div>
                <div class="accordion-item" style="background: transparent; border-bottom: none;">
                    <h2 class="accordion-header" id="faq-headingThree">
                        <button class="accordion-button collapsed fw-bold text-dark py-3" style="background: transparent;" type="button" data-bs-toggle="collapse" data-bs-target="#faq-collapseThree" aria-expanded="false" aria-controls="faq-collapseThree">
                            What are the payment methods accepted?
                        </button>
                    </h2>
                    <div id="faq-collapseThree" class="accordion-collapse collapse" aria-labelledby="faq-headingThree" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary small">
                            We accept all major credit cards, debit cards, UPI payments, net banking, and secure digital wallets.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Extra styling for floating icons -->
<style>
    @keyframes float {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-10px);
        }
    }
</style>

<script>
    $(document).ready(function () {
        // Intersection Observer for Scroll Animation
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('.reveal-on-scroll').forEach((el) => observer.observe(el));

        // Dynamically load destinations when source changes
        $('#source').on('change', function () {
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

            $.getJSON('<?= BASE_URL ?>/ajax/get_destinations.php', { source: source }, function (data) {
                $loading.hide();
                $dest.html('<option value="">Select Destination...</option>');

                if (data.length === 0) {
                    $empty.show();
                    return;
                }

                $.each(data, function (i, dest) {
                    $dest.append($('<option>', { value: dest, text: dest }));
                });

                $dest.prop('disabled', false);
            }).fail(function () {
                $loading.hide();
                $dest.html('<option value="">Error loading routes</option>');
            });
        });

        // City Swapper - swap source <-> destination and reload destinations
        $('#swapCities').on('click', function () {
            var srcVal = $('#source').val();
            var destVal = $('#destination').val();

            // Only swap if destination is valid
            if (!destVal) return;

            // Set source to old destination value
            $('#source').val(destVal).trigger('change');

            // After AJAX loads, set destination to old source value
            // Use a small delay to wait for AJAX
            setTimeout(function () {
                $('#destination').val(srcVal);
            }, 600);
        });
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>