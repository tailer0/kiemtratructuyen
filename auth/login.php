<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

// Nếu đã đăng nhập, chuyển về trang chủ
if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Tìm người dùng bằng email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['password']) {
        // Nếu tìm thấy người dùng và họ có mật khẩu
        if (password_verify($password, $user['password'])) {
            // Mật khẩu chính xác
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_avatar'] = $user['avatar']; // Thêm avatar vào session

            header("Location: /index.php");
            exit();
        } else {
            $error = "Email hoặc mật khẩu không chính xác.";
        }
    } elseif ($user && !$user['password']) {
        $error = "Tài khoản này được đăng ký bằng Google. Vui lòng đăng nhập bằng Google.";
    }
    else {
        $error = "Email hoặc mật khẩu không chính xác.";
    }
}

// Lấy link đăng nhập Google
require_once ROOT_PATH . '/vendor/autoload.php';
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URL);
$client->addScope("email");
$client->addScope("profile");
$google_login_url = $client->createAuthUrl();

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - OnlineTest</title>
    <link rel="stylesheet" href="/assets/css/style.css"> <!-- Link tới file CSS chung -->
    <style>
        /* CSS dành riêng cho trang login */
        body {
            background-color: #f0f2f5; /* Màu nền xám nhạt */
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: Arial, sans-serif;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            margin: 20px;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: #fff;
            text-align: center;
        }
        .login-container h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        .login-container p {
            color: #666;
            margin-bottom: 30px;
        }

        /* Nút Google */
        .google-login {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
            color: #555;
            font-size: 16px;
            text-decoration: none;
            transition: background 0.3s, box-shadow 0.3s;
        }
        .google-login:hover {
            background: #f9f9f9;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .google-login svg {
            width: 20px;
            height: 20px;
            margin-right: 12px;
        }

        /* Dấu gạch "hoặc" */
        .or-divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #aaa;
            margin: 30px 0;
            font-size: 14px;
        }
        .or-divider::before,
        .or-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #eee;
        }
        .or-divider:not(:empty)::before {
            margin-right: .25em;
        }
        .or-divider:not(:empty)::after {
            margin-left: .25em;
        }

        /* Form */
        .form-group input {
            background: #f7f7f7;
            border: 1px solid #eee;
            margin-bottom: 15px; /* Thêm khoảng cách */
        }
        .form-group input:focus {
            background: #fff;
            border-color: #007bff;
            box-shadow: none;
        }

        /* Nút Đăng nhập */
        .button.button-primary {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: bold;
        }

        .register-link {
            margin-top: 25px;
            color: #555;
            font-size: 15px;
        }
        .register-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Chào mừng trở lại!</h1>
        <p>Đăng nhập để tiếp tục</p>

        <!-- Nút Google lên trên -->
        <a href="<?php echo htmlspecialchars($google_login_url); ?>" class="google-login">
            <svg viewBox="0 0 24 24" preserveAspectRatio="xMidYMid meet">
                <path fill="#4285F4" d="M21.35 12.24c0-.79-.07-1.54-.18-2.29H12v4.34h5.24c-.22 1.41-.88 2.6-1.9 3.4v2.8h3.58c2.08-1.92 3.28-4.74 3.28-8.25z"></path>
                <path fill="#34A853" d="M12 22c2.97 0 5.45-.98 7.28-2.66l-3.58-2.8c-.98.66-2.23 1.06-3.7 1.06-2.85 0-5.27-1.93-6.13-4.52H2.18v2.88C3.99 20.04 7.7 22 12 22z"></path>
                <path fill="#FBBC05" d="M5.87 14.36c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.3L2.18 9.18C1.43 10.65 1 12.25 1 14s.43 3.35 1.18 4.82l3.69-2.86z"></path>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 2.96 2.18 5.7L5.87 8.58C6.73 6.01 9.15 4.38 12 4.38z"></D></path>
                <path fill="none" d="M0 0h24v24H0z"></path>
            </svg>
            <span>Đăng nhập bằng Google</span>
        </a>

        <div class="or-divider">hoặc đăng nhập bằng email</div>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email" style="display:none;">Email:</label>
                <input type="email" id="email" name="email" placeholder="Email của bạn" required>
            </div>
            <div class="form-group">
                <label for="password" style="display:none;">Mật khẩu:</label>
                <input type="password" id="password" name="password" placeholder="Mật khẩu" required>
            </div>
            <button type="submit" class="button button-primary">Đăng nhập</button>
        </form>

        <p class="register-link">
            Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
        </p>
    </div>
</body>
</html>

