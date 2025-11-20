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

            // QUAN TRỌNG: Chuyển hướng về trang chủ để bộ định tuyến index.php xử lý
            header("Location: ../index.php");
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
    <title>Đăng nhập - OnlineTest AI</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Space Grotesk & Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    
    <style>
        /* Background Gradient Mesh (Giống trang chủ) */
        body {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            font-family: 'Inter', sans-serif;
            color: white;
            overflow: hidden; /* Ẩn thanh cuộn để nền full đẹp hơn */
        }

        /* Glassmorphism Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Input Fields Styling */
        .input-group input {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
        }
        .input-group input:focus {
            border-color: #6366f1; /* Indigo-500 */
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            outline: none;
            background: rgba(15, 23, 42, 0.8);
        }
        
        /* Floating Labels Animation (Optional - giữ đơn giản bằng placeholder trước) */

        /* Background Orbs Animation */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            animation: float 10s infinite ease-in-out;
        }
        .orb-1 { top: -10%; left: -10%; width: 300px; height: 300px; background: #4f46e5; animation-delay: 0s; }
        .orb-2 { bottom: -10%; right: -10%; width: 400px; height: 400px; background: #db2777; animation-delay: -5s; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(20px, -20px); }
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen relative">

    <!-- Decorative Background Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="w-full max-w-md p-6">
        <!-- Logo & Branding -->
        <div class="text-center mb-8">
            <a href="/" class="inline-flex items-center gap-2 group">
                <i class="fa-solid fa-brain text-3xl text-indigo-500 group-hover:scale-110 transition-transform duration-300"></i>
                <span class="font-['Space_Grotesk'] font-bold text-2xl tracking-tighter text-white">OnlineTest<span class="text-indigo-500">.AI</span></span>
            </a>
            <p class="text-gray-400 mt-2 text-sm">Đăng nhập để tiếp tục hành trình</p>
        </div>

        <!-- Login Card -->
        <div class="glass-card rounded-2xl p-8 w-full">
            
            <!-- Google Login Button -->
            <a href="<?php echo htmlspecialchars($google_login_url); ?>" class="flex items-center justify-center gap-3 w-full bg-white text-gray-800 font-semibold py-3 px-4 rounded-lg hover:bg-gray-100 transition-all duration-200 mb-6 group">
                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M21.35 12.24c0-.79-.07-1.54-.18-2.29H12v4.34h5.24c-.22 1.41-.88 2.6-1.9 3.4v2.8h3.58c2.08-1.92 3.28-4.74 3.28-8.25z"/>
                    <path fill="#34A853" d="M12 22c2.97 0 5.45-.98 7.28-2.66l-3.58-2.8c-.98.66-2.23 1.06-3.7 1.06-2.85 0-5.27-1.93-6.13-4.52H2.18v2.88C3.99 20.04 7.7 22 12 22z"/>
                    <path fill="#FBBC05" d="M5.87 14.36c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.3L2.18 9.18C1.43 10.65 1 12.25 1 14s.43 3.35 1.18 4.82l3.69-2.86z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 2.96 2.18 5.7L5.87 8.58C6.73 6.01 9.15 4.38 12 4.38z"/>
                </svg>
                <span>Tiếp tục với Google</span>
            </a>

            <!-- Divider -->
            <div class="relative flex items-center justify-center mb-6">
                <div class="border-t border-gray-700 w-full absolute"></div>
                <span class="bg-[#0f172a] px-3 text-xs text-gray-500 relative uppercase tracking-widest">hoặc</span>
            </div>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 animate-pulse">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="login.php" method="POST" class="space-y-5">
                <div class="input-group">
                    <label for="email" class="block text-xs font-medium text-gray-400 mb-1 ml-1">Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-regular fa-envelope text-gray-500"></i>
                        </div>
                        <input type="email" id="email" name="email" required class="w-full pl-10 pr-4 py-3 rounded-lg text-sm placeholder-gray-600" placeholder="name@example.com">
                    </div>
                </div>

                <div class="input-group">
                    <label for="password" class="block text-xs font-medium text-gray-400 mb-1 ml-1">Mật khẩu</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-gray-500"></i>
                        </div>
                        <input type="password" id="password" name="password" required class="w-full pl-10 pr-10 py-3 rounded-lg text-sm placeholder-gray-600" placeholder="••••••••">
                        <!-- Toggle Password Visibility Button (Optional JS can be added) -->
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs">
                    <label class="flex items-center text-gray-400 cursor-pointer hover:text-gray-300">
                        <input type="checkbox" class="mr-2 rounded bg-slate-800 border-gray-700 text-indigo-500 focus:ring-offset-0 focus:ring-indigo-500">
                        Ghi nhớ tôi
                    </label>
                    <a href="#" class="text-indigo-400 hover:text-indigo-300 transition-colors">Quên mật khẩu?</a>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold py-3 px-4 rounded-lg hover:from-indigo-500 hover:to-purple-500 transition-all duration-300 shadow-lg shadow-indigo-500/30 transform hover:-translate-y-0.5">
                    Đăng nhập
                </button>
            </form>
        </div>

        <!-- Register Link -->
        <p class="text-center mt-8 text-sm text-gray-400">
            Chưa có tài khoản? 
            <a href="register.php" class="text-indigo-400 font-semibold hover:text-indigo-300 hover:underline transition-colors">Đăng ký ngay</a>
        </p>
    </div>

</body>
</html>