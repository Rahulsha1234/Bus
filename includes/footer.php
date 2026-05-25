    </div> <!-- End Container -->

    <footer class="footer-swift mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-4 mb-md-0">
                    <h5 class="text-white mb-3 d-flex align-items-center">
                        <i class="fa-solid fa-bus text-indigo me-2" style="color: #818cf8;"></i>
                        <span><?= SYSTEM_NAME ?></span>
                    </h5>
                    <p class="text-secondary small">
                        Providing safe, reliable, and premium bus ticketing systems across the country. Enjoy instant bookings, customizable seat arrangements, and robust agency features.
                    </p>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h6 class="text-white mb-3">Quick Navigation</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?= BASE_URL ?>/index.php" class="footer-link small">Search Buses</a></li>
                        <li><a href="<?= BASE_URL ?>/login.php" class="footer-link small">Staff Login</a></li>
                        <li><a href="<?= BASE_URL ?>/register.php" class="footer-link small">Agent Registration</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6 class="text-white mb-3">Support & Security</h6>
                    <p class="small text-secondary mb-1">
                        <i class="fa-solid fa-shield-halved text-indigo me-2" style="color: #818cf8;"></i>Role-Based Security
                    </p>
                    <p class="small text-secondary mb-1">
                        <i class="fa-solid fa-lock text-indigo me-2" style="color: #818cf8;"></i>Prepared SQL Statements
                    </p>
                    <p class="small text-secondary">
                        <i class="fa-solid fa-credit-card text-indigo me-2" style="color: #818cf8;"></i>Secured Payments
                    </p>
                </div>
            </div>
            <hr class="border-secondary my-4">
            <div class="row">
                <div class="col-md-6 text-center text-md-start small">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars(SYSTEM_NAME) ?>. All rights reserved.
                </div>
                <div class="col-md-6 text-center text-md-end small mt-2 mt-md-0">
                    Designed for visual excellence & premium security.
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
