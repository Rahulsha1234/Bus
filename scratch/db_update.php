<?php
require_once __DIR__ . '/../includes/db.php';
try {
    // Drop unique key unique_bus_seat_pos
    $pdo->exec("ALTER TABLE bus_seats DROP INDEX unique_bus_seat_pos");
    echo "Index unique_bus_seat_pos dropped successfully!\n";
} catch (Exception $e) {
    echo "Error dropping index: " . $e->getMessage() . "\n";
}
try {
    // Recreate it as a composite unique key including seat_type (so lower/upper sleepers can occupy same row/col)
    $pdo->exec("ALTER TABLE bus_seats ADD UNIQUE KEY unique_bus_seat_pos (bus_id, row_pos, col_pos, seat_type)");
    echo "Index unique_bus_seat_pos recreated successfully with seat_type!\n";
} catch (Exception $e) {
    echo "Error creating index: " . $e->getMessage() . "\n";
}
?>
