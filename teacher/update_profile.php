<?php
session_start();
require_once '../config.php';

// Kiểm tra quyền truy cập
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$message = '';
$msg_type = ''; // success hoặc error

// Xử lý khi form được submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    
    // Có thể mở rộng thêm đổi mật khẩu hoặc avatar ở đây
    // $new_password = $_POST['password'] ...

    if (empty($name)) {
        $message = "Tên hiển thị không được để trống.";
        $msg_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            if ($stmt->execute([$name, $teacher_id])) {
                // Cập nhật lại Session để hiển thị tên mới ngay lập tức trên Header/Sidebar
                $_SESSION['user_name'] = $name;
                
                $message = "Cập nhật thông tin thành công!";
                $msg_type = 'success';
            } else {
                $message = "Không có thay đổi nào hoặc lỗi hệ thống.";
                $msg_type = 'error';
            }
        } catch (PDOException $e) {
            $message = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// Lấy thông tin hiện tại
$stmt = $pdo->prepare("SELECT name, email, avatar FROM users WHERE id = ?");
$stmt->execute([$teacher_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ Giáo viên - OnlineTest</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-50 text-slate-800 h-screen flex overflow-hidden">

    <!-- SIDEBAR (Copy từ index.php sang để giữ đồng bộ hoặc include) -->
    <aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col z-10">
        <div class="h-16 flex items-center px-6 border-b border-gray-100">
            <i class="fa-solid fa-graduation-cap text-indigo-600 text-2xl mr-2"></i>
            <span class="text-xl font-bold text-slate-800">OnlineTest</span>
        </div>
        <nav class="flex-1 py-6 px-3 space-y-1">
            <a href="index.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-600 hover:bg-gray-50 hover:text-slate-900 transition-colors">
                <i class="fa-solid fa-chart-pie w-6 text-center mr-2 text-lg"></i> Tổng quan
            </a>
            <!-- Item Active -->
            <a href="update_profile.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg bg-indigo-50 text-indigo-600">
                <i class="fa-solid fa-user-gear w-6 text-center mr-2 text-lg"></i> Hồ sơ cá nhân
            </a>
            
            <div class="pt-4 mt-4 border-t border-gray-100">
                <a href="../logout.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-red-600 hover:bg-red-50">
                    <i class="fa-solid fa-right-from-bracket w-6 text-center mr-2"></i> Đăng xuất
                </a>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-200">
            <div class="flex items-center">
                <div class="h-9 w-9 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">GV</div>
                <div class="ml-3"><p class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p></div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
            <h1 class="text-xl font-bold text-slate-800">Cài đặt tài khoản</h1>
        </header>

        <main class="flex-1 overflow-y-auto p-6 bg-gray-50 flex justify-center">
            <div class="w-full max-w-2xl">
                
                <!-- Thông báo -->
                <?php if ($message): ?>
                    <div class="mb-6 px-4 py-3 rounded-lg flex items-center <?php echo $msg_type == 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                        <i class="fa-solid <?php echo $msg_type == 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?> mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-2xl font-bold border-4 border-white shadow-sm">
                            <?php echo substr($user['name'], 0, 1); ?>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></h2>
                            <p class="text-sm text-gray-500">Giáo viên</p>
                        </div>
                    </div>

                    <form method="POST" class="p-6 space-y-6">
                        <!-- Email (Read-only) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email đăng nhập</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed">
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Email không thể thay đổi.</p>
                        </div>

                        <!-- Họ tên -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Họ và tên hiển thị</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fa-solid fa-user"></i></span>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <a href="index.php" class="px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 font-medium transition-colors">Hủy bỏ</a>
                            <button type="submit" class="px-6 py-2 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">
                                Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>