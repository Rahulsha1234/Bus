<?php
/**
 * Bus Operator Bus Management Portal
 */
require_once __DIR__ . '/header.php';

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token validation failed.";
    } else {
        $action = $_POST['action'] ?? '';

        // ADD BUS
        if ($action === 'add') {
            $name = trim($_POST['bus_name'] ?? '');
            $number = strtoupper(trim($_POST['bus_number'] ?? ''));
            $type = $_POST['bus_type'] ?? '';
            $total_seats = intval($_POST['total_seats'] ?? 30);
            
            // Set layout dynamically based on type
            $layout = (strpos($type, 'Sleeper') !== false) ? '2x1_sleeper' : '2x2_seater';
            if (strpos($type, 'Sleeper') !== false) {
                $total_seats = 30; // Sleeper fixed to 30 L1-L15, U1-U15
            } else {
                $total_seats = 40; // Seater fixed to 40
            }

            if (empty($name) || empty($number) || empty($type)) {
                $error = "Please fill in all fields.";
            } elseif (!preg_match('/^[A-Z]{2}[ -]?[0-9]{1,2}[ -]?[A-Z]{0,3}[ -]?[0-9]{4}$/', $number)) {
                $error = "Invalid License Plate Number. Please enter a valid Indian bus number (e.g., KA-01-F-1234 or MH12AB1234).";
            } else {
                // Check if bus number already registered
                $chk = $pdo->prepare("SELECT id FROM buses WHERE bus_number = ? AND status = 'active' LIMIT 1");
                $chk->execute([$number]);
                if ($chk->fetchColumn()) {
                    $error = "Bus Number already registered.";
                } else {
                    try {
                        // agent_id = admin_id (legacy column kept for compatibility)
                        $stmt = $pdo->prepare("INSERT INTO buses (agent_id, admin_id, bus_name, bus_number, bus_type, total_seats, seat_layout_type, discount_type, percentage, fixed) VALUES (?, ?, ?, ?, ?, ?, ?, 'none', 0.00, 0.00)");
                        $stmt->execute([$admin_id, $admin_id, $name, $number, $type, $total_seats, $layout]);
                        $success = "Bus added successfully!";
                        log_activity($pdo, $admin_id, 'BUS_ADD', "Added bus $name ($number)");
                    } catch (PDOException $e) {
                        $error = "Failed to add bus: " . $e->getMessage();
                    }
                }
            }
        }

        // EDIT BUS
        elseif ($action === 'edit') {
            $bus_id = intval($_POST['bus_id'] ?? 0);
            $name = trim($_POST['bus_name'] ?? '');
            $number = strtoupper(trim($_POST['bus_number'] ?? ''));
            $type = $_POST['bus_type'] ?? '';
            
            $layout = (strpos($type, 'Sleeper') !== false) ? '2x1_sleeper' : '2x2_seater';
            $total_seats = (strpos($type, 'Sleeper') !== false) ? 30 : 40;

            if (empty($name) || empty($number) || empty($type) || $bus_id === 0) {
                $error = "Please fill in all fields.";
            } elseif (!preg_match('/^[A-Z]{2}[ -]?[0-9]{1,2}[ -]?[A-Z]{0,3}[ -]?[0-9]{4}$/', $number)) {
                $error = "Invalid License Plate Number. Please enter a valid Indian bus number (e.g., KA-01-F-1234 or MH12AB1234).";
            } else {
                // Verify ownership & bus number availability
                $chk = $pdo->prepare("SELECT id FROM buses WHERE bus_number = ? AND id != ? AND status = 'active' LIMIT 1");
                $chk->execute([$number, $bus_id]);
                if ($chk->fetchColumn()) {
                    $error = "Bus Number already registered to another vehicle.";
                } else {
                    $stmt = $pdo->prepare("UPDATE buses SET bus_name = ?, bus_number = ?, bus_type = ?, total_seats = ?, seat_layout_type = ? WHERE id = ? AND admin_id = ?");
                    $stmt->execute([$name, $number, $type, $total_seats, $layout, $bus_id, $admin_id]);
                    $success = "Bus updated successfully!";
                    log_activity($pdo, $admin_id, 'BUS_EDIT', "Updated bus $name ($number)");
                }
            }
        }

        // DELETE BUS
        elseif ($action === 'delete') {
            $bus_id = intval($_POST['bus_id'] ?? 0);
            
            // Verify ownership and soft delete
            $stmt = $pdo->prepare("UPDATE buses SET status = 'inactive' WHERE id = ? AND admin_id = ?");
            $stmt->execute([$bus_id, $admin_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = "Bus removed successfully!";
                log_activity($pdo, $admin_id, 'BUS_DELETE', "Soft deleted bus ID: $bus_id");
            } else {
                $error = "Failed to delete bus. Ownership mismatch or invalid ID.";
            }
        }

        // UPDATE OPERATOR CONTACTS
        elseif ($action === 'operator') {
            $bus_id = intval($_POST['bus_id'] ?? 0);
            $op_name = trim($_POST['operator_name'] ?? '');
            $op_phone = trim($_POST['contact_number'] ?? '');
            $op_whatsapp = trim($_POST['whatsapp_number'] ?? '');
            $op_emergency = trim($_POST['emergency_number'] ?? '');
            $op_email = trim($_POST['support_email'] ?? '');

            if (empty($op_name) || empty($op_phone) || empty($op_whatsapp) || empty($op_emergency) || empty($op_email) || $bus_id === 0) {
                $error = "Please fill in all operator contact fields.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO operator_contacts (bus_id, operator_name, contact_number, whatsapp_number, emergency_number, support_email)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        operator_name = VALUES(operator_name),
                        contact_number = VALUES(contact_number),
                        whatsapp_number = VALUES(whatsapp_number),
                        emergency_number = VALUES(emergency_number),
                        support_email = VALUES(support_email)
                ");
                $stmt->execute([$bus_id, $op_name, $op_phone, $op_whatsapp, $op_emergency, $op_email]);
                $success = "Operator contacts updated successfully!";
                log_activity($pdo, $admin_id, 'BUS_OPERATOR_UPDATE', "Updated operator info for bus ID: $bus_id");
            }
        }
    }
}

// Fetch Agent's Buses
try {
    $stmt = $pdo->prepare("
        SELECT b.*, op.operator_name, op.contact_number, op.whatsapp_number, op.emergency_number, op.support_email 
        FROM buses b 
        LEFT JOIN operator_contacts op ON b.id = op.bus_id
        WHERE b.admin_id = ? AND b.status = 'active'
        ORDER BY b.id DESC
    ");
    $stmt->execute([$admin_id]);
    $buses = $stmt->fetchAll();
} catch (PDOException $e) {
    $buses = [];
}
?>

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

<!-- Actions Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="text-white fw-bold mb-0">Registered Fleet</h4>
    <button class="btn btn-primary-gradient" data-bs-toggle="modal" data-bs-target="#addBusModal"><i class="fa-solid fa-circle-plus me-2"></i>Register New Bus</button>
</div>

<!-- Fleet Grid Table -->
<div class="glass-card p-4">
    <?php if (count($buses) === 0): ?>
        <div class="text-center py-5 text-secondary small">
            <i class="fa-solid fa-bus-simple mb-3 d-block" style="font-size: 3rem; color: #475569;"></i>
            No vehicles registered. Add a bus to schedule operations.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle">
                <thead>
                    <tr>
                        <th>Bus Name</th>
                        <th>Plate Number</th>
                        <th>Classification</th>
                        <th>Capacity</th>
                        <th>Layout Plan</th>
                        <th>Registered Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buses as $bus): ?>
                        <tr>
                            <td><span class="fw-semibold text-white fs-6"><?= htmlspecialchars($bus['bus_name']) ?></span></td>
                            <td><span class="font-monospace px-2 py-1 rounded bg-dark border border-secondary border-opacity-30 small"><?= htmlspecialchars($bus['bus_number']) ?></span></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($bus['bus_type']) ?></span></td>
                            <td><?= htmlspecialchars($bus['total_seats']) ?> Berth Seats</td>
                            <td><span class="font-monospace text-secondary small"><?= htmlspecialchars($bus['seat_layout_type']) ?></span></td>
                            <td class="text-secondary small"><?= date('d M Y', strtotime($bus['created_at'])) ?></td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end align-items-center">
                                    <a href="configure_layout.php?bus_id=<?= $bus['id'] ?>" class="btn btn-secondary-glass py-1 px-2 small" title="Configure Seats"><i class="fa-solid fa-table-cells text-indigo"></i></a>
                                    <button class="btn btn-secondary-glass py-1 px-2 small operator-btn" data-id="<?= $bus['id'] ?>" data-name="<?= htmlspecialchars($bus['operator_name'] ?? '') ?>" data-phone="<?= htmlspecialchars($bus['contact_number'] ?? '') ?>" data-whatsapp="<?= htmlspecialchars($bus['whatsapp_number'] ?? '') ?>" data-emergency="<?= htmlspecialchars($bus['emergency_number'] ?? '') ?>" data-email="<?= htmlspecialchars($bus['support_email'] ?? '') ?>" data-bs-toggle="modal" data-bs-target="#operatorModal" title="Operator Contact Details"><i class="fa-solid fa-phone"></i></button>
                                    <button class="btn btn-secondary-glass py-1 px-2 small edit-bus-btn" data-id="<?= $bus['id'] ?>" data-name="<?= htmlspecialchars($bus['bus_name']) ?>" data-number="<?= htmlspecialchars($bus['bus_number']) ?>" data-type="<?= htmlspecialchars($bus['bus_type']) ?>" data-discount="<?= htmlspecialchars($bus['discount_type']) ?>" data-percentage="<?= htmlspecialchars($bus['percentage']) ?>" data-fixed="<?= htmlspecialchars($bus['fixed']) ?>" data-bs-toggle="modal" data-bs-target="#editBusModal" title="Edit Bus"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button class="btn btn-secondary-glass py-1 px-2 text-danger small delete-bus-btn" data-id="<?= $bus['id'] ?>" data-bs-toggle="modal" data-bs-target="#deleteBusModal" title="Delete Bus"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ADD BUS MODAL -->
<div class="modal fade" id="addBusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#131a2e; border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-bus me-2 text-indigo"></i>Register Fleet</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Bus Name / Brand</label>
                        <input type="text" name="bus_name" class="form-control form-control-swift" placeholder="e.g. Scania Multiaxle Premium" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">License Plate Number</label>
                        <input type="text" name="bus_number" class="form-control form-control-swift" placeholder="e.g. KA-01-F-1234" pattern="^[A-Za-z]{2}[ -]?[0-9]{1,2}[ -]?[A-Za-z]{0,3}[ -]?[0-9]{4}$" title="Please enter a valid Indian vehicle number format (e.g. KA-01-F-1234 or MH12AB1234)" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Vehicle Classification</label>
                        <select name="bus_type" class="form-select form-control-swift" required>
                            <option value="AC Sleeper">AC Sleeper (30 Seats Layout)</option>
                            <option value="Non-AC Sleeper">Non-AC Sleeper (30 Seats Layout)</option>
                            <option value="AC Seater">AC Seater (40 Seats Layout)</option>
                            <option value="Non-AC Seater">Non-AC Seater (40 Seats Layout)</option>
                        </select>
                    </div>


                </div>
                <div class="modal-footer border-secondary border-opacity-20 p-4">
                    <button type="button" class="btn btn-secondary-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gradient">Add Fleet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT BUS MODAL -->
<div class="modal fade" id="editBusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#131a2e; border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-pen-to-square me-2 text-indigo"></i>Modify Vehicle Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="bus_id" id="edit_bus_id">
                    
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Bus Name / Brand</label>
                        <input type="text" name="bus_name" id="edit_bus_name" class="form-control form-control-swift" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">License Plate Number</label>
                        <input type="text" name="bus_number" id="edit_bus_number" class="form-control form-control-swift" pattern="^[A-Za-z]{2}[ -]?[0-9]{1,2}[ -]?[A-Za-z]{0,3}[ -]?[0-9]{4}$" title="Please enter a valid Indian vehicle number format (e.g. KA-01-F-1234 or MH12AB1234)" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Vehicle Classification</label>
                        <select name="bus_type" id="edit_bus_type" class="form-select form-control-swift" required>
                            <option value="AC Sleeper">AC Sleeper (30 Seats Layout)</option>
                            <option value="Non-AC Sleeper">Non-AC Sleeper (30 Seats Layout)</option>
                            <option value="AC Seater">AC Seater (40 Seats Layout)</option>
                            <option value="Non-AC Seater">Non-AC Seater (40 Seats Layout)</option>
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

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteBusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#131a2e; border-radius: 20px;">
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="bus_id" id="delete_bus_id">
                    
                    <i class="fa-solid fa-circle-exclamation text-danger mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold mb-2">Delete Vehicle?</h5>
                    <p class="text-secondary small">Are you sure you want to delete this bus? Scheduled trips with this bus will be impacted.</p>
                </div>
                <div class="modal-footer border-0 p-3 d-flex justify-content-around">
                    <button type="button" class="btn btn-secondary-glass w-45 py-2" data-bs-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger w-45 py-2">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- OPERATOR DETAILS MODAL -->
<div class="modal fade" id="operatorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#131a2e; border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-headset me-2 text-indigo"></i>Operator Contact Info</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="operator">
                    <input type="hidden" name="bus_id" id="op_bus_id">
                    
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Operator Name / Company</label>
                        <input type="text" name="operator_name" id="op_operator_name" class="form-control form-control-swift" placeholder="e.g. Royal Travels" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Support Contact Number</label>
                        <input type="text" name="contact_number" id="op_contact_number" class="form-control form-control-swift" placeholder="e.g. +91 9876543210" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">WhatsApp Number</label>
                        <input type="text" name="whatsapp_number" id="op_whatsapp_number" class="form-control form-control-swift" placeholder="e.g. +91 9876543210" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Emergency Helpline Number</label>
                        <input type="text" name="emergency_number" id="op_emergency_number" class="form-control form-control-swift" placeholder="e.g. 1800-XXX-XXXX" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Support Email Address</label>
                        <input type="email" name="support_email" id="op_support_email" class="form-control form-control-swift" placeholder="e.g. support@royaltravels.com" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-20 p-4">
                    <button type="button" class="btn btn-secondary-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gradient">Save Operator Info</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Fill Edit fields
    $('.edit-bus-btn').click(function() {
        $('#edit_bus_id').val($(this).data('id'));
        $('#edit_bus_name').val($(this).data('name'));
        $('#edit_bus_number').val($(this).data('number'));
        $('#edit_bus_type').val($(this).data('type'));
        $('#edit_discount_type').val($(this).data('discount'));
        $('#edit_percentage').val($(this).data('percentage'));
        $('#edit_fixed').val($(this).data('fixed'));
    });

    // Fill Delete fields
    $('.delete-bus-btn').click(function() {
        $('#delete_bus_id').val($(this).data('id'));
    });

    // Fill Operator details fields
    $('.operator-btn').click(function() {
        $('#op_bus_id').val($(this).data('id'));
        $('#op_operator_name').val($(this).data('name'));
        $('#op_contact_number').val($(this).data('phone'));
        $('#op_whatsapp_number').val($(this).data('whatsapp'));
        $('#op_emergency_number').val($(this).data('emergency'));
        $('#op_support_email').val($(this).data('email'));
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
