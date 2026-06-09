<?php

/**
 * Customer Homepage - Search Buses
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$page_title = __('nav_home', 'Book Bus Tickets');

// Fetch unique sources from active routes only
try {
    $sources_stmt = $pdo->query("
        SELECT DISTINCT r.source 
        FROM routes r 
        JOIN trips t ON r.id = t.route_id 
        WHERE r.status = 'active' AND t.status = 'ACTIVE' AND t.departure_time >= NOW() 
        ORDER BY r.source ASC
    ");
    $sources = $sources_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch active schedules (trips) with route details and bus details
    $active_trips_stmt = $pdo->query("
        SELECT 
            t.id AS trip_id,
            t.departure_time,
            t.base_fare,
            r.source,
            r.destination,
            r.duration,
            b.bus_name,
            b.bus_type
        FROM trips t
        JOIN routes r ON t.route_id = r.id
        JOIN buses b ON t.bus_id = b.id
        WHERE t.status = 'ACTIVE' AND t.departure_time >= NOW()
        ORDER BY t.departure_time ASC
        LIMIT 10
    ");
    $active_trips = $active_trips_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sources = [];
    $active_trips = [];
}

require_once __DIR__ . '/includes/header.php';
?>


<!-- Hero Banner Section with Video Background & Integrated Search Panel (Full Width) -->
<div class="position-relative overflow-hidden hero-video-section shadow-lg"
    style="height: 90vh; min-height: 600px; background-color: #000000; margin-top: -5rem;">
    <!-- Background Video -->
    <video
        autoplay
        muted
        loop
        playsinline
        preload="auto"
        class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover z-0"
        style="filter: brightness(0.45);"
    >
        <!-- PRIMARY: Local video (download bus video, save as assets/videos/hero.mp4) -->
        <source src="<?= BASE_URL ?>/assets/videos/hero.mp4" type="video/mp4">
        <!-- FALLBACK: Google Storage — confirmed hotlink-allowed, always works -->
        <source src="https://storage.googleapis.com/gtv-videos-bucket/sample/SubaruOutbackOnStreetAndDirt.mp4" type="video/mp4">
    </video>



    <!-- Hero Content Overlay -->
    <div class="position-relative z-1 container px-4 d-flex flex-column justify-content-center align-items-center text-center"
        style="height: 100%; min-height: 600px; padding-top: 5rem;">
        <h1 class="display-4 fw-bold text-white-fixed mb-3"
            style="line-height: 1.1; font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -1px; max-width: 800px; color: #ffffff !important;">
            <?= __('hero_title_1', 'Travel Across India') ?><br><span
                style="background: linear-gradient(135deg, #2ecc71, #198754); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?= __('hero_title_2', 'Comfortably & Reliably') ?></span>
        </h1>
        <p class="lead text-white-fixed mb-4"
            style="max-width: 600px; font-weight: 400; opacity: 0.9; color: rgba(255, 255, 255, 0.9) !important;">
            <?= __('hero_subtitle', 'Book buses, choose seats, track bookings, and travel with confidence.') ?>
        </p>

        <div class="d-flex flex-wrap gap-3 mb-3 justify-content-center">
            <a href="#search-panel" class="btn btn-primary-gradient px-4 py-3 text-uppercase fw-bold text-white-fixed"
                style="font-size: 0.9rem; color: #ffffff !important;"><?= __('hero_btn_search', 'Search Buses') ?></a>
            <a href="#trust-section" class="btn btn-secondary-glass px-4 py-3 text-uppercase fw-bold"
                style="font-size: 0.9rem; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); color: #ffffff !important; border-color: rgba(255,255,255,0.4) !important;"><?= __('hero_btn_features', 'View Features') ?></a>
        </div>
    </div>
</div>

<div class="container position-relative" style="margin-top: 0 !important; z-index: 10;">
    <!-- Re-open the container for the rest of the page contents -->
    <!-- Integrated Search Panel (Overlaps Hero by Half) -->
    <div id="search-panel" class="w-100" style="max-width: 950px; margin: -110px auto 50px auto;">
        <div class="bg-white text-dark p-4 shadow-lg rounded-4 text-start border border-light"
            style="background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px);">
            <h5 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2"
                style="font-family: 'Plus Jakarta Sans', sans-serif;">
                <i class="fa-solid fa-magnifying-glass text-success"></i> <?= __('find_destination', 'Find Your Destination') ?>
            </h5>
            <form action="<?= BASE_URL ?>/search.php" method="GET" class="row g-3 align-items-end">
                <!-- Source dropdown -->
                <div class="col-md-3">
                    <label for="source_search" class="form-label text-secondary small fw-bold"><?= __('leaving_from', 'Leaving From') ?></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-secondary"
                            style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-location-dot"></i></span>
                        <div class="autocomplete-wrapper">
                            <input type="text" id="source_search" class="form-control border-start-0 bg-light"
                                style="border-radius: 0 12px 12px 0; padding: 0.75rem;" placeholder="<?= __('select_origin', 'Select Origin...') ?>" autocomplete="off" required>
                            <input type="hidden" name="source" id="source" value="">
                        </div>
                    </div>
                </div>

                <!-- Swap Button -->
                <div class="col-md-1 text-center mb-2 d-none d-md-block">
                    <button type="button" id="swapCities" class="btn btn-light p-3 rounded-circle border"
                        style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="fa-solid fa-right-left text-success"></i>
                    </button>
                </div>

                <!-- Destination dropdown -->
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1" style="min-height: 21px;">
                        <label for="destination_search" class="form-label text-secondary small fw-bold mb-0"><?= __('going_to', 'Going To') ?></label>
                        <div id="dest-empty" class="small text-warning" style="display:none; font-weight: 500;"><i
                                class="fa-solid fa-triangle-exclamation me-1"></i><?= __('no_routes', 'No routes.') ?></div>
                        <div id="dest-loading" class="small text-muted" style="display:none; font-weight: 500;"><i
                                class="fa-solid fa-spinner fa-spin me-1"></i><?= __('loading', 'Loading...') ?></div>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-secondary"
                            style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-location-crosshairs"></i></span>
                        <div class="autocomplete-wrapper">
                            <input type="text" id="destination_search" class="form-control border-start-0 bg-light"
                                style="border-radius: 0 12px 12px 0; padding: 0.75rem;" placeholder="<?= __('select_destination', 'Select Destination...') ?>" autocomplete="off" required disabled>
                            <input type="hidden" name="destination" id="destination" value="">
                        </div>
                    </div>
                </div>

                <!-- Date Picker -->
                <div class="col-md-3">
                    <label for="date" class="form-label text-secondary small fw-bold"><?= __('travel_date', 'Travel Date') ?></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-secondary"
                            style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-calendar-days"></i></span>
                        <input type="date" name="date" id="date" class="form-control border-start-0 bg-light"
                            style="border-radius: 0 12px 12px 0; padding: 0.75rem;" min="<?= date('Y-m-d') ?>"
                            value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                    </div>
                </div>

                <!-- Search Button -->
                <div class="col-md-2">
                    <div class="d-grid">
                        <button type="submit"
                            class="btn btn-primary-gradient py-3 text-uppercase fw-bold text-white-fixed"
                            style="letter-spacing: 0.5px; color: #ffffff !important;"><?= __('btn_search', 'Search') ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div> <!-- Close container for search panel to allow full-width on subsequent sections -->
<!-- Close container before full-width trust section -->

<!-- Bento Style Premium Feature Showcase Section -->
<div id="trust-section" class="position-relative overflow-hidden py-5 my-5 reveal-on-scroll"
    style="background: linear-gradient(135deg, #091a12 0%, #0d2e18 50%, #091a12 100%); margin-top: 100px !important;">
    <!-- Animated background grid -->
    <div class="position-absolute top-0 start-0 w-100 h-100 pointer-events-none" style="opacity: 0.05;">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="trustDots" width="40" height="40" patternUnits="userSpaceOnUse">
                    <circle cx="20" cy="20" r="1" fill="#ffffff" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#trustDots)" />
        </svg>
    </div>

    <div class="container position-relative z-1">
        <div class="text-center mb-5">
            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill uppercase tracking-wider mb-2" style="font-size: 0.8rem; font-weight: 700; border: 1px solid rgba(46, 204, 113, 0.2);"><?= __('smart_voyages', 'SMART VOYAGES') ?></span>
            <h2 class="fw-bold display-5 text-white" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -1px; color: #ffffff !important;"><?= __('rev_road_travel', 'Revolutionizing Road Travel') ?></h2>
            <p class="text-secondary mx-auto" style="max-width: 600px; color: rgba(255,255,255,0.6) !important;"><?= __('rev_subtitle', 'Experience high-end amenities, robust security infrastructure, and absolute booking flexibility.') ?></p>
        </div>

        <div class="row g-4">
            <!-- 1. Real-time Seating Layout Map -->
            <div class="col-lg-4">
                <div class="p-5 rounded-4 h-100 d-flex flex-column justify-content-between trust-stat-card border"
                    style="background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.07) !important; backdrop-filter: blur(10px);">
                    <div>
                        <div class="mb-4 d-inline-flex align-items-center justify-content-center rounded-3" style="width: 50px; height: 50px; background: rgba(46, 204, 113, 0.1); border: 1px solid rgba(46, 204, 113, 0.25);">
                            <i class="fa-solid fa-chair fs-4 text-success"></i>
                        </div>
                        <h4 class="fw-bold text-white mb-2" style="font-family: 'Plus Jakarta Sans', sans-serif; color: #ffffff !important;"><?= __('feat_seating_title', 'Visual Interactive Seating') ?></h4>
                        <p class="text-secondary small mb-0" style="color: rgba(255,255,255,0.55) !important; line-height: 1.6;"><?= __('feat_seating_desc', 'Our state-of-the-art layout builder provides live grid updates. Select berths, upper/lower sleeper decks, and window alignments in a premium visual interface.') ?></p>
                    </div>
                    <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 d-flex justify-content-between align-items-center">
                        <span class="text-success small fw-bold font-monospace text-uppercase" style="letter-spacing: 1px;"><?= __('feat_seating_badge', 'Live Seating Engine') ?></span>
                        <i class="fa-solid fa-arrow-trend-up text-success"></i>
                    </div>
                </div>
            </div>

            <!-- 2. Integrated Payment & Security -->
            <div class="col-lg-4">
                <div class="p-5 rounded-4 h-100 d-flex flex-column justify-content-between trust-stat-card border"
                    style="background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.07) !important; backdrop-filter: blur(10px);">
                    <div>
                        <div class="mb-4 d-inline-flex align-items-center justify-content-center rounded-3" style="width: 50px; height: 50px; background: rgba(46, 204, 113, 0.1); border: 1px solid rgba(46, 204, 113, 0.25);">
                            <i class="fa-solid fa-shield-halved fs-4 text-success"></i>
                        </div>
                        <h4 class="fw-bold text-white mb-2" style="font-family: 'Plus Jakarta Sans', sans-serif; color: #ffffff !important;"><?= __('feat_security_title', 'Encrypted Safety Standards') ?></h4>
                        <p class="text-secondary small mb-0" style="color: rgba(255,255,255,0.55) !important; line-height: 1.6;"><?= __('feat_security_desc', 'Every booking is processed through secure payment gateways with dynamic hash verification. Advanced session controls safeguard active partner logs.') ?></p>
                    </div>
                    <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 d-flex justify-content-between align-items-center">
                        <span class="text-success small fw-bold font-monospace text-uppercase" style="letter-spacing: 1px;"><?= __('feat_security_badge', 'SSL Secure Desk') ?></span>
                        <i class="fa-solid fa-lock text-success"></i>
                    </div>
                </div>
            </div>

            <!-- 3. Smart Refunds & Cancellations -->
            <div class="col-lg-4">
                <div class="p-5 rounded-4 h-100 d-flex flex-column justify-content-between trust-stat-card border"
                    style="background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.07) !important; backdrop-filter: blur(10px);">
                    <div>
                        <div class="mb-4 d-inline-flex align-items-center justify-content-center rounded-3" style="width: 50px; height: 50px; background: rgba(46, 204, 113, 0.1); border: 1px solid rgba(46, 204, 113, 0.25);">
                            <i class="fa-solid fa-clock-rotate-left fs-4 text-success"></i>
                        </div>
                        <h4 class="fw-bold text-white mb-2" style="font-family: 'Plus Jakarta Sans', sans-serif; color: #ffffff !important;"><?= __('feat_refund_title', 'Instant Cancel & Auto-Refund') ?></h4>
                        <p class="text-secondary small mb-0" style="color: rgba(255,255,255,0.55) !important; line-height: 1.6;"><?= __('feat_refund_desc', 'Change of plans? Cancel tickets with a single click. Our automated settlement system routes refunds instantly based on clean cancellation structures.') ?></p>
                    </div>
                    <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 d-flex justify-content-between align-items-center">
                        <span class="text-success small fw-bold font-monospace text-uppercase" style="letter-spacing: 1px;"><?= __('feat_refund_badge', 'Flex Cancellation') ?></span>
                        <i class="fa-solid fa-rotate-left text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
        border: 1px solid var(--border-color) !important;
        background: var(--card-bg) !important;
        color: var(--text-primary) !important;
    }

    .hover-card-premium h5,
    .hover-card-premium h4,
    .hover-card-premium .text-dark {
        color: var(--text-primary) !important;
    }

    .hover-card-premium p,
    .hover-card-premium .text-secondary {
        color: var(--text-secondary) !important;
    }

    .hover-card-premium:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: var(--shadow-hover) !important;
        border-color: var(--accent-primary) !important;
    }

    .trust-stat-card {
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .trust-stat-card:hover {
        transform: translateY(-6px);
        background: rgba(255, 255, 255, 0.1) !important;
        border-color: rgba(46, 204, 113, 0.3) !important;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    }

    @keyframes floatOrb {

        0%,
        100% {
            transform: translate(0, 0) scale(1);
        }

        50% {
            transform: translate(20px, -30px) scale(1.1);
        }
    }

    @keyframes float {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-10px);
        }
    }

    .pointer-events-none {
        pointer-events: none;
    }

    @keyframes moveRoute {
        to {
            stroke-dashoffset: -40;
        }
    }

    @keyframes pulseGlow {
        0% {
            opacity: 1;
            transform: scale(0.6);
        }

        50% {
            opacity: 0.4;
            transform: scale(1.5);
        }

        100% {
            opacity: 0;
            transform: scale(2.2);
        }
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



<!-- Popular Routes, Testimonials, and FAQs Container with Doodle Background -->
<div class="position-relative overflow-hidden bg-doodles-wrapper" style="background: var(--bg-primary);">
    <!-- Dotted Grid & Curvy Route Paths Background -->
    <div class="position-absolute start-0 w-100 h-100 top-0 overflow-hidden pointer-events-none z-0"
        style="opacity: 0.35;">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="dotPattern" width="30" height="30" patternUnits="userSpaceOnUse">
                    <circle cx="15" cy="15" r="1.5" fill="var(--accent-primary)" opacity="0.7" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#dotPattern)" />
        </svg>
    </div>

    <div class="position-absolute start-0 w-100 h-100 top-0 overflow-hidden pointer-events-none z-0"
        style="opacity: 0.28;">
        <!-- Traveling curves / doodle lines -->
        <svg width="100%" height="100%" viewBox="0 0 1440 1200" fill="none" xmlns="http://www.w3.org/2000/svg"
            style="stroke: var(--accent-primary); stroke-width: 3.5;">
            <path d="M-50 150 C 350 50, 450 450, 750 300 C 1050 150, 1150 750, 1500 650"
                style="stroke-dasharray: 10 10; animation: moveRoute 25s linear infinite;" />
            <path d="M1500 200 C 1100 400, 900 100, 500 500 C 100 900, 300 1050, -50 950" opacity="0.6"
                style="stroke-dasharray: 10 10; animation: moveRoute 20s linear infinite reverse;" />
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
                <span
                    class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill uppercase tracking-wider mb-2"
                    style="font-size: 0.8rem; font-weight: 700;"><?= __('explore_india', 'Explore India') ?></span>
                <h2 class="fw-bold display-6" style="font-family: 'Plus Jakarta Sans', sans-serif;"><?= __('popular_routes', 'Popular Routes & Fares') ?></h2>
                <p class="text-secondary mx-auto" style="max-width: 600px;"><?= __('popular_subtitle', 'Book tickets for our most frequent and highly-rated routes at unbeatable prices.') ?></p>
            </div>
            <!-- Dynamic Carousel of Active Schedules -->
            <?php if (empty($active_trips)): ?>
                <div class="text-center py-5">
                    <p class="text-secondary"><?= __('no_schedules', 'No active schedules found for upcoming dates.') ?></p>
                </div>
            <?php else: ?>
                <div id="popularRoutesCarousel" class="carousel slide" data-bs-ride="carousel">
                    <!-- Carousel Indicators -->
                    <div class="carousel-indicators mb-0" style="bottom: -40px;">
                        <?php
                        $chunks = array_chunk($active_trips, 3);
                        foreach ($chunks as $index => $chunk):
                        ?>
                            <button type="button" data-bs-target="#popularRoutesCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index === 0 ? 'active' : '' ?>" aria-current="<?= $index === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $index + 1 ?>" style="background-color: var(--accent-primary); width: 12px; height: 12px; border-radius: 50%;"></button>
                        <?php endforeach; ?>
                    </div>

                    <div class="carousel-inner px-2 py-3">
                        <?php foreach ($chunks as $index => $chunk): ?>
                            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                <div class="row g-4">
                                    <?php foreach ($chunk as $trip):
                                        // Pick badge randomly or based on criteria
                                        $badges = ['Daily Service', 'High Demand', 'Premium Route', 'Eco Friendly'];
                                        $badge = $badges[($trip['trip_id'] % count($badges))];

                                        // Format date and time
                                        $dep_time = date('h:i A', strtotime($trip['departure_time']));
                                        $dep_date = date('d M Y', strtotime($trip['departure_time']));
                                    ?>
                                        <div class="col-md-4">
                                            <div class="p-4 rounded-4 hover-card-premium shadow-sm h-100 d-flex flex-column justify-content-between">
                                                <div>
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-1.5 rounded-pill" style="font-size: 0.75rem; font-weight: 600;"><?= $badge ?></span>
                                                        <span class="fw-bold text-success text-monospace" style="font-size: 1.15rem;">₹<?= number_format(floatval($trip['base_fare']) > 0 ? floatval($trip['base_fare']) : 399, 0) ?> <span class="text-secondary" style="font-size: 0.75rem; font-weight: normal;"><?= __('onwards', 'onwards') ?></span></span>
                                                    </div>

                                                    <h5 class="fw-bold mb-2 text-main d-flex align-items-center gap-2" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem;">
                                                        <span><?= htmlspecialchars($trip['source']) ?></span>
                                                        <i class="fa-solid fa-arrow-right-long text-success" style="font-size: 0.9rem;"></i>
                                                        <span><?= htmlspecialchars($trip['destination']) ?></span>
                                                    </h5>

                                                    <div class="text-secondary small mb-3">
                                                        <div class="d-flex align-items-center mb-1.5">
                                                            <i class="fa-regular fa-clock text-success me-2"></i>
                                                            <span><?= __('approx', 'Approx.') ?> <?= htmlspecialchars($trip['duration'] ?: '6h') ?></span>
                                                            <span class="mx-2 text-muted">•</span>
                                                            <span><?= htmlspecialchars($trip['bus_type']) ?></span>
                                                        </div>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fa-regular fa-calendar text-success me-2"></i>
                                                            <span><?= __('departs', 'Departs:') ?> <strong><?= $dep_date ?></strong> <?= __('at', 'at') ?> <strong><?= $dep_time ?></strong></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <a href="<?= BASE_URL ?>/search.php?source=<?= urlencode($trip['source']) ?>&destination=<?= urlencode($trip['destination']) ?>&date=<?= date('Y-m-d', strtotime($trip['departure_time'])) ?>"
                                                    class="btn btn-outline-success btn-sm w-100 rounded-3 py-2 fw-bold text-uppercase mt-2 transition-all"><?= __('check_seats', 'Check Seats') ?></a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Testimonials Section -->
        <div class="py-5 my-5 reveal-on-scroll rounded-5 p-5 border shadow-sm" style="background: var(--card-bg); border-color: var(--border-glass) !important;">
            <div class="text-center mb-5">
                <h2 class="fw-bold" style="font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-primary);"><?= __('what_travelers_say', 'What Our Travelers Say') ?></h2>
                <p class="text-secondary" style="color: var(--text-secondary) !important;"><?= __('testimonials_subtitle', 'Verified reviews from passengers who travel with SwiftBus regularly.') ?></p>
            </div>
            <div class="row g-4">
                <!-- Review 1 -->
                <div class="col-md-6">
                    <div class="p-4 rounded-4 border h-100 shadow-sm" style="background: var(--bg-secondary); border-color: var(--border-glass) !important;">
                        <div class="text-warning mb-2">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                class="fa-solid fa-star"></i>
                        </div>
                        <p class="mb-3 italic" style="color: var(--text-primary); opacity: 0.9;"><?= __('passenger_1_text', '"Extremely clean buses and very polite drivers. The online seat selection matched the bus layout perfectly. Will book again!"') ?></p>
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold"
                                style="width: 42px; height: 42px;">AS</div>
                            <div>
                                <h6 class="fw-bold mb-0" style="color: var(--text-primary);"><?= __('passenger_1_name', 'Aarav Sharma') ?></h6>
                                <span class="text-secondary small" style="color: var(--text-secondary) !important;"><?= __('passenger_1_role', 'Verified Passenger') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Review 2 -->
                <div class="col-md-6">
                    <div class="p-4 rounded-4 border h-100 shadow-sm" style="background: var(--bg-secondary); border-color: var(--border-glass) !important;">
                        <div class="text-warning mb-2">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                class="fa-solid fa-star"></i>
                        </div>
                        <p class="mb-3 italic" style="color: var(--text-primary); opacity: 0.9;"><?= __('passenger_2_text', '"The live notification feature kept me updated. Ticket refund and cancellation is super simple compared to other portals."') ?></p>
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold"
                                style="width: 42px; height: 42px;">RP</div>
                            <div>
                                <h6 class="fw-bold mb-0" style="color: var(--text-primary);"><?= __('passenger_2_name', 'Riya Patel') ?></h6>
                                <span class="text-secondary small" style="color: var(--text-secondary) !important;"><?= __('passenger_2_role', 'Frequent Traveler') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Accordion Section -->
        <div class="py-4 my-4 reveal-on-scroll">
            <div class="text-center mb-5">
                <h2 class="fw-bold" style="font-family: 'Plus Jakarta Sans', sans-serif;"><?= __('faq_title', 'Frequently Asked Questions') ?></h2>
                <p class="text-secondary"><?= __('faq_subtitle', 'Have doubts? We\'ve got answers to the most common queries.') ?></p>
            </div>
            <div class="accordion accordion-flush mx-auto shadow-sm border rounded-4 overflow-hidden" id="faqAccordion"
                style="max-width: 800px; background: var(--bg-card);">
                <div class="accordion-item"
                    style="background: transparent; border-bottom: 1px solid var(--border-glass);">
                    <h2 class="accordion-header" id="faq-headingOne">
                        <button class="accordion-button collapsed fw-bold text-main py-3"
                            style="background: transparent;" type="button" data-bs-toggle="collapse"
                            data-bs-target="#faq-collapseOne" aria-expanded="false" aria-controls="faq-collapseOne">
                            <?= __('faq_q1', 'How do I book tickets online?') ?>
                        </button>
                    </h2>
                    <div id="faq-collapseOne" class="accordion-collapse collapse" aria-labelledby="faq-headingOne"
                        data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary small">
                            <?= __('faq_a1', 'Simply enter your departure city, destination, and select your travel date on the homepage. Click search, select your preferred bus service, pick your seat, fill passenger details, and finish the booking.') ?>
                        </div>
                    </div>
                </div>
                <div class="accordion-item"
                    style="background: transparent; border-bottom: 1px solid var(--border-glass);">
                    <h2 class="accordion-header" id="faq-headingTwo">
                        <button class="accordion-button collapsed fw-bold text-main py-3"
                            style="background: transparent;" type="button" data-bs-toggle="collapse"
                            data-bs-target="#faq-collapseTwo" aria-expanded="false" aria-controls="faq-collapseTwo">
                            <?= __('faq_q2', 'Can I cancel or reschedule my ticket?') ?>
                        </button>
                    </h2>
                    <div id="faq-collapseTwo" class="accordion-collapse collapse" aria-labelledby="faq-headingTwo"
                        data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary small">
                            <?= __('faq_a2', 'Yes, you can cancel tickets easily through the \'My Bookings\' section in your dashboard. Refund policies will apply depending on how many hours are left before the scheduled departure.') ?>
                        </div>
                    </div>
                </div>
                <div class="accordion-item" style="background: transparent; border-bottom: none;">
                    <h2 class="accordion-header" id="faq-headingThree">
                        <button class="accordion-button collapsed fw-bold text-main py-3"
                            style="background: transparent;" type="button" data-bs-toggle="collapse"
                            data-bs-target="#faq-collapseThree" aria-expanded="false" aria-controls="faq-collapseThree">
                            <?= __('faq_q3', 'What are the payment methods accepted?') ?>
                        </button>
                    </h2>
                    <div id="faq-collapseThree" class="accordion-collapse collapse" aria-labelledby="faq-headingThree"
                        data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-secondary small">
                            <?= __('faq_a3', 'We accept all major credit cards, debit cards, UPI payments, net banking, and secure digital wallets.') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> <!-- Close inner background doodles wrapper -->

<!-- Fleet Gallery Section -->
<div class="position-relative overflow-hidden py-5 reveal-on-scroll" style="background: var(--bg-primary);">
    <!-- Dot Pattern Background -->
    <div class="position-absolute start-0 w-100 h-100 top-0 overflow-hidden pointer-events-none z-0" style="opacity: 0.35;">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="fleetDotPattern" width="30" height="30" patternUnits="userSpaceOnUse">
                    <circle cx="15" cy="15" r="1.5" fill="var(--accent-primary)" opacity="0.7" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#fleetDotPattern)" />
        </svg>
    </div>

    <div class="container position-relative z-1">
        <div class="text-center mb-5">
            <span
                class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill uppercase tracking-wider mb-2"
                style="font-size: 0.8rem; font-weight: 700; border: 1px solid rgba(25, 135, 84, 0.2);"><?= __('premium_fleet', 'Our Premium Fleet') ?></span>
            <h2 class="fw-bold display-6" style="font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-primary);"><?= __('fleet_title', 'Travel In Redefined Comfort') ?></h2>
            <p class="text-secondary mx-auto" style="max-width: 600px; color: var(--text-secondary) !important;"><?= __('fleet_subtitle', 'Explore the state-of-the-art features of our premium, safe, and highly maintained bus fleet.') ?></p>
        </div>
        <div class="row g-4">
            <!-- Card 1 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm overflow-hidden hover-card-premium h-100 rounded-4"
                    style="background: var(--bg-card); border: 1px solid var(--border-color) !important;">
                    <div class="position-relative overflow-hidden" style="height: 240px;">
                        <img src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=600&q=80"
                            class="w-100 h-100 object-fit-cover hover-zoom" alt="Scania Multi-Axle">
                        <span
                            class="position-absolute top-0 end-0 bg-success text-white px-3 py-1 rounded-bottom-start small m-3 fw-bold"
                            style="border-bottom-left-radius: 12px; border-top-right-radius: 4px; z-index: 1;"><?= __('sleeper_seater', 'Sleeper & Seater') ?></span>
                    </div>
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-2" style="color: var(--text-primary);"><?= __('fleet_1_name', 'Scania Multi-Axle Premium') ?></h4>
                        <p class="text-secondary small" style="color: var(--text-secondary) !important;"><?= __('fleet_1_desc', 'Equipped with luxury reclining seats, USB ports at every seat, ambient lighting, and GPS tracking.') ?></p>
                        <hr class="border-light opacity-50">
                        <div class="d-flex justify-content-between text-secondary small" style="color: var(--text-secondary) !important;">
                            <span><i class="fa-solid fa-snowflake text-info me-1"></i> <?= __('full_ac', 'Full AC') ?></span>
                            <span><i class="fa-solid fa-wifi text-warning me-1"></i> <?= __('free_wifi', 'Free Wi-Fi') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm overflow-hidden hover-card-premium h-100 rounded-4"
                    style="background: var(--bg-card); border: 1px solid var(--border-color) !important;">
                    <div class="position-relative overflow-hidden" style="height: 240px;">
                        <img src="<?= BASE_URL ?>/assets/sleeper_interior.png"
                            class="w-100 h-100 object-fit-cover hover-zoom" alt="Volvo AC Sleeper">
                        <span
                            class="position-absolute top-0 end-0 bg-success text-white px-3 py-1 rounded-bottom-start small m-3 fw-bold"
                            style="border-bottom-left-radius: 12px; border-top-right-radius: 4px; z-index: 1;"><?= __('premium_bunks', 'Premium Bunks') ?></span>
                    </div>
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-2" style="color: var(--text-primary);"><?= __('fleet_2_name', 'Volvo AC Luxury Sleeper') ?></h4>
                        <p class="text-secondary small" style="color: var(--text-secondary) !important;"><?= __('fleet_2_desc', 'Spacious individual sleeper berths with clean blankets, reading lights, and privacy curtains.') ?></p>
                        <hr class="border-light opacity-50">
                        <div class="d-flex justify-content-between text-secondary small" style="color: var(--text-secondary) !important;">
                            <span><i class="fa-solid fa-plug text-info me-1"></i> <?= __('charging_slot', 'Charging Slot') ?></span>
                            <span><i class="fa-solid fa-pillow text-warning me-1"></i> <?= __('pillow_blanket', 'Pillow & Blanket') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Card 3 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm overflow-hidden hover-card-premium h-100 rounded-4"
                    style="background: var(--bg-card); border: 1px solid var(--border-color) !important;">
                    <div class="position-relative overflow-hidden" style="height: 240px;">
                        <img src="<?= BASE_URL ?>/assets/electric_coach.png"
                            class="w-100 h-100 object-fit-cover hover-zoom" alt="Eco Electric Coach">
                        <span
                            class="position-absolute top-0 end-0 bg-success text-white px-3 py-1 rounded-bottom-start small m-3 fw-bold"
                            style="border-bottom-left-radius: 12px; border-top-right-radius: 4px; z-index: 1;"><?= __('eco_friendly', 'Eco-Friendly') ?></span>
                    </div>
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-2" style="color: var(--text-primary);"><?= __('fleet_3_name', 'Electric Intercity Coach') ?></h4>
                        <p class="text-secondary small" style="color: var(--text-secondary) !important;"><?= __('fleet_3_desc', 'Eco-friendly electric motor providing silent rides, regenerative braking, and zero direct emissions.') ?></p>
                        <hr class="border-light opacity-50">
                        <div class="d-flex justify-content-between text-secondary small" style="color: var(--text-secondary) !important;">
                            <span><i class="fa-solid fa-leaf text-success me-1"></i> <?= __('green_travel', 'Green Travel') ?></span>
                            <span><i class="fa-solid fa-volume-mute text-info me-1"></i> <?= __('ultra_quiet', 'Ultra Quiet') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Interactive Route Map Section (Clean Light-Mode Transit Network) -->
<div id="radar-control-section" class="position-relative overflow-hidden py-5 reveal-on-scroll"
    style="background: var(--bg-primary); min-height: 550px; color: var(--text-primary); font-family: 'Plus Jakarta Sans', sans-serif;">
    
    <!-- Dot Pattern Background -->
    <div class="position-absolute start-0 w-100 h-100 top-0 overflow-hidden pointer-events-none z-0" style="opacity: 0.35;">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="radarDotPattern" width="30" height="30" patternUnits="userSpaceOnUse">
                    <circle cx="15" cy="15" r="1.5" fill="var(--accent-primary)" opacity="0.7" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#radarDotPattern)" />
        </svg>
    </div>

    <!-- Moving Road Path Background Doodle -->
    <div class="position-absolute start-0 w-100 h-100 top-0 overflow-hidden pointer-events-none z-0" style="opacity: 0.18;">
        <svg width="100%" height="100%" viewBox="0 0 1440 600" fill="none" xmlns="http://www.w3.org/2000/svg" style="stroke: var(--accent-primary); stroke-width: 2.5;">
            <path d="M-100 200 C 300 50, 500 550, 900 300 C 1300 50, 1200 450, 1600 250"
                style="stroke-dasharray: 12 12; animation: moveRoute 30s linear infinite; will-change: stroke-dashoffset;" />
            <path d="M1600 400 C 1200 150, 1000 550, 600 200 C 200 50, 200 450, -100 350" opacity="0.6"
                style="stroke-dasharray: 12 12; animation: moveRoute 25s linear infinite reverse; will-change: stroke-dashoffset;" />
            <circle cx="300" cy="100" r="6" fill="var(--accent-primary)" opacity="0.3" />
            <circle cx="900" cy="300" r="8" fill="var(--accent-primary)" opacity="0.3" />
            <circle cx="1300" cy="150" r="5" fill="var(--accent-primary)" opacity="0.3" />
        </svg>
    </div>

    <div class="container position-relative z-1 py-4">
        <div class="row align-items-center g-5">
            <!-- Left Side Content (40% Desktop / 50% Tablet / Stacked Mobile) -->
            <div class="col-lg-5 col-md-6 text-start">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="pulse-indicator"></span>
                    <span class="text-uppercase fw-bold tracking-wider" style="color: var(--accent-primary); font-size: 0.75rem; letter-spacing: 2px;">🟢 Live Network Status</span>
                </div>
                
                <h2 class="fw-bold mb-3 display-6" style="color: var(--text-primary); letter-spacing: -1px; font-weight: 800;">
                    Nationwide Bus Network Coverage
                </h2>
                
                <p class="mb-4" style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6;">
                    Connecting major cities across India and Nepal with real-time route monitoring and seamless travel experiences. All major routes operational.
                </p>

                <!-- Statistics Grid in Premium Light Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="light-stat-card p-3 h-100">
                            <div class="stat-val" style="color: var(--accent-primary); font-size: 1.5rem; font-weight: 800;">500+</div>
                            <div class="stat-lbl" style="color: var(--text-secondary); font-size: 0.75rem; font-weight: 500;">Active Routes</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="light-stat-card p-3 h-100">
                            <div class="stat-val" style="color: var(--accent-primary); font-size: 1.5rem; font-weight: 800;">1500+</div>
                            <div class="stat-lbl" style="color: var(--text-secondary); font-size: 0.75rem; font-weight: 500;">Daily Trips</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="light-stat-card p-3 h-100">
                            <div class="stat-val" style="color: var(--accent-primary); font-size: 1.5rem; font-weight: 800;">100+</div>
                            <div class="stat-lbl" style="color: var(--text-secondary); font-size: 0.75rem; font-weight: 500;">Cities Connected</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="light-stat-card p-3 h-100">
                            <div class="stat-val" style="color: var(--accent-primary); font-size: 1.5rem; font-weight: 800;">99.8%</div>
                            <div class="stat-lbl" style="color: var(--text-secondary); font-size: 0.75rem; font-weight: 500;">Service Uptime</div>
                        </div>
                    </div>
                </div>

                <!-- CTA Action -->
                <div>
                    <a href="#search-panel" class="btn btn-network-cta py-3 px-4 fw-bold text-uppercase d-inline-flex align-items-center gap-2">
                        <span>Book Your Route</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Right Side Route Timeline Visualization (60% Desktop / 50% Tablet / Stacked Mobile) -->
            <div class="col-lg-7 col-md-6 text-start">
                <div class="p-4 rounded-4" style="background: var(--card-bg); border: 1px solid var(--border-color); box-shadow: var(--shadow-diffusion); max-width: 600px; margin: 0 auto;">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3" style="border-color: var(--border-color) !important;">
                        <h6 class="fw-bold mb-0" style="color: var(--text-primary);"><i class="fa-solid fa-route me-2" style="color: var(--accent-primary);"></i>Primary Transit Network Corridor</h6>
                        <span class="badge px-2.5 py-1 small fw-semibold" style="background: rgba(25, 135, 84, 0.08); color: var(--accent-primary);">6 Core Hubs</span>
                    </div>

                    <!-- Vertical Timeline Corridor -->
                    <div class="position-relative ps-4 ms-2">
                        <!-- Connecting Line -->
                        <div class="position-absolute start-0 top-0 h-100" style="width: 2px; background: var(--border-color);">
                            <div class="animated-progress-bar h-50 position-absolute start-0 top-0 w-100" style="background: var(--accent-primary); transition: height 0.6s cubic-bezier(0.16, 1, 0.3, 1); will-change: height;"></div>
                        </div>

                        <!-- Hub Delhi -->
                        <div class="transit-hub-item position-relative mb-4 pb-2" data-progress="20">
                            <div class="transit-node-dot position-absolute"></div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 rounded-3 border transition-all hover-shadow-sm" style="background: var(--bg-secondary); border-color: var(--border-color) !important;">
                                <div>
                                    <h6 class="fw-bold mb-1" style="font-size: 0.95rem;">Delhi Hub <span class="badge rounded-pill bg-light border text-secondary ms-1 small" style="font-size: 0.65rem;">NCR</span></h6>
                                    <span class="text-secondary small d-block" style="font-size: 0.75rem;"><i class="fa-solid fa-link me-1"></i>Next Stop: Jaipur</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--accent-primary);">45 Active Routes</div>
                                    <div class="text-muted small" style="font-size: 0.7rem;">320 Departures / Day</div>
                                </div>
                            </div>
                        </div>

                        <!-- Hub Jaipur -->
                        <div class="transit-hub-item position-relative mb-4 pb-2" data-progress="40">
                            <div class="transit-node-dot position-absolute"></div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 rounded-3 border transition-all hover-shadow-sm" style="background: var(--bg-secondary); border-color: var(--border-color) !important;">
                                <div>
                                    <h6 class="fw-bold mb-1" style="font-size: 0.95rem;">Jaipur Stop</h6>
                                    <span class="text-secondary small d-block" style="font-size: 0.75rem;"><i class="fa-solid fa-link me-1"></i>Next Stop: Mumbai</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--accent-primary);">28 Active Routes</div>
                                    <div class="text-muted small" style="font-size: 0.7rem;">140 Departures / Day</div>
                                </div>
                            </div>
                        </div>

                        <!-- Hub Mumbai -->
                        <div class="transit-hub-item position-relative mb-4 pb-2" data-progress="60">
                            <div class="transit-node-dot position-absolute"></div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 rounded-3 border transition-all hover-shadow-sm" style="background: var(--bg-secondary); border-color: var(--border-color) !important;">
                                <div>
                                    <h6 class="fw-bold mb-1" style="font-size: 0.95rem;">Mumbai Hub</h6>
                                    <span class="text-secondary small d-block" style="font-size: 0.75rem;"><i class="fa-solid fa-link me-1"></i>Next Stop: Pune</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--accent-primary);">52 Active Routes</div>
                                    <div class="text-muted small" style="font-size: 0.7rem;">410 Departures / Day</div>
                                </div>
                            </div>
                        </div>

                        <!-- Hub Pune -->
                        <div class="transit-hub-item position-relative mb-4 pb-2" data-progress="80">
                            <div class="transit-node-dot position-absolute"></div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 rounded-3 border transition-all hover-shadow-sm" style="background: var(--bg-secondary); border-color: var(--border-color) !important;">
                                <div>
                                    <h6 class="fw-bold mb-1" style="font-size: 0.95rem;">Pune Stop</h6>
                                    <span class="text-secondary small d-block" style="font-size: 0.75rem;"><i class="fa-solid fa-link me-1"></i>Next Stop: Bangalore</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--accent-primary);">34 Active Routes</div>
                                    <div class="text-muted small" style="font-size: 0.7rem;">220 Departures / Day</div>
                                </div>
                            </div>
                        </div>

                        <!-- Hub Bangalore -->
                        <div class="transit-hub-item position-relative mb-4 pb-2" data-progress="90">
                            <div class="transit-node-dot position-absolute"></div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 rounded-3 border transition-all hover-shadow-sm" style="background: var(--bg-secondary); border-color: var(--border-color) !important;">
                                <div>
                                    <h6 class="fw-bold mb-1" style="font-size: 0.95rem;">Bangalore Hub</h6>
                                    <span class="text-secondary small d-block" style="font-size: 0.75rem;"><i class="fa-solid fa-link me-1"></i>Next Stop: Chennai</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--accent-primary);">48 Active Routes</div>
                                    <div class="text-muted small" style="font-size: 0.7rem;">350 Departures / Day</div>
                                </div>
                            </div>
                        </div>

                        <!-- Hub Chennai -->
                        <div class="transit-hub-item position-relative" data-progress="100">
                            <div class="transit-node-dot position-absolute"></div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 rounded-3 border transition-all hover-shadow-sm" style="background: var(--bg-secondary); border-color: var(--border-color) !important;">
                                <div>
                                    <h6 class="fw-bold mb-1" style="font-size: 0.95rem;">Chennai Terminus</h6>
                                    <span class="text-secondary small d-block" style="font-size: 0.75rem;"><i class="fa-solid fa-circle-check me-1" style="color: var(--accent-primary);"></i>Corridor Terminus</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold" style="font-size: 0.85rem; color: var(--accent-primary);">39 Active Routes</div>
                                    <div class="text-muted small" style="font-size: 0.7rem;">280 Departures / Day</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container"> <!-- Reopen container for remaining content / footer compatibility -->

<style>
    #radar-control-section {
        position: relative;
    }
    
    .pulse-indicator {
        width: 8px;
        height: 8px;
        background-color: var(--accent-primary);
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 8px rgba(25, 135, 84, 0.6);
        animation: pulseBlue 1.8s infinite ease-in-out;
    }

    .light-stat-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
    }

    .light-stat-card:hover {
        border-color: var(--accent-primary);
        transform: translateY(-2px);
        box-shadow: 0 8px 15px rgba(25, 135, 84, 0.08);
    }

    .btn-network-cta {
        background: var(--accent-gold-gradient);
        border: none;
        color: #FFFFFF !important;
        border-radius: 10px;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }

    .btn-network-cta:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(25, 135, 84, 0.3);
    }

    /* Vertical Timeline nodes styling */
    .transit-node-dot {
        width: 12px;
        height: 12px;
        background: var(--card-bg);
        border: 3px solid var(--border-color);
        border-radius: 50%;
        left: -5px;
        top: 24px;
        z-index: 2;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        will-change: transform, background, border-color;
    }

    .transit-hub-item:hover .transit-node-dot {
        border-color: var(--accent-primary);
        background: var(--accent-primary);
        transform: scale(1.35);
    }

    .transit-hub-item:hover .hover-shadow-sm {
        border-color: var(--accent-primary) !important;
        background: var(--card-bg) !important;
        box-shadow: 0 8px 20px rgba(25, 135, 84, 0.08);
        transform: translateX(6px);
    }

    .transit-hub-item, .transit-hub-item .hover-shadow-sm {
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        will-change: transform, box-shadow, background-color, border-color;
    }

    @keyframes pulseBlue {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.4); opacity: 0.5; }
    }
