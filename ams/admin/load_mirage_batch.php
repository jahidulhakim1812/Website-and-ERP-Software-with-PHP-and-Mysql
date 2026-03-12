<?php
session_start();
header('Content-Type: application/json');

// --- Authentication check ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// --- Database connection ---
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $ex) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// --- Get batch_id from URL ---
$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
if ($batch_id <= 0) {
    echo json_encode(['error' => 'Invalid batch ID']);
    exit;
}

try {
    // 1. Fetch batch header
    $stmt = $pdo->prepare("SELECT id, lod_name, subcontractor_id, production_date FROM wigs_batches WHERE id = ?");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch();
    if (!$batch) {
        echo json_encode(['error' => 'Batch not found']);
        exit;
    }

    // 2. Fetch items
    $stmt = $pdo->prepare("SELECT type, size, per_piece, quantity FROM wigs_batch_items WHERE batch_id = ?");
    $stmt->execute([$batch_id]);
    $items = $stmt->fetchAll();

    // 3. Fetch cost rows
    $stmt = $pdo->prepare("SELECT size, description, quantity, unit_price FROM wigs_batch_costs WHERE batch_id = ? ORDER BY id");
    $stmt->execute([$batch_id]);
    $costs = $stmt->fetchAll();

    // 4. Return combined data
    echo json_encode([
        'batch' => $batch,
        'items' => $items,
        'costs' => $costs
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}