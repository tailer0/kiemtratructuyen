<?php
require_once '../config.php';

// Bảo vệ trang: Chỉ teacher mới được truy cập
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Lấy danh sách các bài test do giáo viên này tạo
$stmt = $pdo->prepare("SELECT id, title, invite_code, status, created_at FROM tests WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$teacher_id]);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bảng điều khiển của Giáo viên</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php include '../_partials/header.php'; ?>

    <div class="container">
        <h1>Bảng điều khiển của Giáo viên</h1>
        <p>Chào mừng, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>
        
        <a href="create_test.php" class="button">Tạo bài kiểm tra mới</a>

        <h2>Các bài kiểm tra của bạn</h2>
        <?php if (count($tests) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Tiêu đề</th>
                        <th>Mã mời</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests as $test): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($test['title']); ?></td>
                        <td><strong><?php echo $test['invite_code']; ?></strong></td>
                        <td>
                            <!-- *** BẮT ĐẦU PHẦN CẬP NHẬT *** -->
                            <form action="update_test_status.php" method="POST" style="display: inline-flex; align-items: center; gap: 10px;">
                                <input type="hidden" name="test_id" value="<?php echo $test['id']; ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="draft" <?php if ($test['status'] == 'draft') echo 'selected'; ?>>Bản nháp</option>
                                    <option value="published" <?php if ($test['status'] == 'published') echo 'selected'; ?>>Công bố</option>
                                    <option value="closed" <?php if ($test['status'] == 'closed') echo 'selected'; ?>>Đã đóng</option>
                                </select>
                            </form>
                            <!-- *** KẾT THÚC PHẦN CẬP NHẬT *** -->
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($test['created_at'])); ?></td>
                        <td>
                            <a href="view_results.php?test_id=<?php echo $test['id']; ?>" class="button">Xem kết quả</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Bạn chưa tạo bài kiểm tra nào.</p>
        <?php endif; ?>
    </div>
</body>
</html>

