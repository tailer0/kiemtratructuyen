<?php
// Tăng cường báo cáo lỗi để gỡ rối
ini_set('display_errors', 0); // Không hiển thị lỗi cho người dùng cuối
ini_set('log_errors', 1); // Bật ghi lỗi vào file
error_reporting(E_ALL);

require_once '../config.php';

// Endpoint này chỉ chấp nhận phương thức POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.']);
    exit();
}

// Kiểm tra các dữ liệu cần thiết
if (!isset($_POST['attempt_id']) || !isset($_POST['violation_type']) || !isset($_POST['details'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Thiếu dữ liệu cần thiết.']);
    exit();
}

// === PHẦN SỬA LỖI QUAN TRỌNG ===
// Lấy đúng tên biến 'violation_type' được gửi từ JavaScript
$log_type = $_POST['violation_type']; 
// =============================

$attempt_id = $_POST['attempt_id'];
$details = $_POST['details'];
$proof_image_path = null;

// Xử lý ảnh chụp nếu có
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

            $filename = 'proof_' . $attempt_id . '_' . time() . '_' . uniqid() . '.jpg';
            $upload_dir = ROOT_PATH . '/uploads/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_path = $upload_dir . $filename;

            if (file_put_contents($file_path, $img_data_decoded)) {
                $proof_image_path = '/uploads/' . $filename;
            } else {
                 throw new Exception("Không thể ghi file ảnh vào thư mục uploads.");
            }
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Lỗi xử lý ảnh: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Lỗi máy chủ khi xử lý ảnh.']);
        exit();
    }
}

// Lưu log vào cơ sở dữ liệu
try {
    // Sử dụng biến $log_type đã được sửa ở trên
    $stmt = $pdo->prepare(
        "INSERT INTO cheating_logs (attempt_id, log_type, details, proof_image) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$attempt_id, $log_type, $details, $proof_image_path]);

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Log đã được ghi nhận.']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Lỗi cơ sở dữ liệu khi ghi log gian lận: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Lỗi máy chủ khi ghi log.']);
    exit();
}

