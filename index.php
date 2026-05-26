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
    
    <!-- Glowing Route Lines in Hero Background -->
    <div class="position-absolute start-0 w-100 h-100 top-0 overflow-hidden pointer-events-none z-0" style="opacity: 0.25;">
        <svg width="100%" height="100%" viewBox="0 0 1440 800" fill="none" xmlns="http://www.w3.org/2000/svg" style="stroke: #2ecc71; stroke-width: 3.5;">
            <path d="M-100 500 C 300 300, 500 650, 900 250 C 1200 50, 1300 450, 1600 150" style="stroke-dasharray: 12 12; animation: moveRoute 25s linear infinite;" />
            <path d="M1600 600 C 1200 400, 900 600, 500 200 C 200 50, 100 400, -100 300" opacity="0.5" style="stroke-dasharray: 10 10; animation: moveRoute 20s linear infinite reverse;" />
        </svg>
    </div>
    
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

</div> <!-- Close container before full-width trust section -->

<!-- Trust Indicators Section - Full Width with Count-Up Animation -->
<div id="trust-section" class="position-relative overflow-hidden py-5 reveal-on-scroll" style="background: linear-gradient(135deg, #0c2016 0%, #0f3d1f 50%, #0c2016 100%);">
    <!-- Animated background particles -->
    <div class="position-absolute top-0 start-0 w-100 h-100 pointer-events-none" style="opacity: 0.06;">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="trustDots" width="40" height="40" patternUnits="userSpaceOnUse">
                    <circle cx="20" cy="20" r="1" fill="#ffffff" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#trustDots)" />
        </svg>
    </div>
    <!-- Floating glow orbs -->
    <div class="position-absolute rounded-circle" style="width: 300px; height: 300px; background: radial-gradient(circle, rgba(25,135,84,0.15) 0%, transparent 70%); top: -100px; left: -50px; animation: floatOrb 8s ease-in-out infinite;"></div>
    <div class="position-absolute rounded-circle" style="width: 250px; height: 250px; background: radial-gradient(circle, rgba(25,135,84,0.1) 0%, transparent 70%); bottom: -80px; right: -30px; animation: floatOrb 10s ease-in-out infinite reverse;"></div>

    <div class="container position-relative z-1">
        <h2 class="fw-bold mb-2 text-center text-white" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.5px; color: #ffffff !important;">Why Travelers Trust SwiftBus</h2>
        <p class="text-center mb-5" style="color: rgba(255,255,255,0.55); font-size: 0.95rem;">Numbers that speak for themselves</p>
        <div class="row g-4 justify-content-center">
            <div class="col-6 col-lg-3">
                <div class="text-center p-4 rounded-4 trust-stat-card" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(8px);">
                    <div class="mb-3 mx-auto d-flex align-items-center justify-content-center rounded-circle" style="width: 64px; height: 64px; background: rgba(25,135,84,0.2); border: 1px solid rgba(25,135,84,0.3); animation: float 4s ease-in-out infinite;">
                        <i class="fa-solid fa-ticket fs-4" style="color: #2ecc71;"></i>
                    </div>
                    <h3 class="fw-bold mb-1 counter-value" data-target="10000" data-suffix="+" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 2.2rem; color: #ffffff !important;">0</h3>
                    <p class="mb-0 small fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 2px; color: rgba(255,255,255,0.5);">Bookings Completed</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="text-center p-4 rounded-4 trust-stat-card" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(8px);">
                    <div class="mb-3 mx-auto d-flex align-items-center justify-content-center rounded-circle" style="width: 64px; height: 64px; background: rgba(25,135,84,0.2); border: 1px solid rgba(25,135,84,0.3); animation: float 5s ease-in-out infinite 0.5s;">
                        <i class="fa-solid fa-route fs-4" style="color: #2ecc71;"></i>
                    </div>
                    <h3 class="fw-bold mb-1 counter-value" data-target="500" data-suffix="+" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 2.2rem; color: #ffffff !important;">0</h3>
                    <p class="mb-0 small fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 2px; color: rgba(255,255,255,0.5);">Active Routes</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="text-center p-4 rounded-4 trust-stat-card" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(8px);">
                    <div class="mb-3 mx-auto d-flex align-items-center justify-content-center rounded-circle" style="width: 64px; height: 64px; background: rgba(25,135,84,0.2); border: 1px solid rgba(25,135,84,0.3); animation: float 4.5s ease-in-out infinite 1s;">
                        <i class="fa-solid fa-face-smile fs-4" style="color: #2ecc71;"></i>
                    </div>
                    <h3 class="fw-bold mb-1 counter-value" data-target="99" data-suffix="%" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 2.2rem; color: #ffffff !important;">0</h3>
                    <p class="mb-0 small fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 2px; color: rgba(255,255,255,0.5);">Satisfaction</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="text-center p-4 rounded-4 trust-stat-card" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(8px);">
                    <div class="mb-3 mx-auto d-flex align-items-center justify-content-center rounded-circle" style="width: 64px; height: 64px; background: rgba(25,135,84,0.2); border: 1px solid rgba(25,135,84,0.3); animation: float 5.5s ease-in-out infinite 1.5s;">
                        <i class="fa-solid fa-headset fs-4" style="color: #2ecc71;"></i>
                    </div>
                    <h3 class="fw-bold mb-1" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 2.2rem; color: #ffffff !important;">24/7</h3>
                    <p class="mb-0 small fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 2px; color: rgba(255,255,255,0.5);">Customer Support</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container"> <!-- Reopen container for remaining sections -->

