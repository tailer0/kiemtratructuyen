<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';
$test_id = $_GET['test_id'] ?? 0;

// 1. LẤY CẬP NHẬT MỚI
if ($action === 'fetch_updates') {
    $last_log_id = $_GET['last_log_id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("
            SELECT cl.*, ta.student_name, ta.student_id 
            FROM cheating_logs cl
            JOIN test_attempts ta ON cl.attempt_id = ta.id
            WHERE ta.test_id = ? AND cl.id > ?
            ORDER BY cl.id ASC
        ");
        $stmt->execute([$test_id, $last_log_id]);
        $new_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'new_logs' => $new_logs,
            'last_log_id' => end($new_logs)['id'] ?? $last_log_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. LẤY LỊCH SỬ CHAT
elseif ($action === 'get_chat') {
    $attempt_id = $_GET['attempt_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM exam_messages WHERE attempt_id = ? ORDER BY created_at ASC");
    $stmt->execute([$attempt_id]);
    echo json_encode(['status' => 'success', 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// 3. GỬI TIN NHẮN
elseif ($action === 'send_message') {
    $attempt_id = $_POST['attempt_id'] ?? 0;
    $message = trim($_POST['message'] ?? '');
    
    if ($attempt_id && $message) {
        $stmt = $pdo->prepare("INSERT INTO exam_messages (attempt_id, sender_type, message) VALUES (?, 'teacher', ?)");
        $stmt->execute([$attempt_id, $message]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Empty data']);
    }
}

// 4. DỪNG BÀI THI (CẬP NHẬT QUAN TRỌNG)
elseif ($action === 'stop_exam') {
    $attempt_id = $_POST['attempt_id'] ?? 0;
    
    if ($attempt_id) {
        // Cập nhật: status = 'suspended', score = 0, end_time = NOW()
        $stmt = $pdo->prepare("UPDATE test_attempts SET status = 'suspended', score = 0, end_time = NOW() WHERE id = ?");
        $stmt->execute([$attempt_id]);
        
        // Gửi thông báo hệ thống
        $stmt = $pdo->prepare("INSERT INTO exam_messages (attempt_id, sender_type, message) VALUES (?, 'system', 'BẠN ĐÃ BỊ ĐÌNH CHỈ THI! Điểm bài thi: 0.')");
        $stmt->execute([$attempt_id]);
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
}

// 5. THỐNG KÊ TRẠNG THÁI (Dùng cho Dashboard Monitor)
elseif ($action === 'get_stats') {
    // Đếm số lượng theo trạng thái để cập nhật realtime các thẻ thống kê
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM cheating_logs cl JOIN test_attempts ta ON cl.attempt_id = ta.id WHERE ta.test_id = ?) as total_violations,
            (SELECT COUNT(*) FROM test_attempts WHERE test_id = ? AND end_time IS NULL AND status != 'suspended') as active_students,
            (SELECT COUNT(*) FROM test_attempts ta WHERE ta.test_id = ? AND (SELECT COUNT(*) FROM cheating_logs cl WHERE cl.attempt_id = ta.id) > 3) as critical_violations,
            (SELECT COUNT(*) FROM test_attempts ta WHERE ta.test_id = ? AND (SELECT COUNT(*) FROM cheating_logs cl WHERE cl.attempt_id = ta.id) BETWEEN 1 AND 3) as warning_students
    ");
    $stmt->execute([$test_id, $test_id, $test_id, $test_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success'] + $stats);
}
?>