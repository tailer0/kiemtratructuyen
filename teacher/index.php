<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

// Kiểm tra quyền truy cập
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';

// Xóa message sau khi lấy để không hiện lại khi refresh
unset($_SESSION['success_msg']);
unset($_SESSION['error_msg']);

// --- 1. LẤY THỐNG KÊ ---
try {
    // Đếm số lớp
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $count_classes = $stmt->fetchColumn();

    // Đếm số học sinh (Tổng số thành viên trong các lớp của GV này)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_members cm JOIN classes c ON cm.class_id = c.id WHERE c.teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $count_students = $stmt->fetchColumn();

    // Đếm số bài kiểm tra
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tests WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $count_tests = $stmt->fetchColumn();

    // --- 2. LẤY DANH SÁCH LỚP HỌC ---
    // Kèm theo đếm số học sinh và số bài thi của từng lớp
    $stmt = $pdo->prepare("
        SELECT c.*, 
        (SELECT COUNT(*) FROM class_members cm WHERE cm.class_id = c.id) as student_count,
        (SELECT COUNT(*) FROM tests t WHERE t.class_id = c.id) as test_count
        FROM classes c 
        WHERE c.teacher_id = ? 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng điều khiển Giáo viên - OnlineTest</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c7c7c7; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #a0a0a0; }
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5', // Indigo 600
                        secondary: '#64748b', // Slate 500
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-slate-800 h-screen flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col z-10">
        <div class="h-16 flex items-center px-6 border-b border-gray-100">
            <i class="fa-solid text-primary text-2xl mr-2"></i>
            <span class="text-xl font-bold text-slate-800">OnlineTest</span>
        </div>

        <nav class="flex-1 py-6 px-3 space-y-1 overflow-y-auto">
            <a href="index.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg bg-indigo-50 text-primary group">
                <i class="fa-solid fa-chart-pie w-6 text-center mr-2 text-lg"></i>
                Tổng quan
            </a>
            <a href="update_profile.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-600 hover:bg-gray-50 hover:text-slate-900 transition-colors">
                <i class="fa-solid fa-user-gear w-6 text-center mr-2 text-lg"></i> 
                Hồ sơ cá nhân
            </a>
            <a href="//" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-600 hover:bg-gray-50 hover:text-slate-900 transition-colors">
                <i class="fa-solid fa- w-6 text-center mr-2 text-lg"></i> 
                Hướng dẫn sử dụng
            </a>
            <div class="pt-4 mt-4 border-t border-gray-100">
                <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Tài khoản</p>
                <a href="/auth/logout.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-red-600 hover:bg-red-50">
                    <i class="fa-solid fa-right-from-bracket w-6 text-center mr-2"></i> Đăng xuất
                </a>
            </div>
        </nav>
        
        <div class="p-4 border-t border-gray-200">
            <div class="flex items-center">
                <div class="h-9 w-9 rounded-full bg-indigo-100 flex items-center justify-center text-primary font-bold">GV</div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Giáo viên'); ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <header class="md:hidden h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4">
            <div class="flex items-center">
                <i class="fa-solid fa-graduation-cap text-primary text-xl mr-2"></i>
                <span class="font-bold text-slate-800">OnlineTest</span>
            </div>
            <button class="text-slate-500 hover:text-slate-700"><i class="fa-solid fa-bars text-xl"></i></button>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8 bg-gray-50">
            <div class="max-w-7xl mx-auto">
                
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800">Quản lý Lớp học</h1>
                        <p class="text-slate-500 mt-1">Danh sách các lớp học và bài kiểm tra trong lớp.</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <a href="create_class.php" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg font-medium shadow-md hover:bg-indigo-700 transition-all shadow-indigo-500/30">
                            <i class="fa-solid fa-users-rectangle mr-2"></i> Tạo lớp mới
                        </a>
                    </div>
                </div>

                <!-- SESSION NOTIFICATIONS -->
                <?php if ($success_msg): ?>
                    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative fade-in" role="alert">
                        <strong class="font-bold">Thành công!</strong>
                        <span class="block sm:inline"><?php echo htmlspecialchars($success_msg); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative fade-in" role="alert">
                        <strong class="font-bold">Lỗi!</strong>
                        <span class="block sm:inline"><?php echo htmlspecialchars($error_msg); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center">
                        <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 mr-4">
                            <i class="fa-solid fa-chalkboard-user text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-500">Lớp học</p>
                            <h3 class="text-2xl font-bold text-slate-800"><?php echo $count_classes; ?></h3>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center">
                        <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 mr-4">
                            <i class="fa-solid fa-users text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-500">Học sinh</p>
                            <h3 class="text-2xl font-bold text-slate-800"><?php echo $count_students; ?></h3>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center">
                        <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 mr-4">
                            <i class="fa-solid fa-file-pen text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-500">Bài kiểm tra</p>
                            <h3 class="text-2xl font-bold text-slate-800"><?php echo $count_tests; ?></h3>
                        </div>
                    </div>
                </div>

                <!-- CLASSES LIST -->
                <div class="fade-in">
                    <h2 class="text-lg font-bold text-slate-700 mb-4 border-l-4 border-primary pl-3">Danh sách lớp của tôi</h2>
                    
                    <?php if (empty($classes)): ?>
                        <div class="bg-white rounded-xl p-8 text-center border border-dashed border-gray-300">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-50 text-indigo-500 mb-4">
                                <i class="fa-solid fa-chalkboard text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900">Chưa có lớp học nào</h3>
                            <p class="text-slate-500 mt-1 mb-6">Bắt đầu bằng việc tạo một lớp học mới để quản lý học sinh và bài thi.</p>
                            <a href="create_class.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                <i class="fa-solid fa-plus mr-2"></i> Tạo lớp ngay
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($classes as $class): ?>
                                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-300 border border-gray-200 flex flex-col overflow-hidden group h-full relative">
                                    
                                    <!-- Delete Class Button -->
                                    <a href="delete_class.php?id=<?php echo $class['id']; ?>" onclick="return confirm('Bạn có chắc muốn XÓA lớp này?\nToàn bộ bài thi và dữ liệu học sinh trong lớp sẽ bị xóa vĩnh viễn!')" class="absolute top-3 right-3 z-10 w-8 h-8 bg-white/20 backdrop-blur-md text-white hover:bg-red-500 hover:text-white rounded-full flex items-center justify-center transition-all" title="Xóa lớp học">
                                        <i class="fa-solid fa-trash text-sm"></i>
                                    </a>

                                    <div class="h-24 bg-gradient-to-r from-violet-500 to-rose-600 p-5 relative">
                                        <h3 class="text-white font-bold text-lg truncate pr-8">
                                            <a href="view_class.php?id=<?php echo $class['id']; ?>" class="hover:underline">
                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                            </a>
                                        </h3>
                                    </div>
                                    <div class="p-5 flex-1 flex flex-col">
                                        <p class="text-slate-500 text-sm mb-4 line-clamp-2 h-10">
                                            <?php echo !empty($class['class_description']) ? htmlspecialchars($class['class_description']) : 'Chưa có mô tả'; ?>
                                        </p>
                                        
                                        <div class="grid grid-cols-2 gap-4 mb-4">
                                            <div class="bg-gray-50 rounded-lg p-2 text-center">
                                                <span class="block text-xs text-slate-400 uppercase">Học sinh</span>
                                                <span class="font-bold text-slate-700"><?php echo $class['student_count']; ?></span>
                                            </div>
                                            <div class="bg-gray-50 rounded-lg p-2 text-center">
                                                <span class="block text-xs text-slate-400 uppercase">Bài thi</span>
                                                <span class="font-bold text-slate-700"><?php echo $class['test_count']; ?></span>
                                            </div>
                                        </div>

                                        <div class="mt-auto">
                                            <!-- Copy Code Button -->
                                            <div class="flex items-center bg-indigo-50 border border-indigo-100 rounded-md p-2 mb-3 cursor-pointer hover:bg-indigo-100 transition-colors group/code" onclick="copyToClipboard('<?php echo $class['class_code']; ?>')">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-xs text-indigo-400 mb-0.5">Mã tham gia</p>
                                                    <p class="text-sm font-mono font-bold text-indigo-700 truncate"><?php echo $class['class_code']; ?></p>
                                                </div>
                                                <i class="fa-regular fa-copy text-indigo-400 group-hover/code:text-indigo-600"></i>
                                            </div>

                                            <a href="view_class.php?id=<?php echo $class['id']; ?>" class="block w-full text-center py-2.5 bg-white border border-gray-300 text-slate-700 rounded-lg font-medium hover:bg-slate-50 hover:text-primary hover:border-primary transition-all">
                                                Quản lý lớp & Tạo đề <i class="fa-solid fa-arrow-right ml-1 text-xs"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- TOAST NOTIFICATION -->
    <div id="toast" class="fixed bottom-5 right-5 bg-slate-800 text-white px-4 py-3 rounded-lg shadow-lg transform translate-y-20 opacity-0 transition-all duration-300 z-50 flex items-center">
        <i class="fa-solid fa-check-circle text-green-400 mr-2"></i>
        <span id="toast-msg">Đã sao chép thành công!</span>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast(`Đã sao chép mã: ${text}`);
            }).catch(() => {
                showToast('Không thể sao chép!', true);
            });
        }

        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            const msg = document.getElementById('toast-msg');
            
            msg.innerText = message;
            toast.classList.remove('translate-y-20', 'opacity-0');
            
            if(window.toastTimeout) clearTimeout(window.toastTimeout);
            
            window.toastTimeout = setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }
    </script>
</body>
</html>