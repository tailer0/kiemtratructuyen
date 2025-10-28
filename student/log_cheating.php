<?php
// Tăng cường báo cáo lỗi để gỡ rối
ini_set('display_errors', 0); // Không hiển thị lỗi cho người dùng cuối
ini_set('log_errors', 1); // Bật ghi lỗi vào file
error_reporting(E_ALL);

// === SỬA LỖI: Di chuyển require_once vào BÊN TRONG try-catch ===
try {
    // Sử dụng đường dẫn tuyệt đối, đáng tin cậy hơn để gọi config.php
    require_once dirname(__DIR__) . '/config.php';
    
    // === BƯỚC GỠ LỖI MỚI ===
    if (!isset($pdo) || !$pdo instanceof PDO) {
        // Nếu $pdo không tồn tại, có nghĩa là file config.php
        // bị lỗi cú pháp hoặc kết nối CSDL thất bại.
        throw new Exception("Không thể khởi tạo kết nối CSDL. Vui lòng kiểm tra file config.php.");
    }
    // ==========================

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

    $log_type = $_POST['violation_type'];
    $attempt_id = $_POST['attempt_id'];
    $details = $_POST['details'];
    $proof_image_path = null;

    // Xử lý ảnh chụp nếu có
    if (isset($_POST['screenshot']) && !empty($_POST['screenshot'])) {
        // Khối try-catch này vẫn giữ nguyên để xử lý lỗi ảnh cụ thể
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
                    if (!mkdir($upload_dir, 0777, true)) {
                        throw new Exception("Không thể tạo thư mục uploads.");
                    }
                }

                $file_path = $upload_dir . $filename;

                if (file_put_contents($file_path, $img_data_decoded)) {
                    $proof_image_path = '/uploads/' . $filename;
                } else {
                    throw new Exception("Không thể ghi file ảnh vào thư mục uploads.");
                }
            }
        } catch (Exception $e) {
            // Nếu có lỗi ảnh, ghi log và thoát
            http_response_code(500);
            error_log("Lỗi xử lý ảnh: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Lỗi máy chủ khi xử lý ảnh.']);
            exit();
        }
    }

    // Lưu log vào cơ sở dữ liệu
    $stmt = $pdo->prepare(
        "INSERT INTO cheating_logs (attempt_id, log_type, details, proof_image) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$attempt_id, $log_type, $details, $proof_image_path]);

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Log đã được ghi nhận.']);

} catch (Throwable $t) { // Bắt tất cả các loại lỗi
    http_response_code(500);
    // Ghi lại lỗi thực tế vào log của server
    error_log("Lỗi nghiêm trọng trong log_cheating.php: " . $t->getMessage());
    // Trả về thông báo lỗi chi tiết trong JSON
    echo json_encode([
        'status' => 'error', 
        'message' => 'Lỗi máy chủ nghiêm trọng.',
        // === SỬA LỖI MỚI: Đảm bảo message là UTF-8 ===
        'details' => mb_convert_encoding($t->getMessage(), 'UTF-8', 'auto')
    ]);
    exit();
}

