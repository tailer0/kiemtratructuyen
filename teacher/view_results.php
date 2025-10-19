<?php
require_once '../config.php';
// Bảo vệ trang
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}
if (!isset($_GET['test_id'])) {
    die("Thiếu ID bài kiểm tra.");
}

$test_id = $_GET['test_id'];
$teacher_id = $_SESSION['user_id'];

// Lấy thông tin bài test
$stmt = $pdo->prepare("SELECT title FROM tests WHERE id = ? AND teacher_id = ?");
$stmt->execute([$test_id, $teacher_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    die("Bài kiểm tra không tồn tại hoặc bạn không có quyền xem.");
}

// Lấy danh sách kết quả
$stmt = $pdo->prepare("SELECT id, student_name, student_id, score, start_time, end_time FROM test_attempts WHERE test_id = ? ORDER BY student_name");
$stmt->execute([$test_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy tất cả log gian lận cho bài test này
$stmt = $pdo->prepare("
    SELECT attempt_id, log_type, details, proof_image, timestamp 
    FROM cheating_logs 
    WHERE attempt_id IN (SELECT id FROM test_attempts WHERE test_id = ?)
");
$stmt->execute([$test_id]);
$logs_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nhóm các log theo từng lần làm bài (attempt_id)
$cheating_logs = [];
foreach ($logs_raw as $log) {
    $cheating_logs[$log['attempt_id']][] = $log;
}

// Định nghĩa mức độ vi phạm để tính toán tỷ lệ %
// Giả sử 20 lần vi phạm là mức độ rất cao (100%)
define('MAX_VIOLATIONS_FOR_PERCENTAGE', 20);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kết quả: <?php echo htmlspecialchars($test['title']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* CSS cho Modal xem ảnh */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0;
            width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8);
        }
        .modal-content {
            margin: 5% auto; display: block; max-width: 80%; max-height: 80%;
        }
        .close-modal {
            position: absolute; top: 15px; right: 35px; color: #f1f1f1;
            font-size: 40px; font-weight: bold; cursor: pointer;
        }
    </style>
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
                    <th>Mức độ gian lận (%)</th>
                    <th>Chi tiết vi phạm</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="5">Chưa có sinh viên nào nộp bài.</td></tr>
                <?php else: ?>
                    <?php foreach ($results as $res): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($res['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($res['student_id']); ?></td>
                        <td><strong><?php echo number_format($res['score'], 2); ?></strong></td>
                        <td>
                            <?php
                                $violation_count = isset($cheating_logs[$res['id']]) ? count($cheating_logs[$res['id']]) : 0;
                                $percentage = min(100, ($violation_count / MAX_VIOLATIONS_FOR_PERCENTAGE) * 100);
                                echo number_format($percentage, 0) . '%';
                            ?>
                        </td>
                        <td>
                            <?php if ($violation_count > 0): ?>
                                <strong><?php echo $violation_count; ?></strong> lần vi phạm.
                                <ul class="violation-list">
                                <?php foreach($cheating_logs[$res['id']] as $log): ?>
                                    <li>
                                        - <?php echo htmlspecialchars($log['details']); ?>
                                        <small>(lúc <?php echo date('H:i:s', strtotime($log['timestamp'])); ?>)</small>
                                        <?php if ($log['proof_image']): ?>
                                            <a href="#" class="view-proof" data-src="<?php echo htmlspecialchars($log['proof_image']); ?>">[Xem bằng chứng]</a>
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
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal để hiển thị ảnh -->
    <div id="imageModal" class="modal">
        <span class="close-modal">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            const closeBtn = document.querySelector('.close-modal');

            document.querySelectorAll('.view-proof').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    modal.style.display = 'block';
                    modalImg.src = this.dataset.src;
                });
            });

            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            // Đóng modal khi click ra ngoài ảnh
            window.addEventListener('click', function(e) {
                if (e.target == modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>

