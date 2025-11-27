<?php
session_start();
require_once '../config.php';

// Kiểm tra quyền giáo viên
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy các dữ liệu cơ bản
    $test_id = $_POST['test_id'] ?? 0;
    $class_id = $_POST['class_id'] ?? 0;
    $teacher_id = $_SESSION['user_id'];
    
    // Lấy loại hành động để biết người dùng đang làm gì
    // 'update_status' = Chọn dropdown trạng thái
    // 'update_schedule' = Chọn giờ trong Modal
    $action_type = $_POST['action_type'] ?? 'update_status'; 

    if ($test_id) {
        try {
            if ($action_type === 'update_status') {
                // --- TRƯỜNG HỢP 1: CẬP NHẬT TRẠNG THÁI ---
                $status = $_POST['status'] ?? '';
                $allowed_statuses = ['draft', 'published', 'closed'];

                if (in_array($status, $allowed_statuses)) {
                    $stmt = $pdo->prepare("UPDATE tests SET status = ? WHERE id = ? AND teacher_id = ?");
                    $stmt->execute([$status, $test_id, $teacher_id]);
                }

            } elseif ($action_type === 'update_schedule') {
                // --- TRƯỜNG HỢP 2: CẬP NHẬT HẸN GIỜ ---
                $end_date_input = $_POST['end_date'] ?? '';

                // Xử lý giá trị ngày tháng
                if (empty($end_date_input)) {
                    // Nếu input rỗng => Người dùng muốn xóa hẹn giờ => set NULL
                    $final_date = NULL;
                } else {
                    // Nếu có input => Lưu giá trị đó
                    $final_date = $end_date_input;
                }

                // 1. Cập nhật thời gian kết thúc
                $stmt = $pdo->prepare("UPDATE tests SET end_date = ? WHERE id = ? AND teacher_id = ?");
                $stmt->execute([$final_date, $test_id, $teacher_id]);

                // 2. LOGIC MỚI: Kiểm tra ngay lập tức
                // Nếu thời gian hẹn đã qua so với hiện tại, cập nhật trạng thái thành 'closed' ngay
                if ($final_date && strtotime($final_date) <= time()) {
                    $closeStmt = $pdo->prepare("UPDATE tests SET status = 'closed' WHERE id = ?");
                    $closeStmt->execute([$test_id]);
                }
                // (Tuỳ chọn) Nếu bạn muốn tự động mở lại khi gia hạn thời gian thì thêm logic else ở đây
            }
            
        } catch (PDOException $e) {
            // Ghi log lỗi nếu cần thiết hoặc lưu vào session để hiển thị thông báo
            // $_SESSION['error'] = "Lỗi: " . $e->getMessage();
        }
    }
}

// Quay lại trang chi tiết lớp học
if ($class_id) {
    header("Location: view_class.php?id=" . $class_id);
} else {
    header("Location: index.php");
}
exit();
?>