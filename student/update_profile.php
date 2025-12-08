<?php
session_start();
require_once '../config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 1. Xử lý khi form được submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $student_code = trim($_POST['student_code'] ?? '');
    $dob = $_POST['dob'] ?? '';

    if (empty($name) || empty($student_code) || empty($dob)) {
        $error = "Vui lòng điền đầy đủ tất cả các trường.";
    } else {
        try {
            // Cập nhật thông tin
            $stmt = $pdo->prepare("UPDATE users SET name = ?, student_code = ?, dob = ? WHERE id = ?");
            if ($stmt->execute([$name, $student_code, $dob, $user_id])) {
                // Cập nhật lại session name nếu đổi tên
                $_SESSION['user_name'] = $name;
                
                // Thành công -> Về trang chủ
                $_SESSION['success_msg'] = "Cập nhật thông tin thành công!";
                header("Location: index.php");
                exit();
            } else {
                $error = "Lỗi hệ thống, vui lòng thử lại.";
            }
        } catch (PDOException $e) {
            $error = "Lỗi: " . $e->getMessage();
        }
    }
}

// 2. Lấy thông tin hiện tại để điền vào form
$stmt = $pdo->prepare("SELECT name, student_code, dob, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật thông tin cá nhân</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-green-600 p-6 text-center">
            <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4 backdrop-blur-sm text-white text-2xl">
                <i class="fa-solid fa-user-pen"></i>
            </div>
            <h2 class="text-2xl font-bold text-white">Cập nhật hồ sơ</h2>
            <p class="text-green-100 text-sm mt-1">Vui lòng hoàn thiện thông tin để tiếp tục.</p>
        </div>

        <div class="p-8">
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-6 text-sm flex items-center border border-red-100">
                    <i class="fa-solid fa-circle-exclamation mr-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Email (Readonly) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email đăng nhập</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" disabled class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
                </div>

                <!-- Họ và tên -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Họ và Tên <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                </div>

                <!-- MSSV -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mã số sinh viên (MSSV) <span class="text-red-500">*</span></label>
                    <input type="text" name="student_code" value="<?php echo htmlspecialchars($user['student_code'] ?? ''); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all uppercase" >
                </div>

                <!-- Ngày sinh -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ngày sinh <span class="text-red-500">*</span></label>
                    <input type="date" name="dob" value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                </div>

                <button type="submit" class="w-full bg-green-600 text-white py-2.5 rounded-lg font-bold hover:bg-green-700 transition-all shadow-lg shadow-green-200">
                    Lưu & Tiếp tục
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="/auth/logout.php" class="text-sm text-gray-400 hover:text-gray-600">Đăng xuất</a>
            </div>
        </div>
    </div>

</body>
</html>