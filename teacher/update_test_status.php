<?php
require_once '../config.php';
require_once ROOT_PATH . '/_partials/Header.php';
// Bảo vệ trang: Chỉ teacher mới được truy cập
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_id = $_POST['test_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    $teacher_id = $_SESSION['user_id'];

    // Xác thực đầu vào
    $allowed_statuses = ['draft', 'published', 'closed'];
    if (!$test_id || !in_array($new_status, $allowed_statuses)) {
        // Có thể thêm thông báo lỗi ở đây
        header('Location: index.php');
        exit();
    }

    try {
        // Cập nhật trạng thái, đồng thời kiểm tra xem giáo viên có sở hữu bài test này không
        $stmt = $pdo->prepare("UPDATE tests SET status = ? WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$new_status, $test_id, $teacher_id]);
        
        // Có thể thêm thông báo thành công nếu muốn

    } catch (Exception $e) {
        // Xử lý lỗi nếu có
        // error_log($e->getMessage());
    }
}

// Quay trở lại trang dashboard
header('Location: index.php');
exit();
?>
