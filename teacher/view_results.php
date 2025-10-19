<?php
require_once '../config.php';
require_once ROOT_PATH . '/_partials/Header.php';
// Bảo vệ trang
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}
if (!isset($_GET['test_id'])) {
    die("Thiếu ID bài kiểm tra.");
}

$test_id = $_GET['test_id'];

// Lấy thông tin bài test
$stmt = $pdo->prepare("SELECT title FROM tests WHERE id = ? AND teacher_id = ?");
$stmt->execute([$test_id, $_SESSION['user_id']]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    die("Bài kiểm tra không tồn tại hoặc bạn không có quyền xem.");
}

// Lấy danh sách kết quả
$stmt = $pdo->prepare("SELECT id, student_name, student_id, score, start_time, end_time FROM test_attempts WHERE test_id = ? ORDER BY student_name");
$stmt->execute([$test_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy log gian lận
$stmt = $pdo->prepare("SELECT attempt_id, log_type, details, proof_image, timestamp FROM cheating_logs WHERE attempt_id IN (SELECT id FROM test_attempts WHERE test_id = ?)");
$stmt->execute([$test_id]);
$logs_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$cheating_logs = [];
foreach ($logs_raw as $log) {
    $cheating_logs[$log['attempt_id']][] = $log;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <title>Kết quả: <?php echo htmlspecialchars($test['title']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php include '../_partials/header.php'; ?>
    <div class="container">
        <h1>Kết quả bài kiểm tra: <?php echo htmlspecialchars($test['title']); ?></h1>
        <a href="/teacher/index.php" class="button">Quay lại Dashboard</a>
        
        <table>
            <thead>
                <tr>
                    <th>Tên Sinh viên</th>
                    <th>MSSV</th>
                    <th>Điểm</th>
                    <th>Thời gian bắt đầu</th>
                    <th>Thời gian kết thúc</th>
                    <th>Hành vi gian lận</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $res): ?>
                <tr>
                    <td><?php echo htmlspecialchars($res['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($res['student_id']); ?></td>
                    <td><?php echo number_format($res['score'], 2); ?></td>
                    <td><?php echo $res['start_time']; ?></td>
                    <td><?php echo $res['end_time']; ?></td>
                    <td>
                        <?php if (isset($cheating_logs[$res['id']])): ?>
                            <strong><?php echo count($cheating_logs[$res['id']]); ?></strong> lần vi phạm.
                            <ul>
                            <?php foreach($cheating_logs[$res['id']] as $log): ?>
                                <li>
                                    <?php echo htmlspecialchars($log['details']); ?>
                                    (<?php echo $log['timestamp']; ?>)
                                    <?php if ($log['proof_image']): ?>
                                        <a href="#" onclick="showImage(this); return false;">Xem ảnh</a>
                                        <img src="<?php echo $log['proof_image']; ?>" style="display:none; max-width: 500px;">
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            Không có
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        function showImage(link) {
            const img = link.nextElementSibling;
            img.style.display = (img.style.display === 'none') ? 'block' : 'none';
        }
    </script>
</body>
</html>
