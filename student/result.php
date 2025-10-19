<?php
require_once '../config.php';
require_once ROOT_PATH . '/_partials/Header.php';
if (!isset($_GET['attempt_id'])) {
    die("Không tìm thấy kết quả.");
}

$attempt_id = $_GET['attempt_id'];

// Lấy điểm số từ DB
$stmt = $pdo->prepare("SELECT score, student_name FROM test_attempts WHERE id = ?");
$stmt->execute([$attempt_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    die("Không tìm thấy kết quả cho lần làm bài này.");
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kết quả bài kiểm tra</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Hoàn thành bài kiểm tra!</h1>
        <p>Cảm ơn bạn, <?php echo htmlspecialchars($result['student_name']); ?>.</p>
        <h2>Điểm số của bạn là:</h2>
        <p style="font-size: 2rem; font-weight: bold; color: #28a745;">
            <?php echo number_format($result['score'], 2); ?> / 10
        </p>
        <p>Bạn không thể xem lại đáp án chi tiết. Vui lòng đóng trang này.</p>
        <a href="/" class="button">Về trang chủ</a>
    </div>
</body>
</html>
