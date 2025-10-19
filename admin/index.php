<?php
require_once '../config.php';

// Bảo vệ trang: Chỉ admin mới được truy cập
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: /index.php');
    exit();
}

// Lấy các số liệu thống kê từ cơ sở dữ liệu
try {
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $total_tests = $pdo->query("SELECT COUNT(*) FROM tests")->fetchColumn();
    $total_attempts = $pdo->query("SELECT COUNT(*) FROM test_attempts")->fetchColumn();
} catch (Exception $e) {
    // Xử lý lỗi nếu không thể truy vấn CSDL
    $total_users = $total_teachers = $total_tests = $total_attempts = 'Lỗi';
    // error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bảng điều khiển của Quản trị viên</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .stats-container {
            display: flex;
            gap: 1.5rem;
            justify-content: space-around;
            margin-top: 2rem;
            text-align: center;
        }
        .stat-card {
            background-color: #e9f5ff;
            border-left: 5px solid #007bff;
            padding: 1.5rem;
            border-radius: 8px;
            flex-grow: 1;
        }
        .stat-card h3 {
            margin-top: 0;
            color: #0056b3;
        }
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
        }
        .admin-actions {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <?php include '../_partials/header.php'; ?>

    <div class="container">
        <h1>Bảng điều khiển của Quản trị viên</h1>
        <p>Chào mừng, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>

        <div class="stats-container">
            <div class="stat-card">
                <h3>Tổng người dùng</h3>
                <p class="number"><?php echo $total_users; ?></p>
            </div>
            <div class="stat-card">
                <h3>Số lượng giáo viên</h3>
                <p class="number"><?php echo $total_teachers; ?></p>
            </div>
            <div class="stat-card">
                <h3>Tổng bài kiểm tra</h3>
                <p class="number"><?php echo $total_tests; ?></p>
            </div>
            <div class="stat-card">
                <h3>Tổng lượt làm bài</h3>
                <p class="number"><?php echo $total_attempts; ?></p>
            </div>
        </div>

        <div class="admin-actions">
            <h2>Hành động</h2>
            <a href="manage_users.php" class="button">Quản lý Người dùng</a>
            <!-- Thêm các liên kết quản lý khác ở đây nếu cần -->
        </div>

    </div>
</body>
</html>
