<?php
session_start();
require_once '../config.php';

// Kiểm tra quyền giáo viên
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_id = $_POST['test_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $class_id = $_POST['class_id'] ?? 0;
    $teacher_id = $_SESSION['user_id'];

    // Các trạng thái hợp lệ
    $allowed_statuses = ['draft', 'published', 'closed'];

    if ($test_id && in_array($status, $allowed_statuses)) {
        try {
            // Kiểm tra quyền sở hữu bài thi trước khi update
            $stmt = $pdo->prepare("UPDATE tests SET status = ? WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$status, $test_id, $teacher_id]);
            
            // Không cần thông báo quá rườm rà cho thao tác nhỏ này, chỉ cần redirect
        } catch (PDOException $e) {
            // Có thể lưu log lỗi nếu cần
        }
    }
}

// Quay lại trang lớp học
if ($class_id) {
    header("Location: view_class.php?id=" . $class_id);
} else {
    header("Location: index.php");
}
exit();
?>