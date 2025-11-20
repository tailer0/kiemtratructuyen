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

// 1. LẤY CẬP NHẬT MỚI (Logs gian lận + Tin nhắn mới)
if ($action === 'fetch_updates') {
    $last_log_id = $_GET['last_log_id'] ?? 0;
    
    try {
        // Lấy log gian lận mới hơn last_log_id
        $stmt = $pdo->prepare("
            SELECT cl.*, ta.student_name, ta.student_id 
            FROM cheating_logs cl
            JOIN test_attempts ta ON cl.attempt_id = ta.id
            WHERE ta.test_id = ? AND cl.id > ?
            ORDER BY cl.id ASC
        ");
        $stmt->execute([$test_id, $last_log_id]);
        $new_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Lấy trạng thái online/offline hoặc tin nhắn mới (nếu cần mở rộng sau này)
        
        echo json_encode([
            'status' => 'success',
            'new_logs' => $new_logs,
            'last_log_id' => end($new_logs)['id'] ?? $last_log_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. LẤY LỊCH SỬ CHAT CỦA MỘT SINH VIÊN
elseif ($action === 'get_chat') {
    $attempt_id = $_GET['attempt_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM exam_messages WHERE attempt_id = ? ORDER BY created_at ASC");
    $stmt->execute([$attempt_id]);
    echo json_encode(['status' => 'success', 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// 3. GỬI TIN NHẮN / CẢNH BÁO
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

// 4. DỪNG BÀI THI (KẾT THÚC SỚM)
elseif ($action === 'stop_exam') {
    $attempt_id = $_POST['attempt_id'] ?? 0;
    
    if ($attempt_id) {
        // Cập nhật end_time thành hiện tại -> Coi như nộp bài ngay lập tức
        $stmt = $pdo->prepare("UPDATE test_attempts SET end_time = NOW() WHERE id = ?");
        $stmt->execute([$attempt_id]);
        
        // Gửi thông báo hệ thống vào chat
        $stmt = $pdo->prepare("INSERT INTO exam_messages (attempt_id, sender_type, message) VALUES (?, 'system', 'Giáo viên đã đình chỉ bài thi của bạn do vi phạm quy chế.')");
        $stmt->execute([$attempt_id]);
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
}
?>