<!-- Scroll reveal style and Keyframes -->
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
    .trust-stat-card {
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .trust-stat-card:hover {
        transform: translateY(-6px);
        background: rgba(255,255,255,0.1) !important;
        border-color: rgba(46, 204, 113, 0.3) !important;
        box-shadow: 0 15px 40px rgba(0,0,0,0.3);
    }
    @keyframes floatOrb {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(20px, -30px) scale(1.1); }
    }
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    .pointer-events-none { pointer-events: none; }

    @keyframes moveRoute {
        to {
            stroke-dashoffset: -40;
        }
    }
    @keyframes pulseGlow {
        0% { opacity: 1; transform: scale(0.6); }
        50% { opacity: 0.4; transform: scale(1.5); }
        100% { opacity: 0; transform: scale(2.2); }
    }
    .city-glow {
        animation: pulseGlow 2.5s infinite ease-out;
        transform-origin: center;
    }
    .hover-zoom {
        transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .hover-zoom:hover {
        transform: scale(1.06);
    }
</style>

</div> <!-- Close container for full-width doodles wrapper -->

<!-- Popular Routes, Testimonials, and FAQs Container with Doodle Background -->
<div class="position-relative overflow-hidden bg-doodles-wrapper" style="background: rgba(25, 135, 84, 0.02);">
    <!-- Dotted Grid & Curvy Route Paths Background -->
    <div class="position-absolute start-0 w-100 h-100 top-0 overflow-hidden pointer-events-none z-0" style="opacity: 0.35;">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="dotPattern" width="30" height="30" patternUnits="userSpaceOnUse">
                    <circle cx="15" cy="15" r="1.5" fill="var(--accent-primary)" opacity="0.7" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#dotPattern)" />
        </svg>
    </div>
    
    <div class="position-absolute start-0 w-100 h-100 top-0 overflow-hidden pointer-events-none z-0" style="opacity: 0.28;">
        <!-- Traveling curves / doodle lines -->
        <svg width="100%" height="100%" viewBox="0 0 1440 1200" fill="none" xmlns="http://www.w3.org/2000/svg" style="stroke: var(--accent-primary); stroke-width: 3.5;">
            <path d="M-50 150 C 350 50, 450 450, 750 300 C 1050 150, 1150 750, 1500 650" style="stroke-dasharray: 10 10; animation: moveRoute 25s linear infinite;" />
            <path d="M1500 200 C 1100 400, 900 100, 500 500 C 100 900, 300 1050, -50 950" opacity="0.6" style="stroke-dasharray: 10 10; animation: moveRoute 20s linear infinite reverse;" />
            <circle cx="750" cy="300" r="10" fill="var(--accent-primary)" />
            <circle cx="350" cy="100" r="6" fill="var(--accent-primary)" />
            <circle cx="500" cy="500" r="8" fill="var(--accent-primary)" />
            <circle cx="900" cy="100" r="6" fill="var(--accent-primary)" />
        </svg>
    </div>

    <div class="container position-relative z-1 py-5">
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

<div class="container"> <!-- Reopen container for Fleet Gallery -->

<!-- Fleet Gallery Section -->
<div class="py-5 my-5 reveal-on-scroll">
    <div class="text-center mb-5">
        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill uppercase tracking-wider mb-2" style="font-size: 0.8rem; font-weight: 700;">Our Premium Fleet</span>
        <h2 class="fw-bold display-6" style="font-family: 'Plus Jakarta Sans', sans-serif;">Travel In Redefined Comfort</h2>
        <p class="text-secondary mx-auto" style="max-width: 600px;">Explore the state-of-the-art features of our premium, safe, and highly maintained bus fleet.</p>
    </div>
    <div class="row g-4">
        <!-- Card 1 -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm overflow-hidden hover-card-premium h-100 rounded-4" style="background: var(--bg-card);">
                <div class="position-relative overflow-hidden" style="height: 240px;">
                    <img src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=600&q=80" class="w-100 h-100 object-fit-cover hover-zoom" alt="Scania Multi-Axle">
                    <span class="position-absolute top-0 end-0 bg-success text-white px-3 py-1 rounded-bottom-start small m-3 fw-bold" style="border-bottom-left-radius: 12px; border-top-right-radius: 4px; z-index: 1;">Sleeper & Seater</span>
                </div>
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-2">Scania Multi-Axle Premium</h4>
                    <p class="text-secondary small">Equipped with luxury reclining seats, USB ports at every seat, ambient lighting, and GPS tracking.</p>
                    <hr class="border-light opacity-50">
                    <div class="d-flex justify-content-between text-secondary small">
                        <span><i class="fa-solid fa-snowflake text-info me-1"></i> Full AC</span>
                        <span><i class="fa-solid fa-wifi text-warning me-1"></i> Free Wi-Fi</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 2 -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm overflow-hidden hover-card-premium h-100 rounded-4" style="background: var(--bg-card);">
                <div class="position-relative overflow-hidden" style="height: 240px;">
                    <img src="https://images.unsplash.com/photo-1570129476815-ba368ac77013?auto=format&fit=crop&w=600&q=80" class="w-100 h-100 object-fit-cover hover-zoom" alt="Volvo AC Sleeper">
                    <span class="position-absolute top-0 end-0 bg-success text-white px-3 py-1 rounded-bottom-start small m-3 fw-bold" style="border-bottom-left-radius: 12px; border-top-right-radius: 4px; z-index: 1;">Premium Bunks</span>
                </div>
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-2">Volvo AC Luxury Sleeper</h4>
                    <p class="text-secondary small">Spacious individual sleeper berths with clean blankets, reading lights, and privacy curtains.</p>
                    <hr class="border-light opacity-50">
                    <div class="d-flex justify-content-between text-secondary small">
                        <span><i class="fa-solid fa-plug text-info me-1"></i> Charging Slot</span>
                        <span><i class="fa-solid fa-pillow text-warning me-1"></i> Pillow & Blanket</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 3 -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm overflow-hidden hover-card-premium h-100 rounded-4" style="background: var(--bg-card);">
                <div class="position-relative overflow-hidden" style="height: 240px;">
                    <img src="https://images.unsplash.com/photo-1562620669-9783f982136e?auto=format&fit=crop&w=600&q=80" class="w-100 h-100 object-fit-cover hover-zoom" alt="Eco Electric Coach">
                    <span class="position-absolute top-0 end-0 bg-success text-white px-3 py-1 rounded-bottom-start small m-3 fw-bold" style="border-bottom-left-radius: 12px; border-top-right-radius: 4px; z-index: 1;">Eco-Friendly</span>
                </div>
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-2">Electric Intercity Coach</h4>
                    <p class="text-secondary small">Eco-friendly electric motor providing silent rides, regenerative braking, and zero direct emissions.</p>
                    <hr class="border-light opacity-50">
                    <div class="d-flex justify-content-between text-secondary small">
                        <span><i class="fa-solid fa-leaf text-success me-1"></i> Green Travel</span>
                        <span><i class="fa-solid fa-volume-mute text-info me-1"></i> Ultra Quiet</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div> <!-- Close container for full-width Map section -->

<!-- Interactive Route Map Section (Full Width) -->
<div class="position-relative overflow-hidden py-5 reveal-on-scroll" style="background: radial-gradient(circle at top right, #0e3b24, #081a11 90%); min-height: 550px; border-top: 1px solid rgba(46, 204, 113, 0.15); border-bottom: 1px solid rgba(46, 204, 113, 0.15);">
    <!-- Dotted Grid overlay -->
    <div class="position-absolute start-0 w-100 h-100 top-0 overflow-hidden pointer-events-none z-0" style="opacity: 0.05;">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="mapDots" width="30" height="30" patternUnits="userSpaceOnUse">
                    <circle cx="15" cy="15" r="1" fill="#ffffff" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#mapDots)" />
        </svg>
    </div>
    
    <div class="container position-relative z-1 py-3">
        <div class="row align-items-center g-5">
            <!-- Map Info -->
            <div class="col-lg-5 text-white">
                <span class="badge bg-success bg-opacity-20 text-success px-3 py-2 rounded-pill uppercase tracking-wider mb-2" style="font-size: 0.8rem; font-weight: 700; color: #2ecc71 !important; background: rgba(46,204,113,0.15) !important;">Operations Console</span>
                <h2 class="fw-bold mb-3" style="font-family: 'Plus Jakarta Sans', sans-serif; color: #ffffff !important; letter-spacing: -0.5px;">Our Operations Network</h2>
                <p class="text-secondary mb-4" style="color: rgba(255,255,255,0.65) !important; font-size: 0.95rem;">Hover over or tap any city hub card to highlight its active intercity highway connections on the radar console.</p>
                
                <div class="d-flex flex-column gap-3">
                    <!-- Delhi Card -->
                    <div class="interactive-city-card p-3 rounded-4 d-flex align-items-center justify-content-between" data-city="delhi" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255,255,255,0.06); transition: all 0.3s ease; cursor: pointer;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.25);">
                                <i class="fa-solid fa-location-crosshairs text-success"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold" style="color: #ffffff !important;">Delhi Hub (NCR)</h6>
                                <small class="text-secondary" style="color: rgba(255,255,255,0.45) !important;">Connected to Jaipur</small>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-secondary small opacity-50"></i>
                    </div>

                    <!-- Jaipur Card -->
                    <div class="interactive-city-card p-3 rounded-4 d-flex align-items-center justify-content-between" data-city="jaipur" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255,255,255,0.06); transition: all 0.3s ease; cursor: pointer;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.25);">
                                <i class="fa-solid fa-location-crosshairs text-success"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold" style="color: #ffffff !important;">Jaipur Hub</h6>
                                <small class="text-secondary" style="color: rgba(255,255,255,0.45) !important;">Connected to Delhi & Mumbai</small>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-secondary small opacity-50"></i>
                    </div>

                    <!-- Mumbai & Pune Card -->
                    <div class="interactive-city-card p-3 rounded-4 d-flex align-items-center justify-content-between" data-city="mumbai" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255,255,255,0.06); transition: all 0.3s ease; cursor: pointer;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.25);">
                                <i class="fa-solid fa-location-crosshairs text-success"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold" style="color: #ffffff !important;">Mumbai & Pune Hubs</h6>
                                <small class="text-secondary" style="color: rgba(255,255,255,0.45) !important;">Connected to Bangalore & Jaipur</small>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-secondary small opacity-50"></i>
                    </div>

                    <!-- Bangalore & Chennai Card -->
                    <div class="interactive-city-card p-3 rounded-4 d-flex align-items-center justify-content-between" data-city="bangalore" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255,255,255,0.06); transition: all 0.3s ease; cursor: pointer;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.25);">
                                <i class="fa-solid fa-location-crosshairs text-success"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold" style="color: #ffffff !important;">Southern Hubs (BLR & MAA)</h6>
                                <small class="text-secondary" style="color: rgba(255,255,255,0.45) !important;">Connected to Mumbai & Chennai</small>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-secondary small opacity-50"></i>
                    </div>
                </div>
            </div>
            
            <!-- Map Visualization -->
            <div class="col-lg-7 text-center">
                <div class="position-relative p-3 rounded-4 shadow-lg" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.05); max-width: 600px; margin: 0 auto; box-shadow: 0 40px 80px rgba(0,0,0,0.4) !important;">
                    <!-- Top radar graphic detail -->
                    <div class="d-flex justify-content-between align-items-center mb-3 px-2 text-secondary small" style="opacity: 0.6; font-size: 0.75rem;">
                        <span><i class="fa-solid fa-circle text-danger me-1 blink"></i> RADAR FEED LIVE</span>
                        <span>GRID REF: 28.61 N / 77.20 E</span>
                    </div>

                    <!-- Map SVG -->
                    <svg viewBox="0 0 600 500" class="w-100 h-auto" style="max-height: 450px;">
                        <!-- Geographically Accurate India SVG Outline -->
                        <path d="M300,30 L310,40 L308,60 L320,70 L340,70 L330,90 L345,100 L350,115 L385,130 L430,135 L435,150 L450,150 L455,165 L480,160 L510,170 L535,165 L545,185 L525,205 L500,205 L490,225 L470,225 L455,235 L445,260 L438,290 L408,310 L388,360 L358,420 L318,475 L300,480 L290,460 L270,410 L245,370 L220,345 L195,325 L180,320 L160,320 L145,295 L125,295 L135,270 L160,255 L185,250 L205,215 L220,185 L225,145 L250,115 L265,90 L275,65 Z" fill="rgba(46, 204, 113, 0.05)" stroke="rgba(46, 204, 113, 0.35)" stroke-width="1.8" stroke-linejoin="round" />
                        
                        <!-- Connection Tracks (Beats/Waves) -->
                        <g class="map-routes-group" style="stroke: #2ecc71; stroke-width: 2.5; fill: none; opacity: 0.65; transition: opacity 0.3s ease;">
                            <!-- Delhi <-> Jaipur -->
                            <path class="route-line path-delhi path-jaipur" d="M300 120 Q270 140 240 160" style="stroke-dasharray: 6 6; animation: moveRoute 12s linear infinite;" />
                            <!-- Jaipur <-> Mumbai -->
                            <path class="route-line path-jaipur path-mumbai" d="M240 160 Q200 240 190 320" style="stroke-dasharray: 8 8; animation: moveRoute 14s linear infinite;" />
                            <!-- Mumbai <-> Pune -->
                            <path class="route-line path-mumbai path-pune" d="M190 320 Q205 330 220 340" style="stroke-dasharray: 4 4; animation: moveRoute 8s linear infinite;" />
                            <!-- Mumbai <-> Bangalore -->
                            <path class="route-line path-mumbai path-bangalore" d="M190 320 Q240 370 290 410" style="stroke-dasharray: 8 8; animation: moveRoute 18s linear infinite;" />
                            <!-- Bangalore <-> Chennai -->
                            <path class="route-line path-bangalore path-chennai" d="M290 410 Q320 415 350 420" style="stroke-dasharray: 5 5; animation: moveRoute 10s linear infinite;" />
                            <!-- Chennai <-> Mumbai -->
                            <path class="route-line path-chennai path-mumbai" d="M350 420 Q270 380 190 320" style="stroke-dasharray: 10 10; animation: moveRoute 20s linear infinite reverse;" />
                        </g>

                        <!-- Glow Elements for Cities -->
                        <g style="fill: #2ecc71;">
                            <circle id="glow-delhi" cx="300" cy="120" r="12" class="city-glow" style="transform-origin: 300px 120px;" />
                            <circle id="glow-jaipur" cx="240" cy="160" r="12" class="city-glow" style="transform-origin: 240px 160px;" />
                            <circle id="glow-mumbai" cx="190" cy="320" r="12" class="city-glow" style="transform-origin: 190px 320px;" />
                            <circle id="glow-pune" cx="220" cy="340" r="12" class="city-glow" style="transform-origin: 220px 340px;" />
                            <circle id="glow-bangalore" cx="290" cy="410" r="12" class="city-glow" style="transform-origin: 290px 410px;" />
                            <circle id="glow-chennai" cx="350" cy="420" r="12" class="city-glow" style="transform-origin: 350px 420px;" />
                        </g>

                        <!-- Solid City Points & Text labels -->
                        <g style="fill: #ffffff;">
                            <!-- Delhi -->
                            <circle id="dot-delhi" cx="300" cy="120" r="5px" class="city-dot" style="fill: #2ecc71; stroke: #ffffff; stroke-width: 2; transition: all 0.3s ease; transform-origin: 300px 120px;" />
                            <text x="312" y="124" font-size="12" font-family="'Plus Jakarta Sans', sans-serif" font-weight="bold" fill="#ffffff" style="text-shadow: 0 2px 4px rgba(0,0,0,0.8);">Delhi</text>
                            
                            <!-- Jaipur -->
                            <circle id="dot-jaipur" cx="240" cy="160" r="5px" class="city-dot" style="fill: #2ecc71; stroke: #ffffff; stroke-width: 2; transition: all 0.3s ease; transform-origin: 240px 160px;" />
                            <text x="252" y="164" font-size="12" font-family="'Plus Jakarta Sans', sans-serif" font-weight="bold" fill="#ffffff" style="text-shadow: 0 2px 4px rgba(0,0,0,0.8);">Jaipur</text>
                            
                            <!-- Mumbai -->
                            <circle id="dot-mumbai" cx="190" cy="320" r="5px" class="city-dot" style="fill: #2ecc71; stroke: #ffffff; stroke-width: 2; transition: all 0.3s ease; transform-origin: 190px 320px;" />
                            <text x="130" y="324" font-size="12" font-family="'Plus Jakarta Sans', sans-serif" font-weight="bold" fill="#ffffff" style="text-shadow: 0 2px 4px rgba(0,0,0,0.8);">Mumbai</text>
                            
                            <!-- Pune -->
                            <circle id="dot-pune" cx="220" cy="340" r="5px" class="city-dot" style="fill: #2ecc71; stroke: #ffffff; stroke-width: 2; transition: all 0.3s ease; transform-origin: 220px 340px;" />
                            <text x="232" y="344" font-size="12" font-family="'Plus Jakarta Sans', sans-serif" font-weight="bold" fill="#ffffff" style="text-shadow: 0 2px 4px rgba(0,0,0,0.8);">Pune</text>
                            
                            <!-- Bangalore -->
                            <circle id="dot-bangalore" cx="290" cy="410" r="5px" class="city-dot" style="fill: #2ecc71; stroke: #ffffff; stroke-width: 2; transition: all 0.3s ease; transform-origin: 290px 410px;" />
                            <text x="302" y="414" font-size="12" font-family="'Plus Jakarta Sans', sans-serif" font-weight="bold" fill="#ffffff" style="text-shadow: 0 2px 4px rgba(0,0,0,0.8);">Bangalore</text>
                            
                            <!-- Chennai -->
                            <circle id="dot-chennai" cx="350" cy="420" r="5px" class="city-dot" style="fill: #2ecc71; stroke: #ffffff; stroke-width: 2; transition: all 0.3s ease; transform-origin: 350px 420px;" />
                            <text x="362" y="424" font-size="12" font-family="'Plus Jakarta Sans', sans-serif" font-weight="bold" fill="#ffffff" style="text-shadow: 0 2px 4px rgba(0,0,0,0.8);">Chennai</text>
                        </g>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container"> <!-- Reopen container for remaining content / footer compatibility -->

<script>
    $(document).ready(function () {
        // Interactive City Card Hover Effects on Route Map
        $('.interactive-city-card').on('mouseenter', function() {
            const city = $(this).data('city');
            
            // Highlight the card
            $(this).css({
                'background': 'rgba(46, 204, 113, 0.1)',
                'border-color': 'rgba(46, 204, 113, 0.4)',
                'transform': 'translateX(6px)'
            });

            // Dim unrelated lines, highlight related ones
            $('.route-line').css('opacity', '0.12');
            $(`.path-${city}`).css({
                'opacity': '1',
                'stroke-width': '3.5px'
            });

            // Reset and trigger faster pulses for connected hubs
            $('.city-glow').css('animation-duration', '5s'); 
            $('.city-dot').css('transform', 'scale(1)');
            
            if (city === 'delhi') {
                $('#glow-delhi, #glow-jaipur').css('animation-duration', '1s');
                $('#dot-delhi, #dot-jaipur').css('transform', 'scale(1.5)');
            } else if (city === 'jaipur') {
                $('#glow-jaipur, #glow-delhi, #glow-mumbai').css('animation-duration', '1s');
                $('#dot-jaipur, #dot-delhi, #dot-mumbai').css('transform', 'scale(1.5)');
            } else if (city === 'mumbai') {
                $('#glow-mumbai, #glow-jaipur, #glow-pune, #glow-bangalore, #glow-chennai').css('animation-duration', '1s');
                $('#dot-mumbai, #dot-jaipur, #dot-pune, #dot-bangalore, #dot-chennai').css('transform', 'scale(1.5)');
            } else if (city === 'bangalore') {
                $('#glow-bangalore, #glow-mumbai, #glow-chennai').css('animation-duration', '1s');
                $('#dot-bangalore, #dot-mumbai, #dot-chennai').css('transform', 'scale(1.5)');
            }
        }).on('mouseleave', function() {
            // Restore initial states
            $(this).css({
                'background': 'rgba(255, 255, 255, 0.02)',
                'border-color': 'rgba(255, 255, 255, 0.06)',
                'transform': 'none'
            });

            $('.route-line').css({
                'opacity': '',
                'stroke-width': ''
            });

            $('.city-glow').css('animation-duration', '');
            $('.city-dot').css('transform', '');
        });

        // Intersection Observer for Scroll Animation
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('.reveal-on-scroll').forEach((el) => observer.observe(el));

        // Counter Animation - count from 0 to target
        let counterAnimated = false;
        function formatNumber(n) {
            return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        function animateCounters() {
            if (counterAnimated) return;
            counterAnimated = true;
            document.querySelectorAll('.counter-value').forEach((el, index) => {
                const target = parseInt(el.getAttribute('data-target'));
                const suffix = el.getAttribute('data-suffix') || '';
                const duration = 2000;
                const startTime = performance.now();
                const delay = index * 150; // stagger each counter

                setTimeout(() => {
                    function updateCounter(currentTime) {
                        const elapsed = currentTime - startTime - delay;
                        if (elapsed < 0) { requestAnimationFrame(updateCounter); return; }
                        const progress = Math.min(elapsed / duration, 1);
                        // Ease-out cubic
                        const eased = 1 - Math.pow(1 - progress, 3);
                        const current = Math.floor(eased * target);
                        el.textContent = formatNumber(current) + suffix;
                        if (progress < 1) {
                            requestAnimationFrame(updateCounter);
                        } else {
                            el.textContent = formatNumber(target) + suffix;
                        }
                    }
                    requestAnimationFrame(updateCounter);
                }, delay);
            });
        }
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                }
            });
        }, { threshold: 0.3 });
        const trustSection = document.getElementById('trust-section');
        if (trustSection) counterObserver.observe(trustSection);

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