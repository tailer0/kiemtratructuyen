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
            
        }
    }

    // Lưu log vào cơ sở dữ liệu
    $stmt = $pdo->prepare(
        "INSERT INTO cheating_logs (attempt_id, log_type, details, proof_image) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$attempt_id, $log_type, $details, $proof_image_path]);

    // --- 3. LOGIC TỰ ĐỘNG ĐÌNH CHỈ (AUTO BAN) ---
    // Lấy cấu hình phạt của bài thi này
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
        
        // Kiểm tra xem lỗi hiện tại có nằm trong quy tắc phạt không
        if (isset($rules[$log_type]) && intval($rules[$log_type]) > 0) {
            $limit = intval($rules[$log_type]);
            
            // Đếm tổng số lần vi phạm lỗi này của thí sinh
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cheating_logs WHERE attempt_id = ? AND log_type = ?");
            $stmt->execute([$attempt_id, $log_type]);
            $current_count = $stmt->fetchColumn();

            // Nếu vượt quá giới hạn -> ĐÌNH CHỈ
            if ($current_count >= $limit) {
                $is_suspended = true;
                $suspend_reason = "Hệ thống tự động đình chỉ: Vi phạm lỗi '$log_type' quá $limit lần.";
                
                // Cập nhật trạng thái bài thi: suspended, điểm = 0, kết thúc ngay lập tức
                $stmt = $pdo->prepare("
                    UPDATE test_attempts 
                    SET status = 'suspended', score = 0, end_time = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$attempt_id]);
            }
        }
    }

    http_response_code(200);
    
    // Trả về kết quả, nếu bị đình chỉ thì frontend sẽ xử lý redirect
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
    http_response_code(500);
    error_log("Lỗi nghiêm trọng trong log_cheating.php: " . $t->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Lỗi máy chủ nghiêm trọng.',
        'details' => mb_convert_encoding($t->getMessage(), 'UTF-8', 'auto')
    ]);
    exit();
}

