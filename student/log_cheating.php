<?php
require_once '../config.php';

// Endpoint này chỉ chấp nhận phương thức POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit();
}

// Kiểm tra dữ liệu đầu vào
if (!isset($_POST['attempt_id']) || !isset($_POST['violation_type'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Thiếu dữ liệu cần thiết.']);
    exit();
}

$attempt_id = $_POST['attempt_id'];
$violation_type = $_POST['violation_type'];
$screenshot_path = null;
$details = '';

// Tạo mô tả chi tiết cho từng loại vi phạm
switch ($violation_type) {
    case 'switched_tab':
        $details = 'Sinh viên đã chuyển tab/cửa sổ khác.';
        break;
    case 'no_face_detected':
        $details = 'Không phát hiện thấy khuôn mặt trước camera.';
        break;
    case 'looking_away':
        $details = 'Phát hiện nhìn ra ngoài màn hình.';
        break;
    case 'head_down':
        $details = 'Phát hiện cúi đầu thấp trong thời gian dài.';
        break;
    default:
        $details = 'Hành vi không xác định.';
}

// Xử lý và lưu ảnh chụp màn hình nếu có
if (isset($_POST['screenshot']) && !empty($_POST['screenshot'])) {
    $data = $_POST['screenshot']; // Dữ liệu ảnh dạng Base64
    
    // Tách phần header của Base64
    list($type, $data) = explode(';', $data);
    list(, $data)      = explode(',', $data);
    $decoded_data = base64_decode($data);
    
    // Tạo thư mục uploads nếu chưa tồn tại
    $upload_dir = ROOT_PATH . '/uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Tạo tên file duy nhất
    $filename = 'proof_' . $attempt_id . '_' . time() . '.jpg';
    $filepath = $upload_dir . $filename;
    
    // Lưu file ảnh
    if (file_put_contents($filepath, $decoded_data)) {
        // Lưu đường dẫn tương đối để truy cập từ web
        $screenshot_path = '/uploads/' . $filename;
    }
}

// Lưu log vào cơ sở dữ liệu
try {
    $stmt = $pdo->prepare("
        INSERT INTO cheating_logs (attempt_id, log_type, details, proof_image) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$attempt_id, $violation_type, $details, $screenshot_path]);

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (PDOException $e) {
    http_response_code(500);
    // Ghi lỗi ra file log thay vì hiển thị cho người dùng
    error_log("Lỗi ghi log gian lận: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Lỗi máy chủ.']);
}

