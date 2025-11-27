<?php
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_GET['attempt_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
    exit;
}

$attempt_id = $_GET['attempt_id'];

try {
    // Chỉ lấy trạng thái và điểm số hiện tại
    $stmt = $pdo->prepare("SELECT status, score FROM test_attempts WHERE id = ?");
    $stmt->execute([$attempt_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'status' => 'success',
            'exam_status' => $result['status'], // 'ongoing', 'completed', 'suspended'
            'score' => $result['score']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>