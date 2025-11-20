<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';

// 1. GHI LOG VI PHẠM
if ($action === 'log_violation') {
    $attempt_id = $_POST['attempt_id'] ?? 0;
    $type = $_POST['type'] ?? 'tab_switch';
    $details = $_POST['details'] ?? '';
    $proof = $_POST['proof'] ?? null; // Base64 image nếu có

    if ($attempt_id) {
        try {
            $stmt = $pdo->prepare("INSERT INTO cheating_logs (attempt_id, log_type, details, proof_image) VALUES (?, ?, ?, ?)");
            $stmt->execute([$attempt_id, $type, $details, $proof]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error']);
        }
    }
}

// 2. KIỂM TRA TRẠNG THÁI & TIN NHẮN (POLLING)
elseif ($action === 'check_status') {
    $attempt_id = $_GET['attempt_id'] ?? 0;
    
    if ($attempt_id) {
        // A. Kiểm tra xem bài thi có bị giáo viên DỪNG không?
        $stmt = $pdo->prepare("SELECT end_time FROM test_attempts WHERE id = ?");
        $stmt->execute([$attempt_id]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $force_stop = false;
        if ($attempt && $attempt['end_time'] && strtotime($attempt['end_time']) <= time()) {
            $force_stop = true;
        }

        // B. Lấy tin nhắn mới từ Giáo viên hoặc Hệ thống
        $stmt = $pdo->prepare("
            SELECT * FROM exam_messages 
            WHERE attempt_id = ? AND is_read = 0 AND sender_type IN ('teacher', 'system')
            ORDER BY created_at ASC
        ");
        $stmt->execute([$attempt_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Đánh dấu đã đọc
        if (!empty($messages)) {
            $stmt = $pdo->prepare("UPDATE exam_messages SET is_read = 1 WHERE attempt_id = ? AND sender_type IN ('teacher', 'system')");
            $stmt->execute([$attempt_id]);
        }

        echo json_encode([
            'status' => 'success',
            'force_stop' => $force_stop,
            'new_messages' => $messages
        ]);
    }
}
?>