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

// LPY: Cập nhật SQL để lấy thêm student_dob (ngày sinh)
$stmt = $pdo->prepare("SELECT id, student_name, student_id, student_dob, score, start_time, end_time FROM test_attempts WHERE test_id = ? ORDER BY student_name");
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
            align-items: center; justify-content: center;
        }
        .modal-content {
            margin: auto; display: block; max-width: 80%; max-height: 80%;
            border-radius: 5px;
        }
        .close-modal {
            position: absolute; top: 15px; right: 35px; color: #f1f1f1;
            font-size: 40px; font-weight: bold; cursor: pointer;
            transition: 0.3s;
        }
        .close-modal:hover {
            color: #bbb;
        }
        
        /* LPY: CSS cho danh sách vi phạm ẩn/hiện */
        .violation-list {
            display: none; /* Ẩn danh sách chi tiết ban đầu */
            padding-left: 20px;
            text-align: left;
            margin: 5px 0 0 0;
            list-style-type: none;
            background: #fdfdfd;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .violation-trigger {
            cursor: pointer;
            color: #007bff;
            text-decoration: underline;
            font-weight: bold;
        }
        .violation-trigger:hover {
            color: #0056b3;
        }

        /* LPY: Thêm style cho nút xuất Excel */
        .button-secondary {
            background-color: #6c757d;
        }
        .button-secondary:hover {
            background-color: #5a6268;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../_partials/header.php'; ?>
    <div class="container">
        <div class="header-actions">
            <h1>Kết quả bài kiểm tra: <?php echo htmlspecialchars($test['title']); ?></h1>
            <div>
                <a href="/teacher/index.php" class="button">Quay lại</a>
                <!-- LPY: Thêm nút xuất Excel -->
                <a href="export_excel.php?test_id=<?php echo $test_id; ?>" class="button button-secondary">Xuất Excel (CSV)</a>
            </div>
        </div>
        
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
                                $color = $percentage > 50 ? 'red' : ($percentage > 20 ? 'orange' : 'inherit');
                                echo '<strong style="color: '.$color.';">' . number_format($percentage, 0) . '%</strong>';
                            ?>
                        </td>
                        <td>
                            <?php if ($violation_count > 0): ?>
                                <!-- LPY: Tạo trigger để nhấp vào -->
                                <strong class="violation-trigger" data-target="list-<?php echo $res['id']; ?>">
                                    <?php echo $violation_count; ?> lần vi phạm (Nhấn để xem)
                                </strong>
                                <ul class="violation-list" id="list-<?php echo $res['id']; ?>">
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
            // --- Logic cho Modal xem ảnh ---
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            const closeBtn = document.querySelector('.close-modal');

            document.querySelectorAll('.view-proof').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    modal.style.display = 'flex'; 
                    modalImg.src = this.dataset.src;
                });
            });

            function closeModal() {
                modal.style.display = 'none';
            }
            closeBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === "Escape" && modal.style.display === 'flex') {
                    closeModal();
                }
            });

            // --- LPY: Logic mới cho danh sách vi phạm ẩn/hiện ---
            document.querySelectorAll('.violation-trigger').forEach(trigger => {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.dataset.target;
                    const targetList = document.getElementById(targetId);
                    if (targetList) {
                        // Chuyển đổi trạng thái hiển thị
                        const isHidden = targetList.style.display === 'none' || targetList.style.display === '';
                        targetList.style.display = isHidden ? 'block' : 'none';
                        // Cập nhật nội dung trigger
                        this.textContent = isHidden 
                            ? '<?php echo $violation_count; ?> lần vi phạm (Nhấn để ẩn)' 
                            : '<?php echo $violation_count; ?> lần vi phạm (Nhấn để xem)';
                    }
                });
            });
        });
    </script>
</body>
</html>

