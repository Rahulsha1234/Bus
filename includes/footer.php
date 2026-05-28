    </div> <!-- End Container -->

    <!-- Premium Light/Dark Footer Section with Rounded Top Corners -->
    <footer class="footer-swift mt-auto" style="border-radius: 2.5rem 2.5rem 0 0; padding: 4.5rem 0 2.5rem 0; box-shadow: 0 -15px 30px rgba(0, 0, 0, 0.02);">
        <div class="container">
            <div class="row g-5">
                <!-- Branding and info -->
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5 class="mb-3 d-flex align-items-center" style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; letter-spacing: -0.5px;">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-success me-2" style="width: 36px; height: 36px; background: rgba(25,135,84,0.1) !important;">
                            <i class="fa-solid fa-bus text-success" style="color: #198754 !important;"></i>
                        </span>
                        <span class="text-dark" style="color: #1f2937 !important;"><?= htmlspecialchars(SYSTEM_NAME) ?></span>
                    </h5>
                    <p class="small mb-4" style="line-height: 1.6; color: #6b7280;">
                        Providing safe, highly reliable, and premium bus ticketing systems across India. Experience the next level of intercity travel with smart routing and luxury amenities.
                    </p>
                    
                    <!-- Social Media Links -->
                    <div class="d-flex gap-2">
                        <a href="#" class="social-btn d-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); color: #4b5563; transition: all 0.3s ease;">
                            <i class="fa-brands fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-btn d-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); color: #4b5563; transition: all 0.3s ease;">
                            <i class="fa-brands fa-twitter"></i>
                        </a>
                        <a href="#" class="social-btn d-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); color: #4b5563; transition: all 0.3s ease;">
                            <i class="fa-brands fa-instagram"></i>
                        </a>
                        <a href="#" class="social-btn d-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); color: #4b5563; transition: all 0.3s ease;">
                            <i class="fa-brands fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Quick links -->
                <div class="col-6 col-lg-2 offset-lg-1">
                    <h6 class="text-dark mb-3 text-uppercase tracking-wider small fw-bold" style="color: #1f2937 !important; font-family: 'Plus Jakarta Sans', sans-serif;">Quick Navigation</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2">
                        <li><a href="<?= BASE_URL ?>/index.php" class="footer-link small text-decoration-none" style="color: #6b7280; transition: color 0.2s ease;"><i class="fa-solid fa-chevron-right me-1 small" style="font-size: 0.7rem;"></i> Search Buses</a></li>
                        <li><a href="<?= BASE_URL ?>/login.php" class="footer-link small text-decoration-none" style="color: #6b7280; transition: color 0.2s ease;"><i class="fa-solid fa-chevron-right me-1 small" style="font-size: 0.7rem;"></i> Login Portal</a></li>
                        <li><a href="<?= BASE_URL ?>/register.php" class="footer-link small text-decoration-none" style="color: #6b7280; transition: color 0.2s ease;"><i class="fa-solid fa-chevron-right me-1 small" style="font-size: 0.7rem;"></i> Agent Join</a></li>
                    </ul>
                </div>
                
                <!-- Features -->
                <div class="col-6 col-lg-2">
                    <h6 class="text-dark mb-3 text-uppercase tracking-wider small fw-bold" style="color: #1f2937 !important; font-family: 'Plus Jakarta Sans', sans-serif;">Key Features</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2" style="color: #6b7280;">
                        <li class="small"><i class="fa-solid fa-check text-success me-2"></i> Seat Selection</li>
                        <li class="small"><i class="fa-solid fa-check text-success me-2"></i> Instant Booking</li>
                        <li class="small"><i class="fa-solid fa-check text-success me-2"></i> Cancel & Refund</li>
                    </ul>
                </div>
                
                <!-- Support & Security -->
                <div class="col-lg-3">
                    <h6 class="text-dark mb-3 text-uppercase tracking-wider small fw-bold" style="color: #1f2937 !important; font-family: 'Plus Jakarta Sans', sans-serif;">Support & Security</h6>
                    <div class="d-flex flex-column gap-2">
                        <p class="small mb-1 d-flex align-items-center" style="color: #6b7280;">
                            <i class="fa-solid fa-shield-halved text-success me-2"></i> Role-Based Access Controls
                        </p>
                        <p class="small mb-1 d-flex align-items-center" style="color: #6b7280;">
                            <i class="fa-solid fa-lock text-success me-2"></i> Encrypted SQL Transactions
                        </p>
                        <p class="small d-flex align-items-center" style="color: #6b7280;">
                            <i class="fa-solid fa-credit-card text-success me-2"></i> Secure Payment Gateways
                        </p>
                    </div>
                </div>
            </div>
            
            <hr class="my-4" style="border-color: rgba(0,0,0,0.05);">
            
            <!-- Lower footer -->
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start small" style="color: #9ca3af;">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars(SYSTEM_NAME) ?>. All rights reserved.
                </div>
                <div class="col-md-6 text-center text-md-end small mt-2 mt-md-0" style="color: #9ca3af;">
                    Designed for visual excellence & premium security.
                </div>
            </div>
        </div>
    </footer>

    <!-- Style additions for footer hover states -->
    <style>
        .footer-link:hover {
            color: #198754 !important;
            padding-left: 3px;
        }
        .social-btn:hover {
            background: rgba(25, 135, 84, 0.1) !important;
            border-color: #198754 !important;
            color: #198754 !important;
            transform: translateY(-3px);
        }
    </style>

    <!-- Dynamic Ticker Announcement -->
    <?php if (!empty($GLOBALS['custom_notice'])): ?>
        <div class="notice-marquee">
            <span><i class="fa-solid fa-bullhorn text-warning me-2"></i><?= htmlspecialchars($GLOBALS['custom_notice']) ?></span>
        </div>
    <?php endif; ?>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Searchable Combobox JS -->
    <script src="<?= BASE_URL ?>/assets/js/combobox.js"></script>
    <script>
        $(document).ready(function() {
            convertToSearchableCombobox('source', 'Select Origin...');
            convertToSearchableCombobox('destination', 'Select Destination...');
        });
    </script>
</body>
</html>
