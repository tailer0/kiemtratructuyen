<?php
session_start(); // Đảm bảo session bắt đầu ở dòng đầu tiên
require_once dirname(__DIR__) . '/config.php'; // Đường dẫn an toàn hơn

// Nếu đã đăng nhập, chuyển về trang chủ
if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

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
    <title>Đăng ký - OnlineTest AI</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Space Grotesk & Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    
    <style>
        /* Background Gradient Mesh */
        body {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            font-family: 'Inter', sans-serif;
            color: white;
            overflow-x: hidden;
        }

        /* Glassmorphism Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Input Styling */
        .input-group input, .input-group select {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
        }
        .input-group input:focus, .input-group select:focus {
            border-color: #6366f1; /* Indigo-500 */
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            outline: none;
            background: rgba(15, 23, 42, 0.8);
        }
        
        /* Custom Select Styling */
        select option {
            background-color: #1e293b; /* Slate-800 for options */
            color: white;
        }

        /* Background Orbs Animation */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            animation: float 10s infinite ease-in-out;
        }
        .orb-1 { top: -5%; left: -5%; width: 350px; height: 350px; background: #06b6d4; animation-delay: 0s; }
        .orb-2 { bottom: -5%; right: -5%; width: 400px; height: 400px; background: #8b5cf6; animation-delay: -5s; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen relative py-10">

    <!-- Decorative Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="w-full max-w-lg p-6 relative z-10">
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="/" class="inline-flex items-center gap-2 group cursor-pointer">
                <i class="fa-solid fa-brain text-3xl text-indigo-500 group-hover:scale-110 transition-transform duration-300"></i>
                <span class="font-['Space_Grotesk'] font-bold text-2xl tracking-tighter text-white">OnlineTest<span class="text-indigo-500">.AI</span></span>
            </a>
            <h2 class="text-2xl font-bold mt-4 text-white">Tạo tài khoản mới</h2>
            <p class="text-gray-400 text-sm">Tham gia nền tảng thi cử tương lai</p>
        </div>

        <!-- Register Card -->
        <div class="glass-card rounded-2xl p-8 w-full">
            
            <!-- Error Notification -->
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 animate-pulse">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="space-y-5">
                
                <!-- Họ tên -->
                <div class="input-group">
                    <label for="name" class="block text-xs font-medium text-gray-400 mb-1 ml-1">Họ và tên</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-user text-gray-500"></i>
                        </div>
                        <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required class="w-full pl-10 pr-4 py-3 rounded-lg text-sm placeholder-gray-600" placeholder="Nguyễn Văn A">
                    </div>
                </div>

                <!-- Email -->
                <div class="input-group">
                    <label for="email" class="block text-xs font-medium text-gray-400 mb-1 ml-1">Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-envelope text-gray-500"></i>
                        </div>
                        <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required class="w-full pl-10 pr-4 py-3 rounded-lg text-sm placeholder-gray-600" placeholder="name@example.com">
                    </div>
                </div>

                <!-- Mật khẩu -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="input-group">
                        <label for="password" class="block text-xs font-medium text-gray-400 mb-1 ml-1">Mật khẩu</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-lock text-gray-500"></i>
                            </div>
                            <input type="password" id="password" name="password" required class="w-full pl-10 pr-4 py-3 rounded-lg text-sm placeholder-gray-600" placeholder="••••••">
                        </div>
                    </div>
                    <div class="input-group">
                        <label for="confirm_password" class="block text-xs font-medium text-gray-400 mb-1 ml-1">Xác nhận</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-check-double text-gray-500"></i>
                            </div>
                            <input type="password" id="confirm_password" name="confirm_password" required class="w-full pl-10 pr-4 py-3 rounded-lg text-sm placeholder-gray-600" placeholder="••••••">
                        </div>
                    </div>
                </div>

                <!-- Vai trò -->
                <div class="input-group">
                    <label for="role" class="block text-xs font-medium text-gray-400 mb-1 ml-1">Bạn là ai?</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-users text-gray-500"></i>
                        </div>
                        <select id="role" name="role" required class="w-full pl-10 pr-4 py-3 rounded-lg text-sm appearance-none cursor-pointer">
                            <!-- Tự động chọn nếu có tham số URL ?role=teacher -->
                            <option value="student" <?php echo (isset($_GET['role']) && $_GET['role'] != 'teacher') ? 'selected' : ''; ?>>Học sinh / Sinh viên</option>
                            <option value="teacher" <?php echo (isset($_GET['role']) && $_GET['role'] == 'teacher') ? 'selected' : ''; ?>>Giáo viên</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-chevron-down text-gray-500 text-xs"></i>
                        </div>
                    </div>
                </div>

                <!-- Điều khoản -->
                <div class="flex items-start gap-2 mt-2">
                    <input type="checkbox" id="terms" required class="mt-1 rounded bg-slate-800 border-gray-700 text-indigo-500 focus:ring-offset-0 focus:ring-indigo-500 cursor-pointer">
                    <label for="terms" class="text-xs text-gray-400 cursor-pointer select-none">
                        Tôi đồng ý với <a href="#" class="text-indigo-400 hover:underline">Điều khoản sử dụng</a> và <a href="#" class="text-indigo-400 hover:underline">Chính sách bảo mật</a>.
                    </label>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold py-3 px-4 rounded-lg hover:from-indigo-500 hover:to-purple-500 transition-all duration-300 shadow-lg shadow-indigo-500/30 transform hover:-translate-y-0.5 mt-6">
                    Đăng ký tài khoản
                </button>
            </form>
        </div>

        <!-- Login Link -->
        <p class="text-center mt-8 text-sm text-gray-400">
            Đã có tài khoản? 
            <a href="login.php" class="text-indigo-400 font-semibold hover:text-indigo-300 hover:underline transition-colors">Đăng nhập ngay</a>
        </p>
    </div>

</body>
</html>