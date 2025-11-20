<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
    header('Location: /index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['class_code']);
    $student_id = $_SESSION['user_id'];

    // Tìm lớp
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE class_code = ?");
    $stmt->execute([$code]);
    $class = $stmt->fetch();

    if ($class) {
        try {
            // Thêm vào lớp
            $stmt = $pdo->prepare("INSERT INTO class_members (class_id, user_id) VALUES (?, ?)");
            $stmt->execute([$class['id'], $student_id]);
            header("Location: index.php");
            exit();
        } catch (PDOException $e) {
            // 1062 = Duplicate entry (Đã tham gia rồi)
            if ($e->errorInfo[1] == 1062) {
                echo "<script>alert('Bạn đã tham gia lớp này rồi!'); window.location='index.php';</script>";
            } else {
                echo "Lỗi: " . $e->getMessage();
            }
        }
    } else {
        echo "<script>alert('Mã lớp không tồn tại!'); window.location='index.php';</script>";
    }
}
?>