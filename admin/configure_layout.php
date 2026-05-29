<?php
/**
 * Visual Seating Layout Builder
 */
require_once __DIR__ . '/header.php';

$bus_id = intval($_GET['bus_id'] ?? 0);
if ($bus_id === 0) {
    header("Location: " . BASE_URL . "/admin/buses.php");
    exit();
}

// Verify bus ownership
$stmt = $pdo->prepare("SELECT * FROM buses WHERE id = ? AND admin_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$bus_id, $_SESSION['user_id']]);
$bus = $stmt->fetch();

if (!$bus) {
    die("Bus not found or access denied.");
}

$error = '';
$success = '';

// Handle Template Save / Apply / Delete Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token validation failed.";
    } else {
        $action = $_POST['action'];

        // SAVE TEMPLATE
        if ($action === 'save_template') {
            $template_name = trim($_POST['template_name'] ?? '');
            $rows = intval($_POST['rows_count'] ?? 10);
            $cols = intval($_POST['cols_count'] ?? 5);
            $layout_type = $_POST['layout_type'] ?? 'Mixed';
            $seats_data = $_POST['seats_data'] ?? '[]';

            if (empty($template_name)) {
                $error = "Please enter a template name.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO layout_templates (admin_id, template_name, rows_count, cols_count, layout_type, seats_data)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $template_name, $rows, $cols, $layout_type, $seats_data]);
                    $success = "Template '$template_name' saved successfully!";
                    log_activity($pdo, $_SESSION['user_id'], 'LAYOUT_TEMPLATE_SAVE', "Saved template: $template_name");
                } catch (Exception $e) {
                    $error = "Failed to save template: " . $e->getMessage();
                }
            }
        }

        // APPLY TEMPLATE
        elseif ($action === 'apply_template') {
            $template_id = intval($_POST['template_id'] ?? 0);
            
            try {
                $stmt = $pdo->prepare("SELECT * FROM layout_templates WHERE id = ? AND admin_id = ? LIMIT 1");
                $stmt->execute([$template_id, $_SESSION['user_id']]);
                $template = $stmt->fetch();

                if (!$template) {
                    $error = "Template not found or access denied.";
                } else {
                    $rows = intval($template['rows_count']);
                    $cols = intval($template['cols_count']);
                    $layout_type = $template['layout_type'];
                    $seats_data = json_decode($template['seats_data'], true);

                    $pdo->beginTransaction();

                    // Update bus layout meta
                    $stmt = $pdo->prepare("
                        INSERT INTO bus_layouts (bus_id, rows_count, cols_count, layout_type)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE rows_count = VALUES(rows_count), cols_count = VALUES(cols_count), layout_type = VALUES(layout_type)
                    ");
                    $stmt->execute([$bus_id, $rows, $cols, $layout_type]);

                    // Clear and insert seats
                    $stmt = $pdo->prepare("DELETE FROM bus_seats WHERE bus_id = ?");
                    $stmt->execute([$bus_id]);

                    $stmt = $pdo->prepare("
                        INSERT INTO bus_seats (bus_id, seat_number, row_pos, col_pos, seat_type, is_active, base_price)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    foreach ($seats_data as $seat) {
                        $stmt->execute([
                            $bus_id,
                            trim($seat['number']),
                            intval($seat['row']),
                            intval($seat['col']),
                            $seat['type'],
                            intval($seat['active']),
                            floatval($seat['price'])
                        ]);
                    }

                    $pdo->commit();
                    $success = "Template '{$template['template_name']}' successfully applied to this bus!";
                    log_activity($pdo, $_SESSION['user_id'], 'LAYOUT_TEMPLATE_APPLY', "Applied template ID $template_id to bus $bus_id");
                    
                    // JS redirect because headers already sent by header.php
                    $js_redirect = "configure_layout.php?bus_id=" . $bus_id . "&success=" . urlencode($success);
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Failed to apply template: " . $e->getMessage();
            }
        }

        // DELETE TEMPLATE
        elseif ($action === 'delete_template') {
            $template_id = intval($_POST['template_id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM layout_templates WHERE id = ? AND admin_id = ?");
                $stmt->execute([$template_id, $_SESSION['user_id']]);
                $success = "Template deleted successfully!";
                log_activity($pdo, $_SESSION['user_id'], 'LAYOUT_TEMPLATE_DELETE', "Deleted template ID $template_id");
            } catch (Exception $e) {
                $error = "Failed to delete template: " . $e->getMessage();
            }
        }

        // SAVE LAYOUT
        elseif ($action === 'save_layout') {
            $rows = intval($_POST['rows_count'] ?? 10);
            $cols = intval($_POST['cols_count'] ?? 5);
            $layout_type = $_POST['layout_type'] ?? 'Mixed';
            $seats_data = json_decode($_POST['seats_data'] ?? '[]', true);

            try {
                $pdo->beginTransaction();

                // 1. Save layout metadata
                $stmt = $pdo->prepare("
                    INSERT INTO bus_layouts (bus_id, rows_count, cols_count, layout_type)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE rows_count = VALUES(rows_count), cols_count = VALUES(cols_count), layout_type = VALUES(layout_type)
                ");
                $stmt->execute([$bus_id, $rows, $cols, $layout_type]);

                // 2. Clear old seats
                $stmt = $pdo->prepare("DELETE FROM bus_seats WHERE bus_id = ?");
                $stmt->execute([$bus_id]);

                // 3. Insert new seats
                $stmt = $pdo->prepare("
                    INSERT INTO bus_seats (bus_id, seat_number, row_pos, col_pos, seat_type, is_active, base_price)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($seats_data as $seat) {
                    $stmt->execute([
                        $bus_id,
                        trim($seat['number']),
                        intval($seat['row']),
                        intval($seat['col']),
                        $seat['type'],
                        intval($seat['active']),
                        floatval($seat['price'])
                    ]);
                }

                // Log activity
                log_activity($pdo, $_SESSION['user_id'], 'BUS_LAYOUT_SAVE', "Saved visual layout for Bus ID: $bus_id. Type: $layout_type, Rows: $rows, Cols: $cols, Seats count: " . count($seats_data));

                $pdo->commit();
                $success = "Visual seating layout saved successfully!";
                $js_redirect = "buses.php?success=" . urlencode($success);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to save layout: " . $e->getMessage();
            }
        }
    }
}

// Check GET success message
if (isset($_GET['success']) && empty($success)) {
    $success = $_GET['success'];
}

// Fetch existing layout settings
$layout_stmt = $pdo->prepare("SELECT * FROM bus_layouts WHERE bus_id = ? LIMIT 1");
$layout_stmt->execute([$bus_id]);
$layout = $layout_stmt->fetch();

if ($layout) {
    $rows_count = intval($layout['rows_count']);
    $cols_count = intval($layout['cols_count']);
    $layout_type = $layout['layout_type'];
} else {
    if (strpos($bus['bus_type'], 'Sleeper') !== false) {
        $rows_count = 8;
        $cols_count = 4;
        $layout_type = 'Sleeper';
    } else {
        $rows_count = 10;
        $cols_count = 5;
        $layout_type = 'Seater';
    }
}

// Fetch existing seats
$seats_stmt = $pdo->prepare("SELECT * FROM bus_seats WHERE bus_id = ?");
$seats_stmt->execute([$bus_id]);
$db_seats = $seats_stmt->fetchAll();

// Map seats into a JS readable array
$seats_json = [];
foreach ($db_seats as $s) {
    $seats_json[] = [
        'number' => $s['seat_number'],
        'row' => intval($s['row_pos']),
        'col' => intval($s['col_pos']),
        'type' => $s['seat_type'],
        'active' => intval($s['is_active']),
        'price' => floatval($s['base_price'])
    ];
}

// Fetch Agent's Saved Templates
$templates_stmt = $pdo->prepare("SELECT * FROM layout_templates WHERE admin_id = ? ORDER BY created_at DESC");
$templates_stmt->execute([$_SESSION['user_id']]);
$templates = $templates_stmt->fetchAll();
?>
<?php if (!empty($js_redirect)): ?>
<script>window.location.replace("<?= htmlspecialchars($js_redirect) ?>");</script>
<?php exit(); endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-3" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>


<div class="row g-4">
    <!-- Builder Controls -->
    <div class="col-md-4">
        <div class="glass-card p-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-sliders text-indigo me-2"></i>Layout Dimensions</h5>
            <div class="mb-3">
                <label class="form-label text-secondary small fw-semibold">Class Classification</label>
                <select id="layout_type" class="form-select form-control-swift">
                    <?php foreach (get_vehicle_classifications() as $val => $info): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $layout_type === $val ? 'selected' : '' ?>><?= htmlspecialchars($info['display']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label text-secondary small fw-semibold">Grid Rows</label>
                <select id="grid_rows" class="form-select form-control-swift"></select>
            </div>
            <div class="mb-3">
                <label class="form-label text-secondary small fw-semibold">Grid Columns</label>
                <select id="grid_cols" class="form-select form-control-swift"></select>
            </div>

            <hr class="border-secondary mb-4">

            <h5 class="fw-bold mb-3"><i class="fa-solid fa-circle-info text-indigo me-2"></i>Instructions</h5>
            <ul class="small text-secondary ps-3 mb-4">
                <li class="mb-2">Click on an empty cell to add a seat.</li>
                <li class="mb-2">Drag a seat to duplicate it and auto-increment its number by +1.</li>
                <li class="mb-2">Click on a seat to customize its number, type, pricing, or status.</li>
                <li class="mb-2">For double berths (sleeper), align row placement and use prefixes U (Upper) and D (Lower).</li>
            </ul>

            <form action="" method="POST" id="layoutForm">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="save_layout">
                <input type="hidden" name="rows_count" id="form_rows" value="<?= $rows_count ?>">
                <input type="hidden" name="cols_count" id="form_cols" value="<?= $cols_count ?>">
                <input type="hidden" name="layout_type" id="form_layout_type" value="<?= $layout_type ?>">
                <input type="hidden" name="seats_data" id="form_seats_data">
                
                <button type="submit" id="btnSaveLayout" class="btn btn-primary-gradient w-100 py-3 font-semibold">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Visual Layout
                </button>
            </form>

            <hr class="border-secondary my-4">

            <!-- SAVE AS TEMPLATE FORM -->
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-file-export text-indigo me-2"></i>Save as Template</h5>
            <form action="" method="POST" id="templateSaveForm" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="rows_count" id="tpl_rows" value="<?= $rows_count ?>">
                <input type="hidden" name="cols_count" id="tpl_cols" value="<?= $cols_count ?>">
                <input type="hidden" name="layout_type" id="tpl_layout_type" value="<?= $layout_type ?>">
                <input type="hidden" name="seats_data" id="tpl_seats_data">
                
                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">Template Name</label>
                    <input type="text" name="template_name" class="form-control form-control-swift" placeholder="e.g. Sleeper 2x1 Premium" required>
                </div>
                <button type="submit" id="btnSaveAsTemplate" class="btn btn-secondary-glass w-100 font-semibold">
                    <i class="fa-solid fa-cloud-arrow-up me-2"></i>Save Template
                </button>
            </form>

            <hr class="border-secondary mb-4">

            <!-- APPLY SAVED TEMPLATES -->
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-folder-open text-indigo me-2"></i>Saved Templates</h5>
            <?php if (empty($templates)): ?>
                <p class="text-secondary small">No templates saved yet.</p>
            <?php else: ?>
                <div class="d-grid gap-2">
                    <?php foreach ($templates as $tpl): ?>
                        <div class="d-flex gap-2">
                            <form action="" method="POST" class="flex-grow-1">
                                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                <input type="hidden" name="action" value="apply_template">
                                <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">
                                <button type="submit" class="btn btn-secondary-glass btn-sm w-100 text-start d-flex justify-content-between align-items-center">
                                    <span><?= htmlspecialchars($tpl['template_name']) ?> <small class="text-muted">(<?= $tpl['rows_count'] ?>x<?= $tpl['cols_count'] ?>)</small></span>
                                    <i class="fa-solid fa-chevron-right text-muted small"></i>
                                </button>
                            </form>
                            <form action="" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                <input type="hidden" name="action" value="delete_template">
                                <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">
                                <button type="submit" class="btn btn-danger-glass btn-sm h-100" title="Delete Template">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr class="border-secondary my-4">
            
            <a href="buses.php" class="btn btn-secondary-glass w-100">Back to Fleet</a>
        </div>
    </div>

    <!-- Seating Canvas Grid -->
    <div class="col-md-8">
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary border-opacity-30">
                <div>
                    <h4 class="fw-bold text-white mb-0"><?= htmlspecialchars($bus['bus_name']) ?></h4>
                    <span class="text-secondary small">Registration No: <?= htmlspecialchars($bus['bus_number']) ?></span>
                </div>
                <div class="legend-item"><span class="legend-dot bg-secondary border border-secondary"></span><span class="small text-secondary">Walkway/Empty space</span></div>
            </div>

            <!-- Canvas Grid Container -->
            <div class="text-center overflow-auto py-3">
                <div id="grid-canvas" class="mx-auto grid-canvas-board" style="display: inline-grid; gap: 10px; padding: 15px; border-radius: 12px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- SEAT DETAIL MODAL -->
<div class="modal fade" id="seatConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card p-4" style="border-radius: 20px;">
            <div class="modal-header border-bottom border-secondary border-opacity-20 pb-3">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-chair text-indigo me-2"></i>Configure Seat</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <input type="hidden" id="modal_seat_row">
                <input type="hidden" id="modal_seat_col">

                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">Seat Number / Designation</label>
                    <input type="text" id="modal_seat_number" class="form-control form-control-swift" placeholder="e.g. A1, U1, D1" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">Seat Type</label>
                    <select id="modal_seat_type" class="form-select form-control-swift">
                        <option value="Normal">Normal Seat</option>
                        <option value="Sleeper">Sleeper</option>
                        <option value="Upper Sleeper">Upper Sleeper</option>
                        <option value="Lower Sleeper">Lower Sleeper</option>
                        <option value="Double Sleeper Upper">Double Sleeper Upper (2 Pass)</option>
                        <option value="Double Sleeper Lower">Double Sleeper Lower (2 Pass)</option>
                    </select>
                </div>

                <input type="hidden" id="modal_seat_price" value="500.00">

                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">Seat Status</label>
                    <select id="modal_seat_active" class="form-select form-control-swift">
                        <option value="1">Enabled (Available for booking)</option>
                        <option value="0">Disabled (Blocked space)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 pt-3 d-flex justify-content-between">
                <button type="button" class="btn btn-secondary-glass" id="btnRemoveSeat">Remove Seat</button>
                <div>
                    <button type="button" class="btn btn-secondary-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary-gradient" id="btnApplySeat">Apply Details</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Grid canvas adapts to theme */
.grid-canvas-board {
    background: var(--grid-canvas-bg);
    border: 1px solid var(--border-glass);
}

/* Light theme canvas background */
:root {
    --grid-canvas-bg: rgba(44, 44, 44, 0.06);
    --grid-cell-border: rgba(92, 92, 92, 0.45);
}
[data-theme="dark"] {
    --grid-canvas-bg: rgba(0, 0, 0, 0.35);
    --grid-cell-border: rgba(255, 255, 255, 0.18);
}

.grid-cell {
    width: 60px;
    height: 60px;
    border: 1.5px dashed var(--grid-cell-border);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: transparent;
    transition: all 0.2s ease;
}
.grid-cell:hover {
    background: rgba(200, 169, 107, 0.1);
    border-color: var(--accent-primary);
    border-style: solid;
}
.builder-seat {
    width: 100%;
    height: 100%;
    border-radius: 8px;
    border: 1.5px solid var(--accent-primary);
    background: var(--bg-card);
    color: var(--text-main);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    user-select: none;
    box-shadow: var(--shadow-main);
    cursor: grab;
}
.builder-seat.disabled-seat {
    opacity: 0.45;
    background: var(--bg-secondary);
    border-color: var(--border-glass);
}
.builder-seat .seat-type-badge {
    font-size: 0.55rem;
    color: var(--text-muted);
    font-weight: 500;
}
.builder-seat.sleeper-berth {
    height: 130px;
    z-index: 10;
    position: relative;
}
</style>

<script>
$(document).ready(function() {
    var seats = <?= json_encode($seats_json) ?>;
    var rows = <?= $rows_count ?>;
    var cols = <?= $cols_count ?>;

    // Render visual grid
    function renderGrid() {
        var canvas = $('#grid-canvas');
        canvas.empty();
        canvas.css({
            'grid-template-rows': 'repeat(' + rows + ', 60px)',
            'grid-template-columns': 'repeat(' + cols + ', 60px)'
        });

        // Map occupied cells due to row-spanning sleepers (excluding Semi Sleeper)
        var occupied = {};
        seats.forEach(function(s) {
            var isSleeper = s.type.toLowerCase().indexOf('sleeper') !== -1 && s.type.toLowerCase().indexOf('semi') === -1;
            if (isSleeper) {
                occupied[(s.row + 1) + ',' + s.col] = true;
            }
        });

        for (var r = 0; r < rows; r++) {
            for (var c = 0; c < cols; c++) {
                if (occupied[r + ',' + c]) {
                    // Render empty, non-interactive grid slot spacer to maintain correct grid alignment
                    var spacer = $('<div class="grid-cell spacer-cell"></div>');
                    spacer.css({
                        'grid-row': (r + 1),
                        'grid-column': (c + 1),
                        'visibility': 'hidden',
                        'pointer-events': 'none'
                    });
                    canvas.append(spacer);
                    continue;
                }

                // Find seat at this position
                var seat = findSeat(r, c);
                var cell = $('<div class="grid-cell" data-row="' + r + '" data-col="' + c + '"></div>');
                cell.css({
                    'grid-row': (r + 1),
                    'grid-column': (c + 1)
                });

                if (seat) {
                    var activeClass = seat.active === 1 ? '' : ' disabled-seat';
                    var typeClass = ' type-' + seat.type.toLowerCase().replace(/ /g, '-');
                    var isSleeper = seat.type.toLowerCase().indexOf('sleeper') !== -1 && seat.type.toLowerCase().indexOf('semi') === -1;
                    if (isSleeper) {
                        cell.css({
                            'grid-row': (r + 1) + ' / span 2',
                            'height': '130px'
                        });
                    }
                    var sleeperClass = isSleeper ? ' sleeper-berth' : '';
                    var seatElement = $('<div class="builder-seat' + activeClass + typeClass + sleeperClass + '" draggable="true">' +
                        '<span>' + seat.number + '</span>' +
                        '<span class="seat-type-badge">' + seat.type + '</span>' +
                        '</div>');

                    seatElement.on('dragstart', handleDragStart(r, c));
                    cell.append(seatElement);
                } else {
                    cell.on('click', handleCellClick(r, c));
                }

                cell.on('dragover', function(e) { e.preventDefault(); });
                cell.on('drop', handleDrop(r, c));

                canvas.append(cell);
            }
        }
    }

    function findSeat(row, col) {
        return seats.find(s => s.row === row && s.col === col);
    }

    // Add new seat on click
    function handleCellClick(row, col) {
        return function() {
            // Automatically determine row prefix letter (A, B, C...)
            var rowLetter = String.fromCharCode(65 + row); // 0 -> A, 1 -> B...
            
            // Determine column sequence position within the row
            var colCountInRow = 1;
            for (var c = 0; c < col; c++) {
                if (findSeat(row, c)) {
                    colCountInRow++;
                }
            }
            
            var seatNum = rowLetter + colCountInRow;
            
            // Check if name already exists, if so fall back to simple row+col representation
            if (seats.find(s => s.number === seatNum)) {
                seatNum = rowLetter + (col + 1);
            }
            
            seats.push({
                number: seatNum,
                row: row,
                col: col,
                type: 'Normal',
                active: 1,
                price: 500.00
            });
            renderGrid();
        };
    }

    // Configure details modal trigger
    $(document).on('click', '.builder-seat', function(e) {
        e.stopPropagation();
        var cell = $(this).parent();
        var row = parseInt(cell.data('row'));
        var col = parseInt(cell.data('col'));
        var seat = findSeat(row, col);

        if (seat) {
            $('#modal_seat_row').val(row);
            $('#modal_seat_col').val(col);
            $('#modal_seat_number').val(seat.number);
            $('#modal_seat_type').val(seat.type);
            $('#modal_seat_price').val(seat.price);
            $('#modal_seat_active').val(seat.active);
            $('#seatConfigModal').modal('show');
        }
    });

    // Apply config changes
    $('#btnApplySeat').click(function() {
        var row = parseInt($('#modal_seat_row').val());
        var col = parseInt($('#modal_seat_col').val());
        var seat = findSeat(row, col);

        if (seat) {
            seat.number = $('#modal_seat_number').val();
            seat.type = $('#modal_seat_type').val();
            seat.price = parseFloat($('#modal_seat_price').val());
            seat.active = parseInt($('#modal_seat_active').val());
            $('#seatConfigModal').modal('hide');
            renderGrid();
        }
    });

    // Remove seat
    $('#btnRemoveSeat').click(function() {
        var row = parseInt($('#modal_seat_row').val());
        var col = parseInt($('#modal_seat_col').val());
        seats = seats.filter(s => !(s.row === row && s.col === col));
        $('#seatConfigModal').modal('hide');
        renderGrid();
    });

    // Drag-and-drop properties
    var dragSrcRow = null;
    var dragSrcCol = null;

    function isSleeper(type) {
        if (!type) return false;
        var t = type.toLowerCase();
        return t.indexOf('sleeper') !== -1 && t.indexOf('semi') === -1;
    }

    // Returns shadowed (row,col) keys — cells occupied by the lower half of a sleeper above
    function getShadowedCells() {
        var shadowed = {};
        seats.forEach(function(s) {
            if (isSleeper(s.type)) {
                shadowed[(s.row + 1) + ',' + s.col] = true;
            }
        });
        return shadowed;
    }

    function handleDragStart(row, col) {
        return function(e) {
            dragSrcRow = row;
            dragSrcCol = col;
            e.originalEvent.dataTransfer.setData('text/plain', '');
        };
    }

    function incrementSeatNumber(numStr) {
        var match = numStr.match(/^([A-Za-z\-]+)?(\d+)$/);
        if (match) {
            var prefix = match[1] || '';
            var num = parseInt(match[2]);
            return prefix + (num + 1);
        }
        return numStr + '2';
    }

    function handleDrop(row, col) {
        return function(e) {
            e.preventDefault();
            if (dragSrcRow === null || dragSrcCol === null) return;

            var srcSeat = findSeat(dragSrcRow, dragSrcCol);
            var destSeat = findSeat(row, col);

            // Check if drop target is shadowed by another sleeper
            var shadowed = getShadowedCells();
            if (shadowed[row + ',' + col]) {
                alert('This cell is occupied by the lower half of a sleeper above it. Choose a different cell.');
                dragSrcRow = null;
                dragSrcCol = null;
                return;
            }

            if (srcSeat && !destSeat) {
                var sleeperSeat = isSleeper(srcSeat.type);

                // Sleeper needs 2 rows — reject if no room below or if below cell is occupied/shadowed
                if (sleeperSeat) {
                    if (row + 1 >= rows) {
                        alert('A sleeper berth needs 2 row-heights. This is the last row — not enough space.');
                        dragSrcRow = null;
                        dragSrcCol = null;
                        return;
                    }
                    if (findSeat(row + 1, col)) {
                        alert('A sleeper berth needs 2 free consecutive vertical cells. The cell below is occupied.');
                        dragSrcRow = null;
                        dragSrcCol = null;
                        return;
                    }
                    if (shadowed[(row + 1) + ',' + col]) {
                        alert('A sleeper berth needs 2 free consecutive vertical cells. The cell below is occupied by another sleeper\'s shadow.');
                        dragSrcRow = null;
                        dragSrcCol = null;
                        return;
                    }
                }

                // Generate unique seat number (Copy behavior)
                var newNumber = incrementSeatNumber(srcSeat.number);
                while (seats.find(function(s) { return s.number === newNumber; })) {
                    newNumber = incrementSeatNumber(newNumber);
                }

                seats.push({
                    number: newNumber,
                    row: row,
                    col: col,
                    type: srcSeat.type,
                    active: srcSeat.active,
                    price: srcSeat.price
                });
            }

            dragSrcRow = null;
            dragSrcCol = null;
            renderGrid();
        };
    }

    function updateDropdownOptions(type, current_row, current_col) {
        var maxRows = type === 'Sleeper' ? 14 : 7;
        var maxCols = 5;

        // Populate rows
        var rowSelect = $('#grid_rows');
        rowSelect.empty();
        for (var i = 1; i <= maxRows; i++) {
            var selected = i === current_row ? ' selected' : '';
            rowSelect.append('<option value="' + i + '"' + selected + '>' + i + ' Rows</option>');
        }

        // Populate columns
        var colSelect = $('#grid_cols');
        colSelect.empty();
        for (var j = 1; j <= maxCols; j++) {
            var selected = j === current_col ? ' selected' : '';
            colSelect.append('<option value="' + j + '"' + selected + '>' + j + ' Columns</option>');
        }
    }

    // Grid resize update on input change at runtime instantly
    function updateGridDimensions() {
        var rVal = parseInt($('#grid_rows').val());
        var cVal = parseInt($('#grid_cols').val());
        if (isNaN(rVal) || rVal < 1) rVal = 1;
        if (isNaN(cVal) || cVal < 1) cVal = 1;
        rows = rVal;
        cols = cVal;
        // Clean seats outside the new range
        seats = seats.filter(s => s.row < rows && s.col < cols);
        renderGrid();
    }

    // Trigger update immediately when select dropdown changes
    $('#grid_rows, #grid_cols').on('change', updateGridDimensions);

    // Apply pre-configured layout presets dynamically based on class selection
    function applyLayoutPreset(type) {
        seats = []; // Clear existing seats
        
        if (type === 'Sleeper') {
            updateDropdownOptions('Sleeper', 14, 5);
            rows = 14;
            cols = 5;
            
            for (var r = 0; r < rows; r += 2) {
                var index = (r / 2) + 1;
                // Left side: Column 0 (Lower Sleeper), Column 1 (Upper Sleeper)
                seats.push({ number: 'L' + index, row: r, col: 0, type: 'Lower Sleeper', active: 1, price: 700.00 });
                seats.push({ number: 'U' + index, row: r, col: 1, type: 'Upper Sleeper', active: 1, price: 800.00 });
                // Col 2 is walkway
                // Right side: Column 3 (Double Sleeper Lower), Column 4 (Double Sleeper Upper)
                seats.push({ number: 'DL' + index, row: r, col: 3, type: 'Double Sleeper Lower', active: 1, price: 1000.00 });
                seats.push({ number: 'DU' + index, row: r, col: 4, type: 'Double Sleeper Upper', active: 1, price: 1100.00 });
            }
            
        } else if (type === 'Seater') {
            updateDropdownOptions('Seater', 7, 5);
            rows = 7;
            cols = 5;
            
            for (var r = 0; r < rows; r++) {
                var letter = String.fromCharCode(65 + r); // A, B, C...
                if (r === 6) { // Last row has all 5 seats, no walkway
                    seats.push({ number: letter + '1', row: r, col: 0, type: 'Normal', active: 1, price: 500.00 });
                    seats.push({ number: letter + '2', row: r, col: 1, type: 'Normal', active: 1, price: 500.00 });
                    seats.push({ number: letter + '3', row: r, col: 2, type: 'Normal', active: 1, price: 500.00 });
                    seats.push({ number: letter + '4', row: r, col: 3, type: 'Normal', active: 1, price: 500.00 });
                    seats.push({ number: letter + '5', row: r, col: 4, type: 'Normal', active: 1, price: 500.00 });
                } else {
                    seats.push({ number: letter + '1', row: r, col: 0, type: 'Normal', active: 1, price: 500.00 });
                    seats.push({ number: letter + '2', row: r, col: 1, type: 'Normal', active: 1, price: 500.00 });
                    // Col 2 is walkway
                    seats.push({ number: letter + '3', row: r, col: 3, type: 'Normal', active: 1, price: 500.00 });
                    seats.push({ number: letter + '4', row: r, col: 4, type: 'Normal', active: 1, price: 500.00 });
                }
            }
        }
        
        renderGrid();
    }

    // Trigger preset layout when changing Class Classification dropdown
    $('#layout_type').change(function() {
        applyLayoutPreset($(this).val());
    });

    // Submit Layout Form
    $('#layoutForm').submit(function() {
        $('#form_rows').val(rows);
        $('#form_cols').val(cols);
        $('#form_layout_type').val($('#layout_type').val());
        $('#form_seats_data').val(JSON.stringify(seats));
    });

    // Submit Template Save Form
    $('#templateSaveForm').submit(function() {
        $('#tpl_rows').val(rows);
        $('#tpl_cols').val(cols);
        $('#tpl_layout_type').val($('#layout_type').val());
        $('#tpl_seats_data').val(JSON.stringify(seats));
    });

    // Load dynamic defaults on new setups
    updateDropdownOptions($('#layout_type').val(), rows, cols);

    if (seats.length === 0) {
        applyLayoutPreset($('#layout_type').val());
    } else {
        renderGrid();
    }
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