</style>

<script>
    $(document).ready(function() {
        // Timeline hover progress indicator line animation
        $('.transit-hub-item').on('mouseenter', function() {
            const progress = $(this).data('progress');
            $('.animated-progress-bar').css('height', progress + '%');
        });

        // Intersection Observer scroll animation triggers
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, {
            threshold: 0.1
        });
        document.querySelectorAll('.reveal-on-scroll').forEach((el) => observer.observe(el));
    });
</script>

    <script>
        $(document).ready(function() {
            // Intersection Observer for Scroll Animation
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                threshold: 0.1
            });
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
                            if (elapsed < 0) {
                                requestAnimationFrame(updateCounter);
                                return;
                            }
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
            }, {
                threshold: 0.3
            });
            const trustSection = document.getElementById('trust-section');
            if (trustSection) counterObserver.observe(trustSection);

            // Setup sources array from PHP
            var sourcesList = <?= json_encode($sources) ?> || [];
            var destinationsList = [];

            // Helper function to create suggestion dropdown
            function setupAutocomplete($input, $hidden, listData, onSelect) {
                var wrapperClass = 'autocomplete-wrapper';
                var suggestionsClass = 'autocomplete-suggestions';
                var suggestionClass = 'autocomplete-suggestion';

                $input.on('focus click input', function() {
                    var val = $(this).val().toLowerCase();
                    var $wrapper = $(this).closest('.' + wrapperClass);
                    
                    // Remove existing suggestions
                    $wrapper.find('.' + suggestionsClass).remove();

                    // Filter list
                    var filtered = listData.filter(function(item) {
                        return item.toLowerCase().indexOf(val) > -1;
                    });

                    if (filtered.length === 0) return;

                    var $suggestions = $('<div class="' + suggestionsClass + '"></div>');
                    $.each(filtered, function(i, item) {
                        var $sug = $('<div class="' + suggestionClass + '">' + item + '</div>');
                        $sug.on('mousedown', function(e) {
                            e.preventDefault(); // prevent blur
                            $input.val(item);
                            $hidden.val(item).trigger('change');
                            $wrapper.find('.' + suggestionsClass).remove();
                            if (onSelect) onSelect(item);
                        });
                        $suggestions.append($sug);
                    });
                    $wrapper.append($suggestions);
                });

                $input.on('blur', function() {
                    setTimeout(function() {
                        $input.closest('.' + wrapperClass).find('.' + suggestionsClass).remove();
                    }, 200);
                });
            }

            // Init Autocomplete for Source input
            setupAutocomplete($('#source_search'), $('#source'), sourcesList, function(selectedSource) {
                loadDestinations(selectedSource);
            });

            // Init Autocomplete for Destination input
            setupAutocomplete($('#destination_search'), $('#destination'), destinationsList);

            // Function to load destinations via AJAX
            function loadDestinations(source, callback) {
                var $destInput = $('#destination_search');
                var $destHidden = $('#destination');
                var $loading = $('#dest-loading');
                var $empty = $('#dest-empty');

                // Reset
                $destInput.val('');
                $destHidden.val('');
                $destInput.prop('disabled', true);
                destinationsList = [];
                
                $loading.hide();
                $empty.hide();

                if (!source) {
                    return;
                }

                $loading.show();

                $.getJSON('<?= BASE_URL ?>/ajax/get_destinations.php', {
                    source: source
                }, function(data) {
                    $loading.hide();

                    if (data.length === 0) {
                        $empty.show();
                        return;
                    }

                    destinationsList = data;
                    $destInput.prop('disabled', false);
                    
                    // Re-init setup with updated destinationsList
                    setupAutocomplete($destInput, $destHidden, destinationsList);
                    
                    if (callback) callback();
                }).fail(function() {
                    $loading.hide();
                    $destInput.val('Error loading routes');
                });
            }

            // City Swapper
            $('#swapCities').on('click', function() {
                var srcVal = $('#source').val();
                var destVal = $('#destination').val();

                if (!destVal) return;

                $('#source_search').val(destVal);
                $('#source').val(destVal);

                loadDestinations(destVal, function() {
                    $('#destination_search').val(srcVal);
                    $('#destination').val(srcVal);
                });
            });
        });
    </script>

    <?php
    require_once __DIR__ . '/includes/footer.php';
    ?>