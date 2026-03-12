<?php
session_start();
header('Content-Type: application/json');

// --- Authentication check ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
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
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// --- Read and decode JSON input ---
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// --- Validate required fields ---
$lod_name = trim($input['lod_name'] ?? '');
if ($lod_name === '') {
    echo json_encode(['success' => false, 'error' => 'LOD name is required']);
    exit;
}

$subcontractor_id = isset($input['subcontractor_id']) ? (int)$input['subcontractor_id'] : null;
if ($subcontractor_id === 0) $subcontractor_id = null; // convert 0 to NULL

$production_date = $input['production_date'] ?? date('Y-m-d');
$items = $input['items'] ?? [];
$costs = $input['costs'] ?? [];

// --- Start transaction ---
$pdo->beginTransaction();

try {
    // 1. Insert batch header
    $stmt = $pdo->prepare("INSERT INTO wigs_batches (lod_name, subcontractor_id, production_date) VALUES (?, ?, ?)");
    $stmt->execute([$lod_name, $subcontractor_id, $production_date]);
    $batch_id = $pdo->lastInsertId();

    // 2. Insert items (top/iskin rows)
    if (!empty($items)) {
        $itemStmt = $pdo->prepare("INSERT INTO wigs_batch_items (batch_id, type, size, per_piece, quantity) VALUES (?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $itemStmt->execute([
                $batch_id,
                $item['type'],
                $item['size'],
                $item['per_piece'],
                $item['quantity']
            ]);
        }
    }

    // 3. Insert cost rows
    if (!empty($costs)) {
        $costStmt = $pdo->prepare("INSERT INTO wigs_batch_costs (batch_id, size, description, quantity, unit_price) VALUES (?, ?, ?, ?, ?)");
        foreach ($costs as $cost) {
            $costStmt->execute([
                $batch_id,
                $cost['size'],
                $cost['description'],
                $cost['quantity'],
                $cost['unit_price']
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'batch_id' => $batch_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}