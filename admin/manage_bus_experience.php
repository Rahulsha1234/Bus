<?php
/**
 * Bus Operator Experience Management (Media, Amenities, Specs, Policies, Live Tracking)
 */
require_once __DIR__ . '/header.php';

$admin_id = $_SESSION['user_id'];
$bus_id = intval($_GET['bus_id'] ?? 0);

// Verify bus ownership
$bus_stmt = $pdo->prepare("SELECT * FROM buses WHERE id = ? AND admin_id = ? AND status = 'active' LIMIT 1");
$bus_stmt->execute([$bus_id, $admin_id]);
$bus = $bus_stmt->fetch();

if (!$bus) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Bus not found or unauthorized access.</div></div>";
    require_once __DIR__ . '/footer.php';
    exit();
}

$error = '';
$success = '';

// Handle POST actions (Specs, Policies, Amenities, Media, Tracking)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token validation failed. Please refresh.";
    } else {
        $action = $_POST['action'] ?? '';

        // 1. UPDATE SPECIFICATIONS
        if ($action === 'save_specs') {
            $manufacturer = trim($_POST['manufacturer'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $year = intval($_POST['year'] ?? 0);
            $fuel_type = trim($_POST['fuel_type'] ?? '');
            $total_berths = intval($_POST['total_berths'] ?? 0);
            $ac_type = trim($_POST['ac_type'] ?? '');
            $sleeper_layout = trim($_POST['sleeper_layout'] ?? '');
            $description = trim($_POST['description'] ?? '');

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO bus_specifications (bus_id, manufacturer, model, year, fuel_type, total_berths, ac_type, sleeper_layout, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        manufacturer = VALUES(manufacturer),
                        model = VALUES(model),
                        year = VALUES(year),
                        fuel_type = VALUES(fuel_type),
                        total_berths = VALUES(total_berths),
                        ac_type = VALUES(ac_type),
                        sleeper_layout = VALUES(sleeper_layout),
                        description = VALUES(description)
                ");
                $stmt->execute([$bus_id, $manufacturer, $model, $year, $fuel_type, $total_berths, $ac_type, $sleeper_layout, $description]);
                $success = "Specifications saved successfully.";
                log_activity($pdo, $admin_id, 'BUS_SPECS_UPDATE', "Updated specifications for bus ID $bus_id");
            } catch (PDOException $e) {
                $error = "Failed to save specs: " . $e->getMessage();
            }
        }

        // 2. UPDATE POLICIES
        elseif ($action === 'save_policies') {
            $cancellation = trim($_POST['cancellation_policy'] ?? '');
            $luggage = trim($_POST['luggage_policy'] ?? '');
            $child = trim($_POST['child_policy'] ?? '');
            $smoking = trim($_POST['smoking_policy'] ?? '');
            $pet = trim($_POST['pet_policy'] ?? '');

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO bus_policies (bus_id, cancellation_policy, luggage_policy, child_policy, smoking_policy, pet_policy)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        cancellation_policy = VALUES(cancellation_policy),
                        luggage_policy = VALUES(luggage_policy),
                        child_policy = VALUES(child_policy),
                        smoking_policy = VALUES(smoking_policy),
                        pet_policy = VALUES(pet_policy)
                ");
                $stmt->execute([$bus_id, $cancellation, $luggage, $child, $smoking, $pet]);
                $success = "Policies saved successfully.";
                log_activity($pdo, $admin_id, 'BUS_POLICIES_UPDATE', "Updated policies for bus ID $bus_id");
            } catch (PDOException $e) {
                $error = "Failed to save policies: " . $e->getMessage();
            }
        }

        // 3. UPDATE AMENITIES
        elseif ($action === 'save_amenities') {
            $selected_amenities = $_POST['amenities'] ?? []; // format: Array of ['name' => ..., 'category' => ...]
            try {
                $pdo->beginTransaction();
                // Clear existing non-custom amenities first
                $del_stmt = $pdo->prepare("DELETE FROM bus_amenities WHERE bus_id = ? AND is_custom = 0");
                $del_stmt->execute([$bus_id]);

                // Insert selected
                $ins_stmt = $pdo->prepare("INSERT INTO bus_amenities (bus_id, amenity_name, category, is_custom) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE amenity_name = amenity_name");
                foreach ($selected_amenities as $am_name => $am_cat) {
                    $ins_stmt->execute([$bus_id, $am_name, $am_cat]);
                }

                $pdo->commit();
                $success = "Amenities updated successfully.";
                log_activity($pdo, $admin_id, 'BUS_AMENITIES_UPDATE', "Updated amenities for bus ID $bus_id");
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to save amenities: " . $e->getMessage();
            }
        }

        // 4. ADD CUSTOM AMENITY
        elseif ($action === 'add_custom_amenity') {
            $name = trim($_POST['amenity_name'] ?? '');
            if (empty($name)) {
                $error = "Custom amenity name is required.";
            } else {
                $icon_path = '';
                if (isset($_FILES['custom_icon']) && $_FILES['custom_icon']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../assets/uploads/custom_icons/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $ext = pathinfo($_FILES['custom_icon']['name'], PATHINFO_EXTENSION);
                    $filename = 'custom_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['custom_icon']['tmp_name'], $upload_dir . $filename)) {
                        $icon_path = 'assets/uploads/custom_icons/' . $filename;
                    }
                }
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO bus_amenities (bus_id, amenity_name, category, icon_path, is_custom) VALUES (?, ?, 'custom', ?, 1)");
                    $stmt->execute([$bus_id, $name, $icon_path]);
                    $success = "Custom amenity added successfully.";
                    log_activity($pdo, $admin_id, 'BUS_CUSTOM_AMENITY_ADD', "Added custom amenity '$name' for bus ID $bus_id");
                } catch (PDOException $e) {
                    $error = "Failed to add custom amenity: " . $e->getMessage();
                }
            }
        }

        // 5. UPDATE TRACKING SIMULATION
        elseif ($action === 'save_tracking') {
            $lat = floatval($_POST['latitude'] ?? 0.00);
            $lng = floatval($_POST['longitude'] ?? 0.00);
            $loc = trim($_POST['current_location_name'] ?? '');
            $eta = trim($_POST['eta'] ?? '');
            $b_status = trim($_POST['boarding_status'] ?? '');
            $t_status = trim($_POST['trip_status'] ?? '');

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO bus_tracking (bus_id, latitude, longitude, current_location_name, eta, boarding_status, trip_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        latitude = VALUES(latitude),
                        longitude = VALUES(longitude),
                        current_location_name = VALUES(current_location_name),
                        eta = VALUES(eta),
                        boarding_status = VALUES(boarding_status),
                        trip_status = VALUES(trip_status)
                ");
                $stmt->execute([$bus_id, $lat, $lng, $loc, $eta, $b_status, $t_status]);
                $success = "Tracking details updated successfully.";
                log_activity($pdo, $admin_id, 'BUS_TRACKING_UPDATE', "Updated live tracking coordinates for bus ID $bus_id");
            } catch (PDOException $e) {
                $error = "Failed to update tracking details: " . $e->getMessage();
            }
        }

        // 6. UPLOAD MEDIA
        elseif ($action === 'upload_media') {
            $category = $_POST['category'] ?? '';
            $media_type = $_POST['media_type'] ?? 'image';

            if (empty($category)) {
                $error = "Media category is required.";
            } else {
                // Count current media count
                $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM bus_media WHERE bus_id = ?");
                $count_stmt->execute([$bus_id]);
                $media_count = intval($count_stmt->fetchColumn());

                if ($media_count >= 20) {
                    $error = "Maximum 20 images/videos are allowed per bus.";
                } elseif (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../assets/uploads/bus_media/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
                    $filename = 'media_' . $category . '_' . time() . '_' . uniqid() . '.' . $ext;
                    
                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_dir . $filename)) {
                        $file_path = 'assets/uploads/bus_media/' . $filename;
                        
                        try {
                            // Find next sort order
                            $sort_stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM bus_media WHERE bus_id = ?");
                            $sort_stmt->execute([$bus_id]);
                            $next_sort = intval($sort_stmt->fetchColumn());

                            $stmt = $pdo->prepare("INSERT INTO bus_media (bus_id, category, media_type, file_path, sort_order) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$bus_id, $category, $media_type, $file_path, $next_sort]);
                            $success = "Media uploaded successfully.";
                            log_activity($pdo, $admin_id, 'BUS_MEDIA_UPLOAD', "Uploaded $media_type ($category) for bus ID $bus_id");
                        } catch (PDOException $e) {
                            $error = "Database log failed: " . $e->getMessage();
                        }
                    } else {
                        $error = "File upload failed.";
                    }
                } else {
                    $error = "No file selected or file upload error.";
                }
            }
        }

        // 7. DELETE MEDIA
        elseif ($action === 'delete_media') {
            $media_id = intval($_POST['media_id'] ?? 0);
            try {
                // Fetch media file path
                $m_stmt = $pdo->prepare("SELECT file_path FROM bus_media WHERE id = ? AND bus_id = ?");
                $m_stmt->execute([$media_id, $bus_id]);
                $file = $m_stmt->fetchColumn();

                if ($file) {
                    $full_file_path = __DIR__ . '/../' . $file;
                    if (file_exists($full_file_path)) {
                        unlink($full_file_path);
                    }
                    $del_stmt = $pdo->prepare("DELETE FROM bus_media WHERE id = ?");
                    $del_stmt->execute([$media_id]);
                    $success = "Media deleted successfully.";
                    log_activity($pdo, $admin_id, 'BUS_MEDIA_DELETE', "Deleted media ID $media_id for bus ID $bus_id");
                }
            } catch (PDOException $e) {
                $error = "Failed to delete media: " . $e->getMessage();
            }
        }

        // 8. DELETE CUSTOM AMENITY
        elseif ($action === 'delete_custom_amenity') {
            $amenity_id = intval($_POST['amenity_id'] ?? 0);
            try {
                $m_stmt = $pdo->prepare("SELECT icon_path FROM bus_amenities WHERE id = ? AND bus_id = ? AND is_custom = 1");
                $m_stmt->execute([$amenity_id, $bus_id]);
                $icon = $m_stmt->fetchColumn();

                if ($icon) {
                    $full_icon_path = __DIR__ . '/../' . $icon;
                    if (file_exists($full_icon_path)) {
                        unlink($full_icon_path);
                    }
                }
                $del_stmt = $pdo->prepare("DELETE FROM bus_amenities WHERE id = ?");
                $del_stmt->execute([$amenity_id]);
                $success = "Custom amenity deleted successfully.";
                log_activity($pdo, $admin_id, 'BUS_CUSTOM_AMENITY_DELETE', "Deleted custom amenity ID $amenity_id for bus ID $bus_id");
            } catch (PDOException $e) {
                $error = "Failed to delete custom amenity: " . $e->getMessage();
            }
        }

        // 9. REORDER MEDIA
        elseif ($action === 'reorder_media') {
            $order_ids = $_POST['order_ids'] ?? [];
            try {
                $pdo->beginTransaction();
                $up_stmt = $pdo->prepare("UPDATE bus_media SET sort_order = ? WHERE id = ? AND bus_id = ?");
                foreach ($order_ids as $index => $m_id) {
                    $up_stmt->execute([$index + 1, intval($m_id), $bus_id]);
                }
                $pdo->commit();
                $success = "Media reordered successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Reordering failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch Existing Details
$specs = $pdo->query("SELECT * FROM bus_specifications WHERE bus_id = $bus_id")->fetch() ?: [];
$policies = $pdo->query("SELECT * FROM bus_policies WHERE bus_id = $bus_id")->fetch() ?: [];
$tracking = $pdo->query("SELECT * FROM bus_tracking WHERE bus_id = $bus_id")->fetch() ?: [];
$media_files = $pdo->query("SELECT * FROM bus_media WHERE bus_id = $bus_id ORDER BY sort_order ASC")->fetchAll();
$db_amenities = $pdo->query("SELECT * FROM bus_amenities WHERE bus_id = $bus_id")->fetchAll();

$active_amenities_list = [];
$custom_amenities_list = [];
foreach ($db_amenities as $am) {
    if ($am['is_custom']) {
        $custom_amenities_list[] = $am;
    } else {
        $active_amenities_list[$am['amenity_name']] = true;
    }
}

// Standard categories definitions
$predefined_amenities = [
    'comfort' => ['AC', 'Non AC', 'Air Suspension', 'Recliner Seats', 'Extra Legroom', 'Sleeper', 'Semi Sleeper', 'Premium Sleeper', 'Blanket', 'Pillow', 'Curtains'],
    'technology' => ['WiFi', 'USB Charging', 'Mobile Charging Point', 'GPS Tracking', 'Live Tracking', 'CCTV', 'TV', 'Entertainment System'],
    'safety' => ['Fire Extinguisher', 'Emergency Exit', 'First Aid Kit', 'Panic Button', 'Speed Governor'],
    'convenience' => ['Water Bottle', 'Snacks', 'Washroom', 'Reading Light', 'Luggage Space'],
    'special' => ['Female Friendly Seats', 'Senior Citizen Friendly', 'Wheelchair Friendly', 'Child Friendly', 'Pet Friendly']
];

$photo_categories = [
    'Required' => [
        'front_view' => 'Front View',
        'rear_view' => 'Rear View',
        'left_side_view' => 'Left Side View',
        'right_side_view' => 'Right Side View',
        'interior_front' => 'Interior Front',
        'interior_middle' => 'Interior Middle',
        'interior_rear' => 'Interior Rear',
        'sleeper_cabin' => 'Sleeper Cabin',
        'driver_cabin' => 'Driver Cabin',
        'luggage_storage' => 'Luggage Storage Area'
    ],
    'Optional' => [
        'premium_seats' => 'Premium Seats',
        'washroom' => 'Washroom',
        'charging_ports' => 'Charging Ports',
        'entertainment_system' => 'Entertainment System',
        'water_bottle' => 'Water Bottle Facility',
        'blanket_pillow' => 'Blanket & Pillow Setup'
    ]
];
?>

<div class="container py-4">
    <!-- Header Card -->
    <div class="glass-card p-4 mb-4 d-flex justify-content-between align-items-center" style="border-radius: 20px;">
        <div>
            <h3 class="fw-bold text-white mb-1"><i class="fa-solid fa-wand-magic-sparkles me-2 text-indigo"></i>Manage Travel Experience</h3>
            <p class="text-secondary small mb-0">Configure premium layout assets, photos, videos, and specifications for <strong><?= htmlspecialchars($bus['bus_name']) ?></strong> (<?= htmlspecialchars($bus['bus_number']) ?>)</p>
        </div>
        <a href="buses.php" class="btn btn-secondary-glass rounded-3"><i class="fa-solid fa-arrow-left me-2"></i>Back to Fleet</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-3 mb-4"><i class="fa-solid fa-circle-xmark me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-3 mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="glass-card p-4" style="border-radius: 20px;">
        <ul class="nav nav-pills mb-4 gap-2" id="experienceTab" role="tablist">
            <li class="nav-item">
                <button class="btn btn-secondary-glass active px-4 py-2 border-0 text-white" id="media-tab" data-bs-toggle="tab" data-bs-target="#media-pane" type="button" role="tab"><i class="fa-solid fa-images me-2"></i>Media Manager</button>
            </li>
            <li class="nav-item">
                <button class="btn btn-secondary-glass px-4 py-2 border-0 text-white" id="amenities-tab" data-bs-toggle="tab" data-bs-target="#amenities-pane" type="button" role="tab"><i class="fa-solid fa-gift me-2"></i>Amenities</button>
            </li>
            <li class="nav-item">
                <button class="btn btn-secondary-glass px-4 py-2 border-0 text-white" id="specs-tab" data-bs-toggle="tab" data-bs-target="#specs-pane" type="button" role="tab"><i class="fa-solid fa-gears me-2"></i>Specifications</button>
            </li>
            <li class="nav-item">
                <button class="btn btn-secondary-glass px-4 py-2 border-0 text-white" id="policies-tab" data-bs-toggle="tab" data-bs-target="#policies-pane" type="button" role="tab"><i class="fa-solid fa-shield-halved me-2"></i>Policies</button>
            </li>
            <li class="nav-item">
                <button class="btn btn-secondary-glass px-4 py-2 border-0 text-white" id="tracking-tab" data-bs-toggle="tab" data-bs-target="#tracking-pane" type="button" role="tab"><i class="fa-solid fa-map-location-dot"></i> Live Tracking Simulator</button>
            </li>
        </ul>

        <div class="tab-content" id="experienceTabContent">
            <!-- 1. MEDIA MANAGER PANE -->
            <div class="tab-pane fade show active" id="media-pane" role="tabpanel">
                <div class="row g-4">
                    <!-- Upload Section -->
                    <div class="col-md-4">
                        <div class="card bg-dark border-secondary border-opacity-20 p-4 h-100" style="border-radius:15px;">
                            <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-cloud-arrow-up me-2 text-indigo"></i>Upload Photo / Video</h5>
                            <form method="POST" enctype="multipart/form-data" class="d-flex flex-column h-100">
                                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                <input type="hidden" name="action" value="upload_media">
                                
                                <div class="mb-3">
                                    <label class="form-label text-secondary small fw-semibold">Media Type</label>
                                    <select name="media_type" id="media_type_select" class="form-select form-control-swift" required>
                                        <option value="image">Image (WebP, JPG, PNG)</option>
                                        <option value="video">Walkthrough Video (MP4, Max 60s)</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-secondary small fw-semibold">Photo/Video Category</label>
                                    <select name="category" class="form-select form-control-swift" required>
                                        <optgroup label="Required Photos">
                                            <?php foreach ($photo_categories['Required'] as $k => $name): ?>
                                                <option value="<?= $k ?>"><?= htmlspecialchars($name) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="Optional Photos">
                                            <?php foreach ($photo_categories['Optional'] as $k => $name): ?>
                                                <option value="<?= $k ?>"><?= htmlspecialchars($name) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="Videos">
                                            <option value="walkthrough_video">Walkthrough Video</option>
                                            <option value="interior_video">Interior Tour Video</option>
                                            <option value="sleeper_video">Sleeper Tour Video</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div class="mb-4 flex-grow-1">
                                    <label class="form-label text-secondary small fw-semibold">Choose File</label>
                                    <div class="border border-dashed border-secondary border-opacity-30 rounded-3 p-4 text-center cursor-pointer position-relative hover-opacity" style="background: rgba(0,0,0,0.2);">
                                        <input type="file" name="media_file" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" required>
                                        <i class="fa-solid fa-images text-indigo mb-2" style="font-size:2rem;"></i>
                                        <p class="small text-secondary mb-0">Drag & Drop or Click to Select File</p>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary-gradient w-100 py-2 mt-auto"><i class="fa-solid fa-arrow-up-from-bracket me-2"></i>Upload File</button>
                            </form>
                        </div>
                    </div>

                    <!-- Gallery Grid -->
                    <div class="col-md-8">
                        <div class="card bg-dark border-secondary border-opacity-20 p-4 h-100" style="border-radius:15px;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-white mb-0"><i class="fa-solid fa-photo-film me-2 text-indigo"></i>Bus Media Vault</h5>
                                <span class="text-secondary small fw-semibold"><?= count($media_files) ?> / 20 items</span>
                            </div>
                            
                            <?php if (empty($media_files)): ?>
                                <div class="text-center py-5 text-secondary small">
                                    <i class="fa-solid fa-photo-film mb-3 d-block" style="font-size: 3rem; color: #475569;"></i>
                                    No photos or videos uploaded for this bus.
                                </div>
                            <?php else: ?>
                                <form method="POST" id="reorder_form">
                                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                    <input type="hidden" name="action" value="reorder_media">
                                    <div class="row g-3 sortable-grid">
                                        <?php foreach ($media_files as $mf): ?>
                                            <div class="col-md-4 col-sm-6" data-id="<?= $mf['id'] ?>">
                                                <div class="position-relative overflow-hidden border rounded-3 bg-black h-100" style="min-height:150px; border-color: rgba(255,255,255,0.05) !important;">
                                                    <input type="hidden" name="order_ids[]" value="<?= $mf['id'] ?>">
                                                    
                                                    <?php if ($mf['media_type'] === 'video'): ?>
                                                        <video class="w-100 h-100 object-fit-cover" muted style="max-height: 120px;">
                                                            <source src="<?= BASE_URL ?>/<?= htmlspecialchars($mf['file_path']) ?>" type="video/mp4">
                                                        </video>
                                                        <div class="position-absolute top-50 start-50 translate-middle pointer-events-none">
                                                            <i class="fa-solid fa-circle-play text-white opacity-75" style="font-size: 2rem;"></i>
                                                        </div>
                                                    <?php else: ?>
                                                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($mf['file_path']) ?>" class="w-100 h-100 object-fit-cover" style="max-height: 120px;" alt="">
                                                    <?php endif; ?>

                                                    <div class="p-2 d-flex justify-content-between align-items-center bg-dark bg-opacity-75">
                                                        <span class="text-indigo small fw-bold text-uppercase" style="font-size:0.65rem;"><?= str_replace('_', ' ', $mf['category']) ?></span>
                                                        <button type="button" class="btn btn-outline-danger btn-xs py-0 px-2" onclick="deleteMedia(<?= $mf['id'] ?>)"><i class="fa-solid fa-trash-can" style="font-size:0.75rem;"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="submit" class="btn btn-outline-indigo btn-sm rounded-3 mt-4"><i class="fa-solid fa-arrows-up-down-left-right me-2"></i>Save Media Order</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. AMENITIES PANE -->
            <div class="tab-pane fade" id="amenities-pane" role="tabpanel">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="save_amenities">

                    <div class="row g-4">
                        <?php foreach ($predefined_amenities as $cat => $items): ?>
                            <div class="col-md-4">
                                <div class="card bg-dark border-secondary border-opacity-10 p-3 h-100" style="border-radius:12px;">
                                    <h6 class="fw-bold text-white border-bottom border-secondary border-opacity-20 pb-2 mb-3 text-capitalize"><i class="fa-solid fa-circle-check me-2 text-indigo"></i><?= $cat ?></h6>
                                    <div class="d-flex flex-column gap-2">
                                        <?php foreach ($items as $item): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="amenities[<?= htmlspecialchars($item) ?>]" value="<?= $cat ?>" id="am_<?= md5($item) ?>" <?= isset($active_amenities_list[$item]) ? 'checked' : '' ?>>
                                                <label class="form-check-label text-secondary small" for="am_<?= md5($item) ?>">
                                                    <?= htmlspecialchars($item) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endphp ?>
                        <?php endforeach; ?>

                        <!-- Custom Amenities Section -->
                        <div class="col-md-4">
                            <div class="card bg-dark border-secondary border-opacity-10 p-3 h-100" style="border-radius:12px;">
                                <h6 class="fw-bold text-white border-bottom border-secondary border-opacity-20 pb-2 mb-3"><i class="fa-solid fa-plus-circle me-2 text-indigo"></i>Custom Amenities</h6>
                                <div class="d-flex flex-column gap-2 mb-3">
                                    <?php foreach ($custom_amenities_list as $cam): ?>
                                        <div class="d-flex justify-content-between align-items-center bg-black bg-opacity-30 p-2 rounded border border-secondary border-opacity-10">
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($cam['icon_path'])): ?>
                                                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($cam['icon_path']) ?>" style="width:20px; height:20px;" alt="">
                                                <?php else: ?>
                                                    <i class="fa-solid fa-sparkles text-indigo"></i>
                                                <?php endif; ?>
                                                <span class="text-white small fw-bold"><?= htmlspecialchars($cam['amenity_name']) ?></span>
                                            </div>
                                            <button type="button" class="btn btn-link text-danger p-0 border-0" onclick="deleteCustomAmenity(<?= $cam['id'] ?>)"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="button" class="btn btn-sm btn-outline-indigo w-100 rounded-3" data-bs-toggle="modal" data-bs-target="#customAmenityModal"><i class="fa-solid fa-plus me-1"></i>Create Custom Amenity</button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-gradient px-4 py-2 mt-4"><i class="fa-solid fa-circle-check me-2"></i>Save Selected Amenities</button>
                </form>
            </div>

            <!-- 3. SPECIFICATIONS PANE -->
            <div class="tab-pane fade" id="specs-pane" role="tabpanel">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="save_specs">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Manufacturer</label>
                            <input type="text" name="manufacturer" class="form-control form-control-swift" placeholder="e.g. Scania, Volvo" value="<?= htmlspecialchars($specs['manufacturer'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Model</label>
                            <input type="text" name="model" class="form-control form-control-swift" placeholder="e.g. Metrolink, B11R" value="<?= htmlspecialchars($specs['model'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Year of Manufacture</label>
                            <input type="number" name="year" class="form-control form-control-swift" placeholder="e.g. 2024" value="<?= htmlspecialchars($specs['year'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Fuel Type</label>
                            <select name="fuel_type" class="form-select form-control-swift">
                                <option value="Diesel" <?= ($specs['fuel_type'] ?? '') === 'Diesel' ? 'selected' : '' ?>>Diesel</option>
                                <option value="CNG" <?= ($specs['fuel_type'] ?? '') === 'CNG' ? 'selected' : '' ?>>CNG</option>
                                <option value="LNG" <?= ($specs['fuel_type'] ?? '') === 'LNG' ? 'selected' : '' ?>>LNG</option>
                                <option value="Electric" <?= ($specs['fuel_type'] ?? '') === 'Electric' ? 'selected' : '' ?>>Electric</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Total Berths (Sleeper Compartments)</label>
                            <input type="number" name="total_berths" class="form-control form-control-swift" placeholder="e.g. 15" value="<?= htmlspecialchars($specs['total_berths'] ?? '0') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Air Conditioning Type</label>
                            <input type="text" name="ac_type" class="form-control form-control-swift" placeholder="e.g. Climate Control Multi-Zone AC" value="<?= htmlspecialchars($specs['ac_type'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Sleeper Compartment Layout Description</label>
                            <input type="text" name="sleeper_layout" class="form-control form-control-swift" placeholder="e.g. Single & Double Berths Split" value="<?= htmlspecialchars($specs['sleeper_layout'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Description / Intro Copy</label>
                            <textarea name="description" class="form-control form-control-swift" rows="3" placeholder="Enter high-end marketing introduction..."><?= htmlspecialchars($specs['description'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-gradient px-4 py-2 mt-4"><i class="fa-solid fa-save me-2"></i>Save Specifications</button>
                </form>
            </div>

            <!-- 4. POLICIES PANE -->
            <div class="tab-pane fade" id="policies-pane" role="tabpanel">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="save_policies">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Cancellation Policy Description</label>
                            <textarea name="cancellation_policy" class="form-control form-control-swift" rows="4" placeholder="Enter refund rules by time frame..."><?= htmlspecialchars($policies['cancellation_policy'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Luggage Policy</label>
                            <textarea name="luggage_policy" class="form-control form-control-swift" rows="4" placeholder="e.g. Max 20kg per customer free, charge per extra kg..."><?= htmlspecialchars($policies['luggage_policy'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Child Policy</label>
                            <textarea name="child_policy" class="form-control form-control-swift" rows="4" placeholder="e.g. Children below 5 years travel free without separate berth seat..."><?= htmlspecialchars($policies['child_policy'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Smoking Policy</label>
                            <textarea name="smoking_policy" class="form-control form-control-swift" rows="4" placeholder="e.g. Smoking strictly prohibited onboard..."><?= htmlspecialchars($policies['smoking_policy'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Pet Policy</label>
                            <textarea name="pet_policy" class="form-control form-control-swift" rows="4" placeholder="e.g. Pets are not allowed on this service..."><?= htmlspecialchars($policies['pet_policy'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-gradient px-4 py-2 mt-4"><i class="fa-solid fa-save me-2"></i>Save Policies</button>
                </form>
            </div>

            <!-- 5. LIVE TRACKING SIMULATOR PANE -->
            <div class="tab-pane fade" id="tracking-pane" role="tabpanel">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="save_tracking">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Current Location (Latitude)</label>
                            <input type="number" step="0.00000001" name="latitude" class="form-control form-control-swift" placeholder="e.g. 12.971598" value="<?= htmlspecialchars($tracking['latitude'] ?? '0.00') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Current Location (Longitude)</label>
                            <input type="number" step="0.00000001" name="longitude" class="form-control form-control-swift" placeholder="e.g. 77.594562" value="<?= htmlspecialchars($tracking['longitude'] ?? '0.00') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Current Landmark / Station Name</label>
                            <input type="text" name="current_location_name" class="form-control form-control-swift" placeholder="e.g. Majestic Circle, Toll Plaza" value="<?= htmlspecialchars($tracking['current_location_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Estimated Arrival Time (ETA)</label>
                            <input type="text" name="eta" class="form-control form-control-swift" placeholder="e.g. 45 Minutes" value="<?= htmlspecialchars($tracking['eta'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Boarding Status</label>
                            <select name="boarding_status" class="form-select form-control-swift">
                                <option value="Boarding Not Started" <?= ($tracking['boarding_status'] ?? '') === 'Boarding Not Started' ? 'selected' : '' ?>>Boarding Not Started</option>
                                <option value="Boarding Active" <?= ($tracking['boarding_status'] ?? '') === 'Boarding Active' ? 'selected' : '' ?>>Boarding Active</option>
                                <option value="Boarding Closed" <?= ($tracking['boarding_status'] ?? '') === 'Boarding Closed' ? 'selected' : '' ?>>Boarding Closed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">Trip Status</label>
                            <select name="trip_status" class="form-select form-control-swift">
                                <option value="Scheduled" <?= ($tracking['trip_status'] ?? '') === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="Running Late" <?= ($tracking['trip_status'] ?? '') === 'Running Late' ? 'selected' : '' ?>>Running Late</option>
                                <option value="On Time" <?= ($tracking['trip_status'] ?? '') === 'On Time' ? 'selected' : '' ?>>On Time</option>
                                <option value="Arrived" <?= ($tracking['trip_status'] ?? '') === 'Arrived' ? 'selected' : '' ?>>Arrived</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-gradient px-4 py-2 mt-4"><i class="fa-solid fa-crosshairs me-2"></i>Update Simulator Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Custom Amenity Creation Modal -->
<div class="modal fade" id="customAmenityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#111111; border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-gift me-2 text-indigo"></i>Create Custom Amenity</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="add_custom_amenity">

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Amenity Name</label>
                        <input type="text" name="amenity_name" class="form-control form-control-swift" placeholder="e.g. VIP Charger, Leather Seat" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Custom Icon (PNG, SVG - Max 200KB)</label>
                        <input type="file" name="custom_icon" class="form-control form-control-swift">
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-20 p-4">
                    <button type="button" class="btn btn-secondary-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gradient">Create Amenity</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Media Delete Helper Forms -->
<form id="delete_media_form" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
    <input type="hidden" name="action" value="delete_media">
    <input type="hidden" name="media_id" id="delete_media_id_field">
</form>

<form id="delete_custom_amenity_form" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
    <input type="hidden" name="action" value="delete_custom_amenity">
    <input type="hidden" name="amenity_id" id="delete_custom_amenity_id_field">
</form>

<script>
function deleteMedia(id) {
    if (confirm("Are you sure you want to delete this photo/video?")) {
        document.getElementById('delete_media_id_field').value = id;
        document.getElementById('delete_media_form').submit();
    }
}

function deleteCustomAmenity(id) {
    if (confirm("Are you sure you want to delete this custom amenity?")) {
        document.getElementById('delete_custom_amenity_id_field').value = id;
        document.getElementById('delete_custom_amenity_form').submit();
    }
}
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
