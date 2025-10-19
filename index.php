    <?php
    require_once 'config.php';
    require_once ROOT_PATH . '/_partials/Header.php';
    // Nếu người dùng đã đăng nhập, điều hướng theo vai trò
    if (isset($_SESSION['user_id'])) {
        $role = $_SESSION['user_role'];
        if ($role == 'admin') {
            header('Location: /admin/index.php');
            exit();
        } elseif ($role == 'teacher') {
            header('Location: /teacher/index.php');
            exit();
        } else {
            // Người dùng thông thường có thể ở lại hoặc chuyển đến trang hồ sơ
            // For now, let's just show a generic welcome
        }
    }

    // Lấy URL đăng nhập Google
    $login_url = $gClient->createAuthUrl();
    ?>

    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hệ thống Kiểm tra Trực tuyến</title>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
        <div class="container">
            <h1>Chào mừng đến với Hệ thống Kiểm tra Trực tuyến</h1>
            <p>Vui lòng đăng nhập để tiếp tục.</p>
            <a href="<?php echo $login_url; ?>" class="login-button">
                <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google logo">
                Đăng nhập với Google
            </a>
            <hr>
            <h2>Vào phòng thi</h2>
            <form action="/student/join.php" method="GET">
                <input type="text" name="code" placeholder="Nhập mã mời..." required>
                <button type="submit">Vào thi</button>
            </form>
        </div>
    </body>
    </html>
    
