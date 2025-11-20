<?php
session_start();
require_once '../config.php';

// Kiểm tra quyền giáo viên
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

$class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$teacher_id = $_SESSION['user_id'];

if ($class_id > 0) {
    try {
        // Kiểm tra quyền sở hữu lớp học
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$class_id, $teacher_id]);
        
        if ($stmt->fetch()) {
            // Thực hiện xóa
            // Do DB đã thiết lập ON DELETE CASCADE, việc xóa lớp sẽ tự động xóa:
            // - class_members (thành viên)
            // - tests (bài kiểm tra) -> kéo theo xóa questions, answers, results
            $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
            
            if ($stmt->execute([$class_id])) {
                $_SESSION['success_msg'] = "Đã xóa lớp học thành công.";
            } else {
                $_SESSION['error_msg'] = "Không thể xóa lớp học. Vui lòng thử lại.";
            }
        } else {
            $_SESSION['error_msg'] = "Bạn không có quyền xóa lớp này.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Lỗi hệ thống: " . $e->getMessage();
    }
}

header('Location: index.php');
exit();
?>