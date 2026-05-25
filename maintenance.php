<?php
require_once __DIR__ . '/config/config.php';

// Verify if maintenance mode is active. If not, redirect to homepage.
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
    $mode = $stmt->fetchColumn();
    if ($mode !== '1') {
        header("Location: " . BASE_URL . "/index.php");
        exit();
    }
    
    $stmt_notice = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'custom_notice'");
    $custom_notice = $stmt_notice->fetchColumn();
} catch (Exception $e) {
    $custom_notice = "The system is currently undergoing scheduled maintenance.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - <?= SYSTEM_NAME ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f8fafc;
            overflow-x: hidden;
            position: relative;
        }

        /* Decorative glowing circles */
        .glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            z-index: 1;
            opacity: 0.15;
        }
        .glow-1 {
            width: 400px;
            height: 400px;
            background: #4f46e5;
            top: 10%;
            left: 10%;
        }
        .glow-2 {
            width: 500px;
            height: 500px;
            background: #db2777;
            bottom: 10%;
            right: 10%;
        }

        .maintenance-card {
            background: rgba(30, 41, 59, 0.45);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 3rem;
            max-width: 600px;
            width: 90%;
            z-index: 10;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
            animation: fadeInUp 0.8s ease-out;
        }

        .icon-container {
            width: 100px;
            height: 100px;
            background: rgba(79, 70, 229, 0.1);
            border: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            position: relative;
        }

        .icon-container i {
            font-size: 3rem;
            color: #818cf8;
            animation: pulse 2s infinite ease-in-out;
        }

        .bus-wheel {
            position: absolute;
            font-size: 1.2rem;
            color: #db2777;
            animation: spin 3s infinite linear;
            bottom: 15px;
            right: 15px;
        }

        h1 {
            font-weight: 800;
            background: linear-gradient(to right, #818cf8, #f472b6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
        }

        .notice-box {
            background: rgba(255, 255, 255, 0.04);
            border-left: 4px solid #db2777;
            padding: 1.2rem;
            border-radius: 8px;
            margin: 2rem 0;
            font-size: 1.1rem;
            line-height: 1.6;
            color: #cbd5e1;
        }

        .badge-maintenance {
            background: rgba(219, 39, 119, 0.1);
            color: #f472b6;
            border: 1px solid rgba(219, 39, 119, 0.2);
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        .btn-admin-login {
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            border: none;
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-admin-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px #4f46e5;
            color: white;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>

    <div class="maintenance-card">
        <div class="icon-container">
            <i class="fa-solid fa-bus-simple"></i>
            <i class="fa-solid fa-gear bus-wheel"></i>
        </div>
        
        <span class="badge-maintenance">System Offline</span>
        <h1>We'll Be Back Shortly!</h1>
        <p class="text-secondary">Our engineers are performing scheduled upgrades to enhance your travel experience. We apologize for any inconvenience caused.</p>
        
        <?php if (!empty($custom_notice)): ?>
            <div class="notice-box">
                <i class="fa-solid fa-bullhorn me-2 text-pink"></i>
                <strong>Notice:</strong> <?= htmlspecialchars($custom_notice) ?>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="<?= BASE_URL ?>/login.php" class="btn-admin-login">
                <i class="fa-solid fa-lock me-2"></i>Admin / Staff Login
            </a>
        </div>
        
        <div class="mt-4 text-secondary" style="font-size: 0.8rem;">
            &copy; <?= date('Y') ?> <?= htmlspecialchars(SYSTEM_NAME) ?>. All rights reserved.
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
