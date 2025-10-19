<?php
require_once '../config.php';
require_once ROOT_PATH . '/_partials/Header.php';
// Đây là một endpoint API, không phải trang web
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$attempt_id = $_POST['attempt_id'] ?? null;
$log_type = $_POST['log_type'] ?? null;
$details = $_POST['details'] ?? null;
$proof_image = $_POST['proof_image'] ?? null;

if (!$attempt_id || !$log_type || !$details) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit();
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO cheating_logs (attempt_id, log_type, details, proof_image, timestamp) 
         VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$attempt_id, $log_type, $details, $proof_image]);
    
    echo json_encode(['status' => 'success', 'message' => 'Log saved']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
