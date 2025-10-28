<?php
require_once '../config.php';

// --- XỬ LÝ LOGIC VÀO PHÒNG THI ---
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invite_code'])) {
    $invite_code = trim($_POST['invite_code']);
    if (empty($invite_code)) {
        $error = "Vui lòng nhập mã mời.";
    } else {
        // Chuyển đến trang join.php (trong thư mục student) để xử lý tiếp
        // Đây là file cũ của bạn dùng để nhập thông tin sinh viên
        header("Location: /student/join.php?code=" . urlencode($invite_code));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vào phòng thi - Hệ thống Kiểm tra Trực tuyến</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* CSS cho trang nhập mã */
        .join-page-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding-top: 50px;
            text-align: center;
        }
        .join-test-box {
            width: 100%;
            max-width: 450px;
            margin-top: 30px;
            padding: 30px 35px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .join-test-box h2 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        .join-test-box p {
            color: #666;
            margin-bottom: 25px;
        }
        .join-test-box .form-group {
            text-align: left;
            margin-bottom: 15px;
        }
        .join-test-box .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .join-test-box .form-group input {
            width: 100%;
            box-sizing: border-box; 
        }
        .join-test-box .button {
            width: 100%;
            padding: 12px;
        }
    </style>
</head>
<body>
    <?php include ROOT_PATH . '/_partials/header.php';?>

    <div class="container">
        <div class="main-content join-page-container">

            <div class="join-test-box">
                <h2>Tham gia bài kiểm tra</h2>
                <p>Nhập mã mời được cung cấp bởi giáo viên của bạn.</p>
                
                <?php if ($error): ?>
                    <div class="alert error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="join_test.php" method="POST">
                    <div class="form-group">
                        <label for="invite_code">Mã mời:</label>
                        <input type="text" id="invite_code" name="invite_code" placeholder="Nhập mã..." required>
                    </div>
                    <button type="submit" class="button">Vào phòng thi</button>
                </form>
            </div>

            <?php if (!isset($_SESSION['user_id'])): ?>
                <p style="margin-top: 25px; font-size: 15px;">
                    Bạn là giáo viên hoặc admin? <a href="/auth/login.php">Đăng nhập tại đây</a>.
                </p>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>
