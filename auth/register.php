<?php
session_start(); // Đảm bảo session bắt đầu ở dòng đầu tiên
require_once dirname(__DIR__) . '/config.php'; // Đường dẫn an toàn hơn

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    // Sửa lỗi logic: Cột trong DB là 'user'
    if ($role == 'student') {
        $role = 'user';
    }

    if ($password !== $confirm_password) {
        $error = "Mật khẩu không khớp. Vui lòng thử lại.";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } elseif (!in_array($role, ['user', 'teacher'])) {
        $error = "Vai trò không hợp lệ.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email này đã được sử dụng. Vui lòng chọn email khác.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // Đặt avatar mặc định
            $default_avatar = '/assets/images/default-avatar.png';

            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, role, avatar) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $email, $hashed_password, $role, $default_avatar]);
            
            $user_id = $pdo->lastInsertId();
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = $role;
            $_SESSION['user_avatar'] = $default_avatar; // Thiết lập session avatar

            header("Location: /index.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - OnlineTest</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: Arial, sans-serif;
        }
        .register-container {
            width: 100%;
            max-width: 450px; /* Rộng hơn một chút cho form đăng ký */
            margin: 20px;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: #fff;
            text-align: center;
        }
        .register-container h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        .register-container p {
            color: #666;
            margin-bottom: 30px;
        }

        /* Form */
        .form-group {
            text-align: left; /* Căn lề trái cho label */
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 15px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px; /* Tăng padding */
            background: #f7f7f7;
            border: 1px solid #eee;
            border-radius: 8px;
            box-sizing: border-box; /* Quan trọng */
        }
        .form-group input:focus,
        .form-group select:focus {
            background: #fff;
            border-color: #007bff;
            box-shadow: none;
            outline: none;
        }

        .button.button-primary {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
        }

        .login-link {
            margin-top: 25px;
            color: #555;
            font-size: 15px;
        }
        .login-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>Tạo tài khoản mới</h1>
        <p>Bắt đầu hành trình của bạn ngay hôm nay</p>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="name">Họ và tên:</label>
                <input type="text" id="name" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu (ít nhất 6 ký tự):</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Xác nhận mật khẩu:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div class="form-group">
                <label for="role">Bạn là:</label>
                <select id="role" name="role" required>
                    <option value="student">Học sinh / Sinh viên</option>
                    <option value="teacher">Giáo viên</option>
                </select>
            </div>
            <button type="submit" class="button button-primary">Đăng ký</button>
        </form>

        <p class="login-link">
            Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a>
        </p>
    </div>
</body>
</html>

