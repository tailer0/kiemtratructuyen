<?php
// === PHẢI GỌI session_start() NGAY ĐẦU ===
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['class_name']);
    $desc = trim($_POST['class_description']);
    $teacher_id = $_SESSION['user_id'];
    
    if (empty($name)) {
        $error = "Vui lòng nhập tên lớp học.";
    } else {
        // Tạo mã lớp ngẫu nhiên 6 ký tự (Check trùng lặp nếu cần, ở đây giả định uniqid đủ tốt)
        $class_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        
        try {
            $stmt = $pdo->prepare("INSERT INTO classes (teacher_id, class_name, class_description, class_code) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$teacher_id, $name, $desc, $class_code])) {
                // Redirect về index
                header("Location: index.php");
                exit();
            } else {
                $error = "Không thể lưu vào cơ sở dữ liệu.";
            }
        } catch (PDOException $e) {
            // Bắt lỗi chi tiết từ SQL (Ví dụ: sai tên cột, lỗi kết nối...)
            $error = "Lỗi hệ thống: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo Lớp Học Mới</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-slate-800 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-lg w-full bg-white rounded-xl shadow-lg overflow-hidden">
        <!-- Header -->
        <div class="bg-indigo-600 px-6 py-4 border-b border-indigo-700">
            <h1 class="text-xl font-bold text-white flex items-center">
                <i class="fa-solid fa-chalkboard-user mr-3"></i> Tạo Lớp Học Mới
            </h1>
            <p class="text-indigo-100 text-sm mt-1">Tạo không gian học tập mới cho học sinh của bạn.</p>
        </div>

        <!-- Body -->
        <div class="p-6 sm:p-8">
            
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-lg p-4 mb-6 flex items-start">
                    <i class="fa-solid fa-circle-exclamation mt-1 mr-3"></i>
                    <div>
                        <p class="font-bold">Không thể tạo lớp</p>
                        <p class="text-sm"><?php echo $error; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Tên lớp -->
                <div class="mb-5">
                    <label for="class_name" class="block text-sm font-medium text-slate-700 mb-1">Tên lớp học <span class="text-red-500">*</span></label>
                    <input type="text" id="class_name" name="class_name" required 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all" 
                        placeholder="Ví dụ: Toán Cao Cấp A1 - HK1 2025">
                </div>

                <!-- Mô tả -->
                <div class="mb-6">
                    <label for="class_description" class="block text-sm font-medium text-slate-700 mb-1">Mô tả (Tùy chọn)</label>
                    <textarea id="class_description" name="class_description" rows="4" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all resize-none" 
                        placeholder="Nhập thông tin về môn học, lịch học hoặc ghi chú..."></textarea>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <a href="index.php" class="px-5 py-2.5 rounded-lg text-slate-600 font-medium hover:bg-gray-100 transition-colors">
                        Hủy bỏ
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all transform active:scale-95">
                        <i class="fa-solid fa-plus mr-2"></i> Tạo Lớp
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Footer Note -->
        <div class="bg-gray-50 px-6 py-3 text-center border-t border-gray-100">
            <p class="text-xs text-slate-500">Mã lớp sẽ được tạo tự động sau khi lưu.</p>
        </div>
    </div>

</body>
</html>