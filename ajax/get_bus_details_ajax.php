<?php
/**
 * AJAX endpoint to fetch bus premium details
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

header('Content-Type: application/json');

$bus_id = intval($_GET['bus_id'] ?? 0);

if ($bus_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Bus ID']);
    exit();
}

try {
    // 1. Fetch Bus main details & verification
    $bus_stmt = $pdo->prepare("
        SELECT b.*, COALESCE(v.is_verified, 0) as is_verified, v.verified_at
        FROM buses b
        LEFT JOIN bus_verifications v ON b.id = v.bus_id
        WHERE b.id = ? AND b.status = 'active'
        LIMIT 1
    ");
    $bus_stmt->execute([$bus_id]);
    $bus = $bus_stmt->fetch();

    if (!$bus) {
        echo json_encode(['success' => false, 'message' => 'Bus not found']);
        exit();
    }

    // 2. Fetch Media
    $media_stmt = $pdo->prepare("SELECT * FROM bus_media WHERE bus_id = ? ORDER BY sort_order ASC");
    $media_stmt->execute([$bus_id]);
    $media = $media_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Specifications
    $specs_stmt = $pdo->prepare("SELECT * FROM bus_specifications WHERE bus_id = ?");
    $specs_stmt->execute([$bus_id]);
    $specs = $specs_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // 4. Fetch Policies
    $policies_stmt = $pdo->prepare("SELECT * FROM bus_policies WHERE bus_id = ?");
    $policies_stmt->execute([$bus_id]);
    $policies = $policies_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // 5. Fetch Amenities
    $amenities_stmt = $pdo->prepare("SELECT * FROM bus_amenities WHERE bus_id = ? ORDER BY is_custom ASC, category ASC");
    $amenities_stmt->execute([$bus_id]);
    $amenities = $amenities_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Fetch Live Tracking
    $tracking_stmt = $pdo->prepare("SELECT * FROM bus_tracking WHERE bus_id = ?");
    $tracking_stmt->execute([$bus_id]);
    $tracking = $tracking_stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // 7. Fetch Reviews Average & Breakdown
    $rev_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            COALESCE(AVG(rating), 0.00) as avg_rating,
            COALESCE(AVG(cleanliness), 0.00) as avg_cleanliness,
            COALESCE(AVG(staff_behaviour), 0.00) as avg_staff,
            COALESCE(AVG(punctuality), 0.00) as avg_punctuality,
            COALESCE(AVG(comfort), 0.00) as avg_comfort,
            COALESCE(AVG(safety), 0.00) as avg_safety
        FROM bus_reviews 
        WHERE bus_id = ? AND status = 'approved'
    ");
    $rev_stmt->execute([$bus_id]);
    $reviews_summary = $rev_stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Review List
    $rev_list_stmt = $pdo->prepare("
        SELECT r.*, u.username 
        FROM bus_reviews r 
        JOIN users u ON r.customer_id = u.id 
        WHERE r.bus_id = ? AND r.status = 'approved' 
        ORDER BY r.created_at DESC 
        LIMIT 10
    ");
    $rev_list_stmt->execute([$bus_id]);
    $reviews = $rev_list_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'bus' => [
            'id' => $bus['id'],
            'bus_name' => $bus['bus_name'],
            'bus_number' => $bus['bus_number'],
            'bus_type' => $bus['bus_type'],
            'is_verified' => intval($bus['is_verified'])
        ],
        'media' => $media,
        'specifications' => $specs,
        'policies' => $policies,
        'amenities' => $amenities,
        'tracking' => $tracking,
        'reviews_summary' => $reviews_summary,
        'reviews' => $reviews
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Query error: ' . $e->getMessage()]);
}
