<?php
/**
 * Shared PDF/Print Template System - SwiftBus Design Language
 */

// Global branding constants
if (!defined('PDF_COLOR_PRIMARY')) define('PDF_COLOR_PRIMARY', '#0F5132');      // Primary Green
if (!defined('PDF_COLOR_HOVER')) define('PDF_COLOR_HOVER', '#146C43');          // Hover Green
if (!defined('PDF_COLOR_CREAM')) define('PDF_COLOR_CREAM', '#FAF7F0');          // Cream Background
if (!defined('PDF_COLOR_CARD')) define('PDF_COLOR_CARD', '#FFFDF8');            // Card Background
if (!defined('PDF_COLOR_GOLD')) define('PDF_COLOR_GOLD', '#D4AF37');            // Gold Accent
if (!defined('PDF_COLOR_TEXT_MAIN')) define('PDF_COLOR_TEXT_MAIN', '#1F2937');  // Primary Text
if (!defined('PDF_COLOR_TEXT_MUTED')) define('PDF_COLOR_TEXT_MUTED', '#6B7280'); // Secondary Text

/**
 * Output the premium HTML head and CSS styles for a standardized PDF/print page.
 */
function render_pdf_head($title) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <!-- Google Fonts Outfit & JetBrains Mono -->
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
        <!-- FontAwesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            :root {
                --pdf-bg: <?= PDF_COLOR_CREAM ?>;
                --pdf-surface: <?= PDF_COLOR_CARD ?>;
                --pdf-primary: <?= PDF_COLOR_PRIMARY ?>;
                --pdf-hover: <?= PDF_COLOR_HOVER ?>;
                --pdf-gold: <?= PDF_COLOR_GOLD ?>;
                --pdf-text: <?= PDF_COLOR_TEXT_MAIN ?>;
                --pdf-muted: <?= PDF_COLOR_TEXT_MUTED ?>;
                --pdf-border: rgba(15, 81, 50, 0.12);
            }
            body {
                background-color: var(--pdf-bg);
                color: var(--pdf-text);
                font-family: 'Outfit', sans-serif;
                font-size: 0.95rem;
                padding: 40px 20px;
                line-height: 1.5;
            }
            .pdf-container {
                max-width: 850px;
                margin: 0 auto;
                background-color: var(--pdf-surface);
                border: 1px solid var(--pdf-border);
                border-radius: 20px;
                box-shadow: 0 15px 30px rgba(15, 81, 50, 0.04);
                padding: 45px;
                position: relative;
            }
            
            /* Header */
            .pdf-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                border-bottom: 2px solid var(--pdf-border);
                padding-bottom: 25px;
                margin-bottom: 30px;
            }
            .pdf-logo {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .pdf-logo i {
                color: var(--pdf-primary);
                font-size: 2.2rem;
            }
            .pdf-brand {
                font-size: 2rem;
                font-weight: 800;
                color: var(--pdf-primary);
                letter-spacing: -0.5px;
            }
            .pdf-brand span {
                color: var(--pdf-gold);
            }
            .pdf-header-meta {
                text-align: right;
            }
            .pdf-title {
                font-size: 1.6rem;
                font-weight: 700;
                color: var(--pdf-primary);
                margin-bottom: 5px;
            }
            
            /* Section Titles */
            .pdf-section-title {
                font-size: 0.85rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 1.2px;
                color: var(--pdf-primary);
                border-bottom: 2px solid var(--pdf-border);
                padding-bottom: 6px;
                margin-top: 25px;
                margin-bottom: 18px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .pdf-section-title i {
                color: var(--pdf-gold);
            }

            /* Info Card */
            .pdf-info-card {
                background: #FFFFFF;
                border: 1px solid var(--pdf-border);
                border-radius: 12px;
                padding: 20px;
                height: 100%;
            }
            .info-label {
                font-size: 0.75rem;
                color: var(--pdf-muted);
                text-transform: uppercase;
                letter-spacing: 0.8px;
                font-weight: 600;
                margin-bottom: 3px;
            }
            .info-value {
                font-weight: 600;
                color: var(--pdf-text);
            }
            .info-value.mono {
                font-family: 'JetBrains Mono', monospace;
                color: var(--pdf-primary);
            }

            /* Tables */
            .pdf-table {
                width: 100%;
                margin-bottom: 20px;
                border-collapse: collapse;
            }
            .pdf-table th {
                font-size: 0.8rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #FFFFFF;
                background-color: var(--pdf-primary);
                padding: 12px 15px;
                border: 1px solid var(--pdf-border);
            }
            .pdf-table td {
                padding: 12px 15px;
                border: 1px solid var(--pdf-border);
                background-color: #FFFFFF;
                vertical-align: middle;
            }
            .pdf-table tr:nth-child(even) td {
                background-color: var(--pdf-bg);
            }

            /* Summary Block */
            .pdf-summary-box {
                background: #FFFFFF;
                border: 1px solid var(--pdf-border);
                border-radius: 12px;
                padding: 20px;
                display: inline-block;
                min-width: 250px;
            }
            .pdf-summary-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
                font-size: 0.9rem;
            }
            .pdf-summary-total {
                display: flex;
                justify-content: space-between;
                border-top: 1px solid var(--pdf-border);
                padding-top: 8px;
                margin-top: 8px;
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--pdf-primary);
            }

            /* Footer */
            .pdf-footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid var(--pdf-border);
                text-align: center;
                font-size: 0.8rem;
                color: var(--pdf-muted);
            }

            /* Print Bar */
            .print-btn-bar {
                max-width: 850px;
                margin: 0 auto 20px auto;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .btn-pdf-primary {
                background-color: var(--pdf-primary);
                color: #FFFFFF;
                border: none;
                padding: 10px 24px;
                border-radius: 10px;
                font-weight: 600;
                transition: all 0.2s ease;
            }
            .btn-pdf-primary:hover {
                background-color: var(--pdf-hover);
                color: #FFFFFF;
            }
            .btn-pdf-outline {
                border: 1px solid var(--pdf-border);
                color: var(--pdf-muted);
                background: #FFFFFF;
                padding: 10px 24px;
                border-radius: 10px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s ease;
            }
            .btn-pdf-outline:hover {
                background-color: var(--pdf-bg);
                color: var(--pdf-text);
            }

            /* ── Stats row: always 3 columns side-by-side ── */
            .stats-row {
                display: flex !important;
                flex-direction: row !important;
                gap: 16px !important;
                margin-bottom: 24px !important;
            }
            .stats-row .stat-card {
                flex: 1 1 0 !important;
                background: #FFFFFF;
                border: 1px solid var(--pdf-border);
                border-radius: 12px;
                padding: 20px;
                text-align: center;
            }

            @media print {
                body {
                    background: #FFFFFF !important;
                    padding: 0 !important;
                }
                .pdf-container {
                    border: none !important;
                    box-shadow: none !important;
                    padding: 20px !important;
                }
                .print-btn-bar {
                    display: none !important;
                }
                /* Force 3-column stats in print */
                .stats-row {
                    display: flex !important;
                    flex-direction: row !important;
                    gap: 12px !important;
                    page-break-inside: avoid !important;
                }
                .stats-row .stat-card {
                    flex: 1 1 0 !important;
                    page-break-inside: avoid !important;
                }
                /* Prevent Bootstrap from collapsing cols to 100% in print */
                .row { display: flex !important; flex-wrap: wrap !important; }
                .col-md-4 { flex: 0 0 33.333% !important; max-width: 33.333% !important; }
                .col-md-6 { flex: 0 0 50% !important; max-width: 50% !important; }
            }
        </style>
    </head>
    <body>
    <?php
}

/**
 * Renders the top Toolbar for printing/exporting.
 */
function render_pdf_toolbar($back_url = '../index.php', $additional_buttons = '') {
    ?>
    <div class="print-btn-bar no-print">
        <a href="<?= htmlspecialchars($back_url) ?>" class="btn-pdf-outline"><i class="fa-solid fa-arrow-left me-2"></i>Back</a>
        <div class="d-flex gap-2">
            <?= $additional_buttons ?>
            <button onclick="window.print()" class="btn-pdf-primary"><i class="fa-solid fa-print me-2"></i>Print / Save PDF</button>
        </div>
    </div>
    <?php
}

/**
 * Standard PDF Header.
 */
function render_pdf_header($doc_title, $meta_label, $meta_value) {
    ?>
    <div class="pdf-header">
        <div class="pdf-logo">
            <i class="fa-solid fa-bus"></i>
            <div>
                <div class="pdf-brand">Swift<span>Bus</span></div>
                <div style="font-size: 0.75rem; color: var(--pdf-muted); letter-spacing: 0.8px; font-weight: 600; font-family: 'JetBrains Mono', monospace;">PREMIUM TRAVEL SOLUTIONS</div>
            </div>
        </div>
        <div class="pdf-header-meta">
            <div class="pdf-title"><?= htmlspecialchars($doc_title) ?></div>
            <div class="info-label"><?= htmlspecialchars($meta_label) ?></div>
            <div class="info-value font-monospace" style="color: var(--pdf-primary);"><?= htmlspecialchars($meta_value) ?></div>
        </div>
    </div>
    <?php
}

/**
 * Standard PDF Section Title.
 */
function render_pdf_section_title($title, $icon_class = '') {
    ?>
    <div class="pdf-section-title">
        <?php if (!empty($icon_class)): ?>
            <i class="<?= htmlspecialchars($icon_class) ?>"></i>
        <?php endif; ?>
        <span><?= htmlspecialchars($title) ?></span>
    </div>
    <?php
}

/**
 * Standard PDF Footer.
 */
function render_pdf_footer() {
    ?>
        <div class="pdf-footer">
            <div class="mb-1"><strong>SwiftBus Systems Limited</strong> | Website: www.swiftbus-travels.com | Support: support@swiftbus.com</div>
            <div>Generated on: <?= date('d M Y, H:i:s') ?> | System Ticket Invoice Copy</div>
        </div>
    </div>
    </body>
    </html>
    <?php
}
