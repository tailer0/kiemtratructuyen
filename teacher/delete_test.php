<?php
session_start();
require_once '../config.php';

// 1. Kiểm tra quyền giáo viên
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

// 2. Lấy ID
$test_id = isset($_GET['test_id']) ? intval($_GET['test_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$teacher_id = $_SESSION['user_id'];

if ($test_id > 0) {
    try {
        // 3. Kiểm tra quyền sở hữu (Chỉ xóa bài do chính mình tạo)
        $stmt = $pdo->prepare("SELECT id FROM tests WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$test_id, $teacher_id]);
        
        if ($stmt->fetch()) {
            $pdo->beginTransaction();
            
            // 4. Thực hiện xóa
            // Dựa trên Schema bạn cung cấp, các bảng đã có ON DELETE CASCADE.
            // Tuy nhiên, ta vẫn thực hiện xóa rõ ràng các bảng con để đảm bảo tính toàn vẹn
            // ngay cả khi DB Engine không hỗ trợ FK (ví dụ MyISAM).
            
            // Xóa các câu trả lời của sinh viên (liên quan đến attempt)
            // (Nếu có CASCADE thì bước này DB tự lo, nhưng viết ra cho chắc chắn)
            // Lưu ý: Cần xóa theo thứ tự con -> cha nếu không có Cascade.
            
            // 1. Xóa chi tiết bài làm (user_answers) thông qua attempt
            // $stmt = $pdo->prepare("DELETE ua FROM user_answers ua JOIN test_attempts ta ON ua.attempt_id = ta.id WHERE ta.test_id = ?");
            // $stmt->execute([$test_id]);

            // 2. Xóa lịch sử làm bài (Đã sửa từ 'results' thành 'test_attempts' theo DB của bạn)
            $stmt = $pdo->prepare("DELETE FROM test_attempts WHERE test_id = ?");
            $stmt->execute([$test_id]);

            // 3. Xóa đáp án của câu hỏi
            $stmt = $pdo->prepare("DELETE a FROM answers a JOIN questions q ON a.question_id = q.id WHERE q.test_id = ?");
            $stmt->execute([$test_id]);

            // 4. Xóa câu hỏi
            $stmt = $pdo->prepare("DELETE FROM questions WHERE test_id = ?");
            $stmt->execute([$test_id]);
            
            // 5. Cuối cùng xóa bài kiểm tra
            $stmt = $pdo->prepare("DELETE FROM tests WHERE id = ?");
            $stmt->execute([$test_id]);
            
            $pdo->commit();
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Log lỗi hoặc hiển thị thông báo (ở đây chọn die để dễ debug)
        die("Lỗi xóa bài thi: " . $e->getMessage());
    }
}

// 5. Quay về trang lớp học hoặc Dashboard
if ($class_id > 0) {
    header("Location: view_class.php?id=" . $class_id);
} else {
    header("Location: index.php");
}
exit();
?>