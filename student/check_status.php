<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

// Tắt hiển thị lỗi PHP ra màn hình để tránh hỏng JSON, nhưng ghi vào log
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $attempt_id = $_REQUEST['attempt_id'] ?? 0;

    if (!$attempt_id) {
        throw new Exception("Missing Attempt ID");
    }

    // ==========================================
    // 1. XỬ LÝ GET: Polling trạng thái (Cho exam_security.js)
    // ==========================================
    if ($method === 'GET') {
        // Lấy trạng thái hiện tại từ DB
        $stmt = $pdo->prepare("SELECT status, score, end_time FROM test_attempts WHERE id = ?");
        $stmt->execute([$attempt_id]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt) {
            throw new Exception("Attempt not found");
        }

        // Logic kiểm tra xem bài thi có bị giáo viên thu bài hoặc hết giờ không
        $force_stop = false;
        if ($attempt['end_time'] && strtotime($attempt['end_time']) <= time()) {
            $force_stop = true;
        }

        echo json_encode([
            'status' => 'success',
            'exam_status' => $attempt['status'], // Quan trọng: trả về 'suspended' nếu bị ban
            'score' => $attempt['score'],
            'force_stop' => $force_stop
        ]);
        exit();
    }

    // ==========================================
    // 2. XỬ LÝ POST: Ghi Log & Auto Ban Logic
    // ==========================================
    if ($method === 'POST') {
        $log_type = $_POST['log_type'] ?? $_POST['violation_type'] ?? 'unknown';
        $details = $_POST['details'] ?? '';
        $proof_image_path = null;

        // --- A. Xử lý ảnh Screenshot (nếu có) ---
        if (!empty($_POST['screenshot'])) {
            $img_data = $_POST['screenshot'];
            if (preg_match('/^data:image\/(\w+);base64,/', $img_data, $type)) {
                $img_data = substr($img_data, strpos($img_data, ',') + 1);
                $img_data = base64_decode(str_replace(' ', '+', $img_data));
                
                if ($img_data !== false) {
                    $filename = 'proof_' . $attempt_id . '_' . time() . '.jpg';
                    $upload_dir = dirname(__DIR__) . '/uploads/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    
                    if (file_put_contents($upload_dir . $filename, $img_data)) {
                        $proof_image_path = '/uploads/' . $filename;
                    }
                }
            }
        }

        // --- B. Ghi log vào Database ---
        $stmt = $pdo->prepare("INSERT INTO cheating_logs (attempt_id, log_type, details, proof_image, timestamp) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$attempt_id, $log_type, $details, $proof_image_path]);

        // --- C. LOGIC AUTO BAN (QUAN TRỌNG NHẤT) ---
        // 1. Lấy quy tắc phạt từ bảng tests
        $stmt = $pdo->prepare("
            SELECT t.suspension_rules 
            FROM test_attempts ta 
            JOIN tests t ON ta.test_id = t.id 
            WHERE ta.id = ?
        ");
        $stmt->execute([$attempt_id]);
        $rules_json = $stmt->fetchColumn();
        $rules = json_decode($rules_json ?? '{}', true);

        $is_banned = false;
        $ban_reason = "";

        // 2. Kiểm tra nếu có quy tắc cho loại lỗi này
        if (isset($rules[$log_type]) && intval($rules[$log_type]) > 0) {
            $limit = intval($rules[$log_type]);

            // 3. Đếm số lần vi phạm loại lỗi này
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cheating_logs WHERE attempt_id = ? AND log_type = ?");
            $countStmt->execute([$attempt_id, $log_type]);
            $current_count = $countStmt->fetchColumn();

            // 4. Nếu vượt quá giới hạn -> BAN NGAY LẬP TỨC
            if ($current_count >= $limit) {
                $is_banned = true;
                $ban_reason = "Đình chỉ tự động: Vi phạm lỗi '$log_type' $current_count/$limit lần.";

                // Cập nhật trạng thái thi: suspended, điểm 0, kết thúc ngay
                $updateStmt = $pdo->prepare("
                    UPDATE test_attempts 
                    SET status = 'suspended', score = 0, end_time = NOW() 
                    WHERE id = ? AND status != 'suspended'
                ");
                $updateStmt->execute([$attempt_id]);

                // Gửi tin nhắn hệ thống để lưu vết
                $msgStmt = $pdo->prepare("INSERT INTO exam_messages (attempt_id, sender_type, message) VALUES (?, 'system', ?)");
                $msgStmt->execute([$attempt_id, $ban_reason]);
            }
        }

        echo json_encode([
            'status' => $is_banned ? 'suspended' : 'success',
            'message' => $is_banned ? $ban_reason : 'Log saved',
            'violation_count' => $current_count ?? 1
        ]);
        exit();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>