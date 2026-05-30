<?php
require_once __DIR__ . '/../includes/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM weekly_settlements");
print_r($stmt->fetchAll());
