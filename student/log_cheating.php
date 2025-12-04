<?php
// FILE: log_cheating.php (BẢN FINAL - TRẢ VỀ SỐ LẦN VI PHẠM)
session_start();
require_once '../config.php';
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    // 1. NHẬN DỮ LIỆU
    $input = json_decode(file_get_contents('php://input'), true);
    $attempt_id = $_POST['attempt_id'] ?? $input['attempt_id'] ?? null;
    $raw_type   = $_POST['log_type'] ?? $_POST['violation_type'] ?? $input['type'] ?? $input['log_type'] ?? null;
    $details    = $_POST['details'] ?? $input['details'] ?? '';
    $screenshot_data = $_POST['screenshot'] ?? $input['screenshot'] ?? null;

    if (!$attempt_id || !$raw_type) throw new Exception("Thiếu dữ liệu");

    // 2. MAPPING (PHIÊN DỊCH LỖI & TÊN HIỂN THỊ)
    $standard_type = $raw_type;
    $friendly_name = "Vi phạm quy chế";

    switch ($raw_type) {
        case 'window_blur':
        case 'switched_tab':
        case 'fullscreen_exit': 
        case 'devtools_key_attempt':
            $standard_type = 'tab_switch';
            $friendly_name = "Rời khỏi màn hình thi";
            break;
        case 'no_face_detected':
        case 'face_not_visible':
            $standard_type = 'face_missing';
            $friendly_name = "Không tìm thấy khuôn mặt";
            break;
        case 'multiple_faces':
            $standard_type = 'multiple_faces'; 
            $friendly_name = "Phát hiện nhiều người";
            break;
        case 'contextmenu':
        case 'right_click':
            $standard_type = 'right_click';
            $friendly_name = "Sử dụng chuột phải";
            break;
        case 'copy':
        case 'paste':
        case 'cut':
            $standard_type = 'copy_paste';
            $friendly_name = "Thao tác Copy/Paste";
            break;
        case 'phone_detected':
             $standard_type = 'phone_detected';
             $friendly_name = "Phát hiện điện thoại";
             break;    
    }

    // 3. XỬ LÝ ẢNH (Lưu ảnh bằng chứng)
    $proof_image_path = null;
    if ($screenshot_data && strpos($screenshot_data, 'base64') !== false) {
        try {
            $img_data = explode(',', $screenshot_data)[1];
            $decoded = base64_decode($img_data);
            if ($decoded) {
                $filename = 'proof_' . $attempt_id . '_' . time() . '_' . uniqid() . '.jpg';
                $upload_dir = dirname(__DIR__) . '/uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                file_put_contents($upload_dir . $filename, $decoded);
                $proof_image_path = '/uploads/' . $filename;
            }
        } catch (Exception $e) {}
    }

    // 4. GHI LOG VÀO DB
    $stmt = $pdo->prepare("INSERT INTO cheating_logs (attempt_id, log_type, details, proof_image, timestamp) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$attempt_id, $standard_type, $details, $proof_image_path]);

    // 5. TÍNH TOÁN GIỚI HẠN & AUTO BAN
    $stmt = $pdo->prepare("SELECT test_id FROM test_attempts WHERE id = ?");
    $stmt->execute([$attempt_id]);
    $test_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT suspension_rules FROM tests WHERE id = ?");
    $stmt->execute([$test_id]);
    $rules = json_decode($stmt->fetchColumn() ?? '{}', true);

    $remaining = -1; // Mặc định là -1 (Không giới hạn/Chỉ cảnh báo)
    $limit = 0;
    $current_count = 0;

    // Kiểm tra xem lỗi này có bị giới hạn không
    if (isset($rules[$standard_type]) && intval($rules[$standard_type]) > 0) {
        $limit = intval($rules[$standard_type]);
        
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM cheating_logs WHERE attempt_id = ? AND log_type = ?");
        $cntStmt->execute([$attempt_id, $standard_type]);
        $current_count = $cntStmt->fetchColumn();

        $remaining = $limit - $current_count;

        if ($current_count >= $limit) {
            $ban_reason = "Vi phạm lỗi '$friendly_name' quá $limit lần.";
            
            $pdo->prepare("UPDATE test_attempts SET status = 'suspended', score = 0, end_time = NOW() WHERE id = ?")->execute([$attempt_id]);
            $pdo->prepare("INSERT INTO cheating_logs (attempt_id, log_type, details) VALUES (?, 'system_ban', ?)")->execute([$attempt_id, "AUTO BAN: $ban_reason"]);

            echo json_encode([
                'status' => 'suspended', 
                'reason' => $ban_reason,
                'total_violations' => $current_count
            ]);
            exit;
        }
    }

    // --- TRẢ VỀ CẢNH BÁO (WARNING) KÈM SỐ LẦN ---
    echo json_encode([
        'status' => 'warning',
        'message' => $friendly_name,
        'remaining' => $remaining,
        'limit' => $limit
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>