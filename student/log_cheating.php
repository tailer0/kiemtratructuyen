<?php
// Tăng cường báo cáo lỗi nhưng không hiện ra màn hình (tránh hỏng JSON)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    // 1. Kết nối CSDL
    require_once '../config.php';
    
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("Không thể khởi tạo kết nối CSDL. Kiểm tra config.php.");
    }

    // 2. Kiểm tra Request Method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Yêu cầu không hợp lệ (Method Not Allowed).');
    }

    // 3. Lấy dữ liệu đầu vào (Hỗ trợ cả tên biến cũ và mới)
    $attempt_id = $_POST['attempt_id'] ?? null;
    // Ưu tiên log_type, nếu không có thì lấy violation_type (fallback)
    $log_type = $_POST['log_type'] ?? $_POST['violation_type'] ?? null;
    $details = $_POST['details'] ?? '';
    
    if (!$attempt_id || !$log_type) {
        http_response_code(400);
        throw new Exception('Thiếu dữ liệu cần thiết (attempt_id hoặc log_type).');
    }

    $proof_image_path = null;

    // 4. Xử lý ảnh chụp bằng chứng (Screenshot)
    if (isset($_POST['screenshot']) && !empty($_POST['screenshot'])) {
        try {
            $img_data = $_POST['screenshot'];
            if (strpos($img_data, 'data:image/jpeg;base64,') === 0) {
                $img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
                $img_data = str_replace(' ', '+', $img_data);
                $img_data_decoded = base64_decode($img_data);

                if ($img_data_decoded === false) {
                    throw new Exception("Dữ liệu base64 không hợp lệ.");
                }

                // Tạo tên file duy nhất
                $filename = 'proof_' . $attempt_id . '_' . time() . '_' . uniqid() . '.jpg';
                // Sử dụng __DIR__ để định vị đường dẫn chính xác hơn
                $upload_dir = dirname(__DIR__) . '/uploads/';

                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0777, true)) {
                        throw new Exception("Không thể tạo thư mục uploads.");
                    }
                }

                $file_path = $upload_dir . $filename;

                if (file_put_contents($file_path, $img_data_decoded)) {
                    $proof_image_path = '/uploads/' . $filename;
                } else {
                    throw new Exception("Không thể ghi file ảnh.");
                }
            }
        } catch (Exception $e) {
            // Ghi log lỗi ảnh nhưng không dừng quy trình (vẫn lưu log vi phạm)
            error_log("Lỗi xử lý ảnh: " . $e->getMessage());
        }
    }

    // 5. Ghi log vi phạm vào CSDL
    $stmt = $pdo->prepare(
        "INSERT INTO cheating_logs (attempt_id, log_type, details, proof_image, timestamp) VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$attempt_id, $log_type, $details, $proof_image_path]);


    // 6. LOGIC TỰ ĐỘNG ĐÌNH CHỈ (AUTO BAN)
    // Lấy quy tắc phạt từ bảng tests thông qua attempt_id
    $stmt = $pdo->prepare("
        SELECT t.suspension_rules 
        FROM test_attempts ta 
        JOIN tests t ON ta.test_id = t.id 
        WHERE ta.id = ?
    ");
    $stmt->execute([$attempt_id]);
    $rules_json = $stmt->fetchColumn();

    $is_suspended = false;
    $suspend_reason = "";

    if ($rules_json) {
        $rules = json_decode($rules_json, true);
        
        // Kiểm tra xem loại lỗi này có nằm trong danh sách phạt không và giới hạn > 0
        if (isset($rules[$log_type]) && intval($rules[$log_type]) > 0) {
            $limit = intval($rules[$log_type]);
            
            // Đếm tổng số lần vi phạm lỗi này của thí sinh hiện tại
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cheating_logs WHERE attempt_id = ? AND log_type = ?");
            $countStmt->execute([$attempt_id, $log_type]);
            $current_count = $countStmt->fetchColumn();

            // Nếu số lần vi phạm >= giới hạn cho phép -> ĐÌNH CHỈ
            if ($current_count >= $limit) {
                $is_suspended = true;
                $suspend_reason = "Hệ thống tự động đình chỉ: Vi phạm lỗi '$log_type' quá $limit lần.";
                
                // Cập nhật trạng thái bài thi: suspended, điểm = 0, kết thúc ngay lập tức
                $updateStmt = $pdo->prepare("
                    UPDATE test_attempts 
                    SET status = 'suspended', score = 0, end_time = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$attempt_id]);

                // Gửi tin nhắn hệ thống vào khung chat để lưu vết
                $msgStmt = $pdo->prepare("INSERT INTO exam_messages (attempt_id, sender_type, message) VALUES (?, 'system', ?)");
                $msgStmt->execute([$attempt_id, $suspend_reason]);
            }
        }
    }

    // 7. Trả về kết quả
    http_response_code(200);
    
    if ($is_suspended) {
        echo json_encode([
            'status' => 'suspended', 
            'message' => $suspend_reason
        ]);
    } else {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Log đã được ghi nhận.'
        ]);
    }

} catch (Throwable $t) {
    // Bắt tất cả lỗi (Exception và Error)
    http_response_code(500);
    error_log("Lỗi nghiêm trọng trong log_cheating.php: " . $t->getMessage());
    
    echo json_encode([
        'status' => 'error', 
        'message' => 'Lỗi máy chủ nghiêm trọng.',
        'details' => mb_convert_encoding($t->getMessage(), 'UTF-8', 'auto')
    ]);
    exit();
}
?>