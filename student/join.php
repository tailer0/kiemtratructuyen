<?php
require_once '../config.php';
require_once ROOT_PATH . '/_partials/Header.php';
if (!isset($_GET['code'])) {
    die("Thiếu mã mời.");
}

$invite_code = $_GET['code'];

// Tìm bài test với mã mời tương ứng
$stmt = $pdo->prepare("SELECT id, title FROM tests WHERE invite_code = ? AND status = 'published'");
$stmt->execute([$invite_code]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    die("Mã mời không hợp lệ hoặc bài kiểm tra chưa được công bố.");
}

// Xử lý khi sinh viên nộp form thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_name = $_POST['student_name'];
    $student_dob = $_POST['student_dob'];
    $student_id = $_POST['student_id'];
    $test_id = $test['id'];

    // Kiểm tra xem sinh viên này đã làm bài chưa
    $checkStmt = $pdo->prepare("SELECT id FROM test_attempts WHERE test_id = ? AND student_id = ?");
    $checkStmt->execute([$test_id, $student_id]);
    if ($checkStmt->fetch()) {
        die("Bạn đã hoàn thành bài kiểm tra này rồi.");
    }

    // Tạo một bản ghi mới cho lần làm bài này
    $insertStmt = $pdo->prepare(
        "INSERT INTO test_attempts (test_id, student_name, student_dob, student_id, start_time, ip_address) 
         VALUES (?, ?, ?, ?, NOW(), ?)"
    );
    $insertStmt->execute([$test_id, $student_name, $student_dob, $student_id, $_SERVER['REMOTE_ADDR']]);
    
    $attempt_id = $pdo->lastInsertId();

    // Chuyển hướng đến trang làm bài
    header("Location: /student/take_test.php?attempt_id=" . $attempt_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác nhận thông tin</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Bài kiểm tra: <?php echo htmlspecialchars($test['title']); ?></h1>
        <p>Vui lòng điền đầy đủ thông tin để bắt đầu làm bài.</p>
        <form method="POST">
            <label for="student_name">Họ và Tên:</label>
            <input type="text" id="student_name" name="student_name" required>

            <label for="student_id">Mã số sinh viên:</label>
            <input type="text" id="student_id" name="student_id" required>
            
            <label for="student_dob">Ngày sinh:</label>
            <input type="date" id="student_dob" name="student_dob" required>

            <button type="submit">Bắt đầu làm bài</button>
        </form>
    </div>
</body>
</html>
