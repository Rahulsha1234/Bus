<?php
/**
 * Pricing Engine Management Console
 */
require_once __DIR__ . '/header.php';

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = "Security token mismatch.";
    } else {
        $action = $_POST['action'] ?? '';

        // 1. SAVE GLOBAL SETTINGS
        if ($action === 'save_settings') {
            $enable = intval($_POST['enable_dynamic_pricing'] ?? 0);
            $mode = $_POST['dynamic_pricing_mode'] ?? 'custom';

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO global_pricing_settings (operator_id, enable_dynamic_pricing, dynamic_pricing_mode)
                    VALUES (:operator_id, :enable, :mode)
                    ON DUPLICATE KEY UPDATE 
                        enable_dynamic_pricing = :enable_update, 
                        dynamic_pricing_mode = :mode_update
                ");
                $stmt->execute([
                    ':operator_id' => $admin_id,
                    ':enable' => $enable,
                    ':mode' => $mode,
                    ':enable_update' => $enable,
                    ':mode_update' => $mode
                ]);
                $success = "Global dynamic pricing settings updated successfully!";
                log_activity($pdo, $admin_id, 'PRICING_SETTINGS_UPDATE', "Updated pricing status: $enable, Mode: $mode");
            } catch (Exception $e) {
                $error = "Failed to save settings: " . $e->getMessage();
            }
        }

        // 2. OCCUPANCY RULES ACTIONS
        elseif ($action === 'add_occupancy_rule') {
            $min_occ = floatval($_POST['min_occupancy'] ?? 0);
            $max_occ = floatval($_POST['max_occupancy'] ?? 0);
            $increase = floatval($_POST['price_increase_percentage'] ?? 0);
            $order = intval($_POST['sort_order'] ?? 0);

            if ($min_occ < 0 || $max_occ > 100 || $min_occ > $max_occ) {
                $error = "Invalid occupancy range limits.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO occupancy_pricing_rules (operator_id, min_occupancy, max_occupancy, price_increase_percentage, sort_order, status)
                        VALUES (?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([$admin_id, $min_occ, $max_occ, $increase, $order]);
                    $success = "Occupancy rule created successfully.";
                    log_activity($pdo, $admin_id, 'PRICING_RULE_ADD', "Added occupancy rule: $min_occ - $max_occ% -> $increase%");
                } catch (Exception $e) {
                    $error = "Failed to add occupancy rule: " . $e->getMessage();
                }
            }
        }

        elseif ($action === 'edit_occupancy_rule') {
            $rule_id = intval($_POST['rule_id'] ?? 0);
            $min_occ = floatval($_POST['min_occupancy'] ?? 0);
            $max_occ = floatval($_POST['max_occupancy'] ?? 0);
            $increase = floatval($_POST['price_increase_percentage'] ?? 0);
            $order = intval($_POST['sort_order'] ?? 0);
            $status = $_POST['status'] ?? 'active';

            try {
                $stmt = $pdo->prepare("
                    UPDATE occupancy_pricing_rules 
                    SET min_occupancy = ?, max_occupancy = ?, price_increase_percentage = ?, sort_order = ?, status = ?
                    WHERE id = ? AND operator_id = ?
                ");
                $stmt->execute([$min_occ, $max_occ, $increase, $order, $status, $rule_id, $admin_id]);
                $success = "Occupancy rule modified.";
                log_activity($pdo, $admin_id, 'PRICING_RULE_EDIT', "Modified occupancy rule ID $rule_id");
            } catch (Exception $e) {
                $error = "Failed to edit rule: " . $e->getMessage();
            }
        }

        elseif ($action === 'delete_occupancy_rule') {
            $rule_id = intval($_POST['rule_id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM occupancy_pricing_rules WHERE id = ? AND operator_id = ?");
                $stmt->execute([$rule_id, $admin_id]);
                $success = "Occupancy rule deleted.";
                log_activity($pdo, $admin_id, 'PRICING_RULE_DELETE', "Deleted occupancy rule ID $rule_id");
            } catch (Exception $e) {
                $error = "Failed to delete rule: " . $e->getMessage();
            }
        }

        // 3. TIME RULES ACTIONS
        elseif ($action === 'add_time_rule') {
            $min_days = intval($_POST['min_days'] ?? 0);
            $max_days = intval($_POST['max_days'] ?? 0);
            $increase = floatval($_POST['price_increase_percentage'] ?? 0);
            $order = intval($_POST['sort_order'] ?? 0);

            if ($min_days < 0 || $min_days > $max_days) {
                $error = "Invalid time range parameters.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO time_pricing_rules (operator_id, min_days, max_days, price_increase_percentage, sort_order, status)
                        VALUES (?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([$admin_id, $min_days, $max_days, $increase, $order]);
                    $success = "Time rule created successfully.";
                    log_activity($pdo, $admin_id, 'TIME_RULE_ADD', "Added time rule: $min_days - $max_days days -> $increase%");
                } catch (Exception $e) {
                    $error = "Failed to add time rule: " . $e->getMessage();
                }
            }
        }

        elseif ($action === 'edit_time_rule') {
            $rule_id = intval($_POST['rule_id'] ?? 0);
            $min_days = intval($_POST['min_days'] ?? 0);
            $max_days = intval($_POST['max_days'] ?? 0);
            $increase = floatval($_POST['price_increase_percentage'] ?? 0);
            $order = intval($_POST['sort_order'] ?? 0);
            $status = $_POST['status'] ?? 'active';

            try {
                $stmt = $pdo->prepare("
                    UPDATE time_pricing_rules 
                    SET min_days = ?, max_days = ?, price_increase_percentage = ?, sort_order = ?, status = ?
                    WHERE id = ? AND operator_id = ?
                ");
                $stmt->execute([$min_days, $max_days, $increase, $order, $status, $rule_id, $admin_id]);
                $success = "Time rule modified.";
                log_activity($pdo, $admin_id, 'TIME_RULE_EDIT', "Modified time rule ID $rule_id");
            } catch (Exception $e) {
                $error = "Failed to edit time rule: " . $e->getMessage();
            }
        }

        elseif ($action === 'delete_time_rule') {
            $rule_id = intval($_POST['rule_id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM time_pricing_rules WHERE id = ? AND operator_id = ?");
                $stmt->execute([$rule_id, $admin_id]);
                $success = "Time rule deleted.";
                log_activity($pdo, $admin_id, 'TIME_RULE_DELETE', "Deleted time rule ID $rule_id");
            } catch (Exception $e) {
                $error = "Failed to delete time rule: " . $e->getMessage();
            }
        }
    }
}

// Fetch Active settings
$settings_stmt = $pdo->prepare("SELECT * FROM global_pricing_settings WHERE operator_id = ? LIMIT 1");
$settings_stmt->execute([$admin_id]);
$settings = $settings_stmt->fetch() ?: [
    'enable_dynamic_pricing' => 1,
    'dynamic_pricing_mode' => 'custom'
];

// Fetch Rules
$occ_stmt = $pdo->prepare("SELECT * FROM occupancy_pricing_rules WHERE operator_id = ? ORDER BY sort_order ASC, id DESC");
$occ_stmt->execute([$admin_id]);
$occ_rules = $occ_stmt->fetchAll();

$time_stmt = $pdo->prepare("SELECT * FROM time_pricing_rules WHERE operator_id = ? ORDER BY sort_order ASC, id DESC");
$time_stmt->execute([$admin_id]);
$time_rules = $time_stmt->fetchAll();
?>

<!-- Alerts -->
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

<!-- Tabs navigation -->
<ul class="nav nav-tabs border-secondary border-opacity-25 mb-4" id="pricingTab" role="tablist">
    <li class="nav-item">
        <button class="nav-link active text-white" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-pane" type="button" role="tab"><i class="fa-solid fa-sliders me-2"></i>Global Settings</button>
    </li>
    <li class="nav-item">
        <button class="nav-link text-white" id="occupancy-tab" data-bs-toggle="tab" data-bs-target="#occupancy-pane" type="button" role="tab"><i class="fa-solid fa-chart-bar me-2"></i>Occupancy Rules</button>
    </li>
    <li class="nav-item">
        <button class="nav-link text-white" id="time-tab" data-bs-toggle="tab" data-bs-target="#time-pane" type="button" role="tab"><i class="fa-solid fa-hourglass-half me-2"></i>Time Rules</button>
    </li>
    <li class="nav-item">
        <button class="nav-link text-white" id="simulator-tab" data-bs-toggle="tab" data-bs-target="#simulator-pane" type="button" role="tab"><i class="fa-solid fa-laptop-code me-2"></i>Pricing Simulator</button>
    </li>
</ul>

<div class="tab-content" id="pricingTabContent">
    <!-- 1. GLOBAL SETTINGS -->
    <div class="tab-pane fade show active" id="settings-pane" role="tabpanel">
        <div class="glass-card p-5 mt-2">
            <h5 class="text-white fw-bold mb-4">Dynamic Engine Configuration</h5>
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="save_settings">

                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Enable Dynamic Pricing Engine</label>
                    <select name="enable_dynamic_pricing" class="form-select form-control-swift" required>
                        <option value="1" <?= $settings['enable_dynamic_pricing'] == 1 ? 'selected' : '' ?>>Yes, modify prices dynamically</option>
                        <option value="0" <?= $settings['enable_dynamic_pricing'] == 0 ? 'selected' : '' ?>>No, keep fixed base fares</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Dynamic Pricing Mode Presets</label>
                    <select name="dynamic_pricing_mode" class="form-select form-control-swift" required>
                        <option value="custom" <?= $settings['dynamic_pricing_mode'] === 'custom' ? 'selected' : '' ?>>Custom Mode (Uses database rules below)</option>
                        <option value="conservative" <?= $settings['dynamic_pricing_mode'] === 'conservative' ? 'selected' : '' ?>>Conservative Preset (Up to +15% occupancy, +10% time)</option>
                        <option value="balanced" <?= $settings['dynamic_pricing_mode'] === 'balanced' ? 'selected' : '' ?>>Balanced Preset (Up to +50% occupancy, +30% time)</option>
                        <option value="aggressive" <?= $settings['dynamic_pricing_mode'] === 'aggressive' ? 'selected' : '' ?>>Aggressive Preset (Up to +75% occupancy, +50% time)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary-gradient py-2 px-4">Update Configuration</button>
            </form>
        </div>
    </div>

    <!-- 2. OCCUPANCY RULES -->
    <div class="tab-pane fade" id="occupancy-pane" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
            <h5 class="text-white fw-bold mb-0">Occupancy-based Price Escalation</h5>
            <button class="btn btn-primary-gradient py-1 px-3 small" data-bs-toggle="modal" data-bs-target="#addOccModal"><i class="fa-solid fa-plus me-2"></i>Add Occupancy Rule</button>
        </div>

        <div class="glass-card p-4">
            <?php if (empty($occ_rules)): ?>
                <div class="text-center py-5 text-secondary small">
                    <i class="fa-solid fa-chart-line mb-3 d-block" style="font-size: 3rem; color: #475569;"></i>
                    No custom occupancy rules configured yet. The system will use global system defaults.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-swift table-dark table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Min Occupancy %</th>
                                <th>Max Occupancy %</th>
                                <th>Price Increase %</th>
                                <th>Sort Order</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($occ_rules as $r): ?>
                                <tr>
                                    <td><span class="font-monospace text-white"><?= htmlspecialchars($r['min_occupancy']) ?>%</span></td>
                                    <td><span class="font-monospace text-white"><?= htmlspecialchars($r['max_occupancy']) ?>%</span></td>
                                    <td><span class="font-monospace text-success fw-bold">+<?= htmlspecialchars($r['price_increase_percentage']) ?>%</span></td>
                                    <td><span class="font-monospace text-secondary"><?= htmlspecialchars($r['sort_order']) ?></span></td>
                                    <td>
                                        <span class="badge bg-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?> border border-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?> border-opacity-25"><?= strtoupper($r['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group gap-2">
                                            <button class="btn btn-secondary-glass btn-sm edit-occ-btn" 
                                                data-id="<?= $r['id'] ?>"
                                                data-min="<?= htmlspecialchars($r['min_occupancy']) ?>"
                                                data-max="<?= htmlspecialchars($r['max_occupancy']) ?>"
                                                data-inc="<?= htmlspecialchars($r['price_increase_percentage']) ?>"
                                                data-order="<?= htmlspecialchars($r['sort_order']) ?>"
                                                data-status="<?= $r['status'] ?>"
                                                data-bs-toggle="modal" data-bs-target="#editOccModal"><i class="fa-solid fa-pen-to-square"></i></button>
                                            <button class="btn btn-secondary-glass btn-sm text-danger delete-occ-btn" data-id="<?= $r['id'] ?>" data-bs-toggle="modal" data-bs-target="#deleteOccModal"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. TIME RULES -->
    <div class="tab-pane fade" id="time-pane" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
            <h5 class="text-white fw-bold mb-0">Time-based Price Escalation</h5>
            <button class="btn btn-primary-gradient py-1 px-3 small" data-bs-toggle="modal" data-bs-target="#addTimeModal"><i class="fa-solid fa-plus me-2"></i>Add Time Rule</button>
        </div>

        <div class="glass-card p-4">
            <?php if (empty($time_rules)): ?>
                <div class="text-center py-5 text-secondary small">
                    <i class="fa-solid fa-clock mb-3 d-block" style="font-size: 3rem; color: #475569;"></i>
                    No custom time rules configured yet. The system will use global system defaults.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-swift table-dark table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Min Days Before Dep.</th>
                                <th>Max Days Before Dep.</th>
                                <th>Price Increase %</th>
                                <th>Sort Order</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($time_rules as $r): ?>
                                <tr>
                                    <td><span class="font-monospace text-white"><?= htmlspecialchars($r['min_days']) ?> Days</span></td>
                                    <td><span class="font-monospace text-white"><?= htmlspecialchars($r['max_days']) ?> Days</span></td>
                                    <td><span class="font-monospace text-success fw-bold">+<?= htmlspecialchars($r['price_increase_percentage']) ?>%</span></td>
                                    <td><span class="font-monospace text-secondary"><?= htmlspecialchars($r['sort_order']) ?></span></td>
                                    <td>
                                        <span class="badge bg-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?> border border-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?> border-opacity-25"><?= strtoupper($r['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group gap-2">
                                            <button class="btn btn-secondary-glass btn-sm edit-time-btn" 
                                                data-id="<?= $r['id'] ?>"
                                                data-min="<?= htmlspecialchars($r['min_days']) ?>"
                                                data-max="<?= htmlspecialchars($r['max_days']) ?>"
                                                data-inc="<?= htmlspecialchars($r['price_increase_percentage']) ?>"
                                                data-order="<?= htmlspecialchars($r['sort_order']) ?>"
                                                data-status="<?= $r['status'] ?>"
                                                data-bs-toggle="modal" data-bs-target="#editTimeModal"><i class="fa-solid fa-pen-to-square"></i></button>
                                            <button class="btn btn-secondary-glass btn-sm text-danger delete-time-btn" data-id="<?= $r['id'] ?>" data-bs-toggle="modal" data-bs-target="#deleteTimeModal"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4. SIMULATOR -->
    <div class="tab-pane fade" id="simulator-pane" role="tabpanel">
        <div class="row g-4 mt-2">
            <div class="col-md-5">
                <div class="glass-card p-4">
                    <h5 class="text-white fw-bold mb-4">Simulation Sandbox</h5>
                    <form id="simulatorForm">
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Base Fare (₹)</label>
                            <input type="number" id="sim_base_fare" class="form-control form-control-swift" value="500" min="50" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Total Active Seats</label>
                            <input type="number" id="sim_total_seats" class="form-control form-control-swift" value="40" min="10" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Seats Booked / Sold</label>
                            <input type="number" id="sim_seats_sold" class="form-control form-control-swift" value="28" min="0" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-semibold">Days Remaining to Departure</label>
                            <input type="number" id="sim_days_left" class="form-control form-control-swift" value="2" min="0" required>
                        </div>
                        <button type="button" id="btnRunSimulation" class="btn btn-primary-gradient w-100 py-2.5">Simulate Price Output</button>
                    </form>
                </div>
            </div>

            <div class="col-md-7">
                <div class="glass-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="text-white fw-bold mb-4">Calculated Outcome Preview</h5>
                        <div class="p-3 rounded-3 bg-dark bg-opacity-35 border border-secondary border-opacity-15 mb-3">
                            <div class="row">
                                <div class="col-6">
                                    <span class="text-secondary small d-block">Occupancy Level</span>
                                    <span class="text-white fw-bold font-monospace" id="out_occ_percent">70.00%</span>
                                </div>
                                <div class="col-6">
                                    <span class="text-secondary small d-block">Time Factor</span>
                                    <span class="text-white fw-bold font-monospace" id="out_days_left">2 Days Left</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 rounded-3 bg-dark bg-opacity-20 border border-secondary border-opacity-10 mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-secondary small">Base Ticket Fare:</span>
                                <span class="text-white font-monospace" id="out_base_fare">₹500.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-secondary small">Occupancy Price Scale adjustment:</span>
                                <span class="text-success font-monospace" id="out_occ_adjust">+₹100.00 (+20%)</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary small">Time Threshold adjustment:</span>
                                <span class="text-success font-monospace" id="out_time_adjust">+₹100.00 (+20%)</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-end pt-3 border-top border-secondary border-opacity-15">
                        <span class="text-secondary small d-block text-uppercase">Final Simulated Ticket Price</span>
                        <span class="display-5 fw-bold text-success font-monospace" id="out_final_price">₹700.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALS SECTION -->
<!-- Add Occupancy Modal -->
<div class="modal fade" id="addOccModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#111111; border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-plus me-2 text-indigo"></i>New Occupancy Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="add_occupancy_rule">

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Minimum Occupancy %</label>
                        <input type="number" name="min_occupancy" class="form-control form-control-swift" placeholder="e.g. 50" min="0" max="100" step="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Maximum Occupancy %</label>
                        <input type="number" name="max_occupancy" class="form-control form-control-swift" placeholder="e.g. 70" min="0" max="100" step="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Price Increase %</label>
                        <input type="number" name="price_increase_percentage" class="form-control form-control-swift" placeholder="e.g. 10" min="0" max="200" step="0.5" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Sort Evaluation Order</label>
                        <input type="number" name="sort_order" class="form-control form-control-swift" value="1" min="1" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-20 p-4">
                    <button type="button" class="btn btn-secondary-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gradient">Create Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Occupancy Modal -->
<div class="modal fade" id="editOccModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#111111; border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-pen-to-square me-2 text-indigo"></i>Modify Occupancy Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="edit_occupancy_rule">
                    <input type="hidden" name="rule_id" id="edit_occ_id">

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Minimum Occupancy %</label>
                        <input type="number" name="min_occupancy" id="edit_occ_min" class="form-control form-control-swift" min="0" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Maximum Occupancy %</label>
                        <input type="number" name="max_occupancy" id="edit_occ_max" class="form-control form-control-swift" min="0" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Price Increase %</label>
                        <input type="number" name="price_increase_percentage" id="edit_occ_inc" class="form-control form-control-swift" min="0" max="200" step="0.5" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Sort Evaluation Order</label>
                        <input type="number" name="sort_order" id="edit_occ_order" class="form-control form-control-swift" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Status</label>
                        <select name="status" id="edit_occ_status" class="form-select form-control-swift" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-20 p-4">
                    <button type="button" class="btn btn-secondary-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gradient">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Occupancy Modal -->
<div class="modal fade" id="deleteOccModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#111111; border-radius: 20px;">
            <form action="" method="POST">
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="delete_occupancy_rule">
                    <input type="hidden" name="rule_id" id="delete_occ_id">

                    <i class="fa-solid fa-trash-can text-danger mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold mb-2">Delete Rule?</h5>
                    <p class="text-secondary small">Are you sure you want to remove this occupancy pricing rule?</p>
                </div>
                <div class="modal-footer border-0 p-3 d-flex justify-content-around">
                    <button type="button" class="btn btn-secondary-glass w-45 py-2" data-bs-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger w-45 py-2">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Time Modal -->
<div class="modal fade" id="addTimeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#111111; border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-plus me-2 text-indigo"></i>New Time Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="add_time_rule">

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Minimum Days Before Departure</label>
                        <input type="number" name="min_days" class="form-control form-control-swift" placeholder="e.g. 1" min="0" max="365" step="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Maximum Days Before Departure</label>
                        <input type="number" name="max_days" class="form-control form-control-swift" placeholder="e.g. 3" min="0" max="365" step="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Price Increase %</label>
                        <input type="number" name="price_increase_percentage" class="form-control form-control-swift" placeholder="e.g. 20" min="0" max="200" step="0.5" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Sort Evaluation Order</label>
                        <input type="number" name="sort_order" class="form-control form-control-swift" value="1" min="1" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-20 p-4">
                    <button type="button" class="btn btn-secondary-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gradient">Create Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Time Modal -->
<div class="modal fade" id="editTimeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#111111; border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-pen-to-square me-2 text-indigo"></i>Modify Time Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="edit_time_rule">
                    <input type="hidden" name="rule_id" id="edit_time_id">

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Minimum Days Before Departure</label>
                        <input type="number" name="min_days" id="edit_time_min" class="form-control form-control-swift" min="0" max="365" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Maximum Days Before Departure</label>
                        <input type="number" name="max_days" id="edit_time_max" class="form-control form-control-swift" min="0" max="365" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Price Increase %</label>
                        <input type="number" name="price_increase_percentage" id="edit_time_inc" class="form-control form-control-swift" min="0" max="200" step="0.5" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Sort Evaluation Order</label>
                        <input type="number" name="sort_order" id="edit_time_order" class="form-control form-control-swift" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Status</label>
                        <select name="status" id="edit_time_status" class="form-select form-control-swift" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-20 p-4">
                    <button type="button" class="btn btn-secondary-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gradient">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Time Modal -->
<div class="modal fade" id="deleteTimeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#111111; border-radius: 20px;">
            <form action="" method="POST">
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="delete_time_rule">
                    <input type="hidden" name="rule_id" id="delete_time_id">

                    <i class="fa-solid fa-trash-can text-danger mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold mb-2">Delete Rule?</h5>
                    <p class="text-secondary small">Are you sure you want to remove this time pricing rule?</p>
                </div>
                <div class="modal-footer border-0 p-3 d-flex justify-content-around">
                    <button type="button" class="btn btn-secondary-glass w-45 py-2" data-bs-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger w-45 py-2">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Fill Occupancy rule edit details
    $('.edit-occ-btn').click(function() {
        $('#edit_occ_id').val($(this).data('id'));
        $('#edit_occ_min').val($(this).data('min'));
        $('#edit_occ_max').val($(this).data('max'));
        $('#edit_occ_inc').val($(this).data('inc'));
        $('#edit_occ_order').val($(this).data('order'));
        $('#edit_occ_status').val($(this).data('status'));
    });

    // Fill Occupancy delete details
    $('.delete-occ-btn').click(function() {
        $('#delete_occ_id').val($(this).data('id'));
    });

    // Fill Time rule edit details
    $('.edit-time-btn').click(function() {
        $('#edit_time_id').val($(this).data('id'));
        $('#edit_time_min').val($(this).data('min'));
        $('#edit_time_max').val($(this).data('max'));
        $('#edit_time_inc').val($(this).data('inc'));
        $('#edit_time_order').val($(this).data('order'));
        $('#edit_time_status').val($(this).data('status'));
    });

    // Fill Time delete details
    $('.delete-time-btn').click(function() {
        $('#delete_time_id').val($(this).data('id'));
    });

    // Run Simulator calculations
    function calculateSimulation() {
        var base_fare = parseFloat($('#sim_base_fare').val()) || 0;
        var total_seats = parseInt($('#sim_total_seats').val()) || 1;
        var seats_sold = parseInt($('#sim_seats_sold').val()) || 0;
        var days_left = parseInt($('#sim_days_left').val()) || 0;

        var occ_percent = (seats_sold / total_seats) * 100;
        $('#out_occ_percent').text(occ_percent.toFixed(2) + '%');
        $('#out_days_left').text(days_left + ' Days Left');
        $('#out_base_fare').text('₹' + base_fare.toFixed(2));

        // Selected Mode calculation mimicking PHP engine
        var mode = '<?= htmlspecialchars($settings['dynamic_pricing_mode']) ?>';
        var occ_pct_inc = 0;
        var time_pct_inc = 0;

        if (mode === 'conservative') {
            if (occ_percent > 90) occ_pct_inc = 15;
            else if (occ_percent > 80) occ_pct_inc = 10;
            else if (occ_percent > 50) occ_pct_inc = 5;

            if (days_left < 3) time_pct_inc = 10;
            else if (days_left <= 7) time_pct_inc = 5;
        } else if (mode === 'balanced') {
            if (occ_percent > 95) occ_pct_inc = 50;
            else if (occ_percent > 85) occ_pct_inc = 35;
            else if (occ_percent > 70) occ_pct_inc = 20;
            else if (occ_percent > 50) occ_pct_inc = 10;

            if (days_left == 0) time_pct_inc = 30;
            else if (days_left <= 2) time_pct_inc = 20;
            else if (days_left <= 7) time_pct_inc = 10;
        } else if (mode === 'aggressive') {
            if (occ_percent > 90) occ_pct_inc = 75;
            else if (occ_percent > 80) occ_pct_inc = 50;
            else if (occ_percent > 60) occ_pct_inc = 30;
            else if (occ_percent > 50) occ_pct_inc = 15;

            if (days_left == 0) time_pct_inc = 50;
            else if (days_left <= 2) time_pct_inc = 30;
            else if (days_left <= 7) time_pct_inc = 15;
        } else {
            // custom mode: Fetch rules dynamically from current DOM tables if possible,
            // or fallback to Balanced preset simulation logic for sandbox preview
            // Let's implement active table check
            var matched_occ = 0;
            $('#occupancy-pane tbody tr').each(function() {
                var min = parseFloat($(this).find('td:nth-child(1)').text()) || 0;
                var max = parseFloat($(this).find('td:nth-child(2)').text()) || 0;
                var inc = parseFloat($(this).find('td:nth-child(3)').text().replace('+','').replace('%','')) || 0;
                var status = $(this).find('td:nth-child(5) .badge').text().trim().toLowerCase();
                
                if (status === 'active' && occ_percent >= min && occ_percent <= max) {
                    matched_occ = inc;
                    return false; // break
                }
            });
            occ_pct_inc = matched_occ;

            var matched_time = 0;
            $('#time-pane tbody tr').each(function() {
                var min = parseInt($(this).find('td:nth-child(1)').text()) || 0;
                var max = parseInt($(this).find('td:nth-child(2)').text()) || 0;
                var inc = parseFloat($(this).find('td:nth-child(3)').text().replace('+','').replace('%','')) || 0;
                var status = $(this).find('td:nth-child(5) .badge').text().trim().toLowerCase();

                if (status === 'active' && days_left >= min && days_left <= max) {
                    matched_time = inc;
                    return false; // break
                }
            });
            time_pct_inc = matched_time;
        }

        var occ_adjust = (base_fare * occ_pct_inc) / 100;
        var time_adjust = (base_fare * time_pct_inc) / 100;
        var final_price = base_fare + occ_adjust + time_adjust;

        $('#out_occ_adjust').text('+₹' + occ_adjust.toFixed(2) + ' (+' + occ_pct_inc + '%)');
        $('#out_time_adjust').text('+₹' + time_adjust.toFixed(2) + ' (+' + time_pct_inc + '%)');
        $('#out_final_price').text('₹' + final_price.toFixed(2));
    }

    $('#btnRunSimulation').click(calculateSimulation);
    // Initial run
    calculateSimulation();
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
