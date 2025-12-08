<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
    header('Location: /index.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// --- [QUAN TRỌNG] KIỂM TRA THÔNG TIN CÁ NHÂN ---
// Nếu chưa có MSSV hoặc Ngày sinh -> Bắt buộc chuyển sang trang cập nhật
$stmt = $pdo->prepare("SELECT student_code, dob FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($user_info['student_code']) || empty($user_info['dob'])) {
    header("Location: update_profile.php");
    exit();
}
// ------------------------------------------------

// Lấy danh sách lớp đã tham gia
$stmt = $pdo->prepare("
    SELECT c.*, u.name as teacher_name,
    (SELECT COUNT(*) FROM tests t WHERE t.class_id = c.id AND t.status = 'published') as active_tests
    FROM classes c
    JOIN class_members cm ON c.id = cm.class_id
    JOIN users u ON c.teacher_id = u.id
    WHERE cm.user_id = ?
    ORDER BY cm.joined_at DESC
");
$stmt->execute([$student_id]);
$my_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy thông báo thành công từ session (nếu có)
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['success_msg']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Góc học tập - OnlineTest</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-50 text-slate-800 h-screen flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-gray-100 border-r border-gray-200 hidden md:flex flex-col z-10">
        <div class="h-16 flex items-center px-6 border-b border-gray-100">
            <i class="fa-solid text-indigo-600 text-2xl mr-2"></i>
            <span class="text-xl font-bold text-slate-800">OnlineTest</span>
        </div>
        <nav class="flex-1 py-6 px-3 space-y-1">
            <a href="index.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg bg-indigo-50 text-indigo-600">
                <i class="fa-solid fa-book-open w-6 text-center mr-2 text-lg"></i> Lớp học của tôi
            </a>
            <a href="update_profile.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-600 hover:bg-gray-50 hover:text-slate-900 transition-colors">
                <i class="fa-solid fa-user-gear w-6 text-center mr-2 text-lg"></i> Hồ sơ cá nhân
            </a>
            <div class="pt-4 mt-4 border-t border-gray-100">
                <a href="/auth/logout.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-red-600 hover:bg-red-50">
                    <i class="fa-solid fa-right-from-bracket w-6 text-center mr-2"></i> Đăng xuất
                </a>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-200">
            <div class="flex items-center">
                <div class="h-9 w-9 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">SV</div>
                <div class="ml-3"><p class="text-sm font-medium text-slate-700 truncate w-32"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p></div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
            <h1 class="text-xl font-bold text-slate-800">Lớp học của bạn</h1>
        </header>

        <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
            
            <!-- Thông báo cập nhật thành công -->
            <?php if ($success_msg): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center shadow-sm fade-in">
                    <i class="fa-solid fa-circle-check mr-2"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Quick Actions Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Tham gia lớp -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:border-indigo-200 transition-all">
                    <h2 class="text-lg font-bold text-slate-800 mb-2"><i class="fa-solid fa-users-rectangle mr-2 text-indigo-500"></i>Tham gia lớp học</h2>
                    <p class="text-sm text-slate-500 mb-4">Nhập mã lớp do giáo viên cung cấp.</p>
                    <form action="join_class.php" method="POST" class="flex gap-2">
                        <input type="text" name="class_code" placeholder="Mã lớp (VD: 470CB6)" required class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 outline-none uppercase text-sm">
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors font-medium text-sm whitespace-nowrap">
                            Tham gia
                        </button>
                    </form>
                </div>
            </div>

            <h2 class="text-lg font-bold text-slate-700 mb-4 border-l-4 border-indigo-600 pl-3">Các lớp đã tham gia</h2>
            
            <?php if (empty($my_classes)): ?>
                <div class="text-center py-12 bg-white rounded-xl border border-dashed border-gray-300">
                    <p class="text-slate-500">Bạn chưa tham gia lớp học nào.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($my_classes as $class): ?>
                        <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all border border-gray-200 overflow-hidden flex flex-col h-full">
                            <div class="h-20 bg-gradient-to-r from-emerald-800 to-green-400 p-4">
                                <h3 class="text-white font-bold text-lg truncate"><?php echo htmlspecialchars($class['class_name']); ?></h3>
                                <p class="text-indigo-100 text-xs">GV: <?php echo htmlspecialchars($class['teacher_name']); ?></p>
                            </div>
                            <div class="p-5 flex-1 flex flex-col">
                                <p class="text-slate-500 text-sm mb-4 line-clamp-2 h-10">
                                    <?php echo !empty($class['class_description']) ? htmlspecialchars($class['class_description']) : 'Không có mô tả'; ?>
                                </p>
                                <div class="mt-auto flex items-center justify-between">
                                    <span class="text-xs font-medium bg-green-100 text-green-700 px-2 py-1 rounded">
                                        <?php echo $class['active_tests']; ?> bài thi đang mở
                                    </span>
                                    <a href="view_class.php?id=<?php echo $class['id']; ?>" class="text-black-600 hover:underline text-sm font-medium">
                                        Vào lớp <i class="fa-solid fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>