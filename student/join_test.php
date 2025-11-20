<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
    header('Location: /index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invite_code = trim($_POST['invite_code'] ?? '');

    if (empty($invite_code)) {
        echo "<script>alert('Vui lòng nhập mã mời!'); window.location='index.php';</script>";
        exit();
    }

    // 1. Tìm bài test
    $stmt = $pdo->prepare("SELECT id, status FROM tests WHERE invite_code = ?");
    $stmt->execute([$invite_code]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        echo "<script>alert('Mã mời không hợp lệ!'); window.location='index.php';</script>";
        exit();
    }

    if ($test['status'] !== 'published') {
        echo "<script>alert('Bài kiểm tra này chưa mở hoặc đã đóng!'); window.location='index.php';</script>";
        exit();
    }

    // 2. Kiểm tra xem sinh viên đã làm bài này chưa (để báo lỗi sớm)
    $stmt = $pdo->prepare("SELECT id, end_time FROM test_attempts WHERE test_id = ? AND user_id = ?");
    $stmt->execute([$test['id'], $_SESSION['user_id']]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attempt && $attempt['end_time']) {
        // Đã nộp bài -> Chuyển sang xem kết quả
        header("Location: result.php?attempt_id=" . $attempt['id']);
        exit();
    }

    // 3. Thành công -> Chuyển sang trang làm bài
    // (Trang take_test.php sẽ tự động tạo attempt mới nếu chưa có)
    header("Location: take_test.php?test_id=" . $test['id']);
    exit();
} else {
    header('Location: index.php');
    exit();
}
?>