<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: /index.php');
    exit();
}

$msg = '';

// Xử lý lưu cài đặt
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $settings = [
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
            'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
            'site_announcement' => trim($_POST['site_announcement']),
            'max_upload_size' => intval($_POST['max_upload_size'])
        ];

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        
        $msg = "Đã lưu cài đặt thành công!";
    } catch (Exception $e) {
        $msg = "Lỗi: " . $e->getMessage();
    }
}

// Lấy cài đặt hiện tại
// SỬA LỖI: Chỉ chọn đúng 2 cột để dùng FETCH_KEY_PAIR
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$db_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [key => value]

// Default values nếu chưa có trong DB
$current = array_merge([
    'maintenance_mode' => '0',
    'allow_registration' => '1',
    'site_announcement' => '',
    'max_upload_size' => '5'
], $db_settings);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cài đặt Hệ thống - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } 
    /* Toggle Switch CSS */
    .toggle-checkbox:checked { right: 0; border-color: #68D391; }
    .toggle-checkbox:checked + .toggle-label { background-color: #68D391; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 h-screen flex overflow-hidden">

    <aside class="w-64 bg-slate-800 border-r border-slate-700 hidden md:flex flex-col z-10">
        <div class="h-16 flex items-center px-6 border-b border-slate-700">
            <i class="fa-solid fa-shield-cat text-indigo-500 text-2xl mr-2"></i>
            <span class="text-xl font-bold text-white">Admin<span class="text-indigo-500">Panel</span></span>
        </div>
        <nav class="flex-1 py-6 px-3 space-y-1">
            <a href="index.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-gauge-high w-6 text-center mr-2"></i> Dashboard
            </a>
            <a href="manage_users.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-users-gear w-6 text-center mr-2"></i> Quản lý Users
            </a>
             <a href="monitor_cheating.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-triangle-exclamation w-6 text-center mr-2"></i> Giám sát Vi phạm
            </a>
            <a href="test_quality.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-chart-line w-6 text-center mr-2"></i> Phân tích Đề thi
            </a>
            <a href="settings.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg bg-indigo-600 text-white shadow-lg shadow-indigo-500/20">
                <i class="fa-solid fa-sliders w-6 text-center mr-2"></i> Cài đặt Hệ thống
            </a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden relative bg-slate-900">
        <header class="h-16 bg-slate-800 border-b border-slate-700 flex items-center justify-between px-6 shadow-md">
            <h1 class="text-lg font-bold text-white">Cấu hình Hệ thống</h1>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            
            <?php if($msg): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fa-solid fa-check-circle mr-2"></i> <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="max-w-3xl">
                
                <!-- Card: Trạng thái Web -->
                <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-lg mb-6">
                    <h3 class="text-lg font-bold text-white mb-4 border-b border-slate-700 pb-2">Trạng thái & Truy cập</h3>
                    
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <label class="font-bold block text-slate-200">Chế độ Bảo trì</label>
                            <p class="text-xs text-slate-400">Khi bật, chỉ Admin mới có thể truy cập. Học sinh và GV sẽ thấy thông báo bảo trì.</p>
                        </div>
                        <div class="relative inline-block w-12 mr-2 align-middle select-none transition duration-200 ease-in">
                            <input type="checkbox" name="maintenance_mode" id="toggle-maint" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer transition-all duration-300 <?php echo $current['maintenance_mode'] == '1' ? 'right-0 border-emerald-400' : 'left-0 border-slate-400'; ?>" <?php echo $current['maintenance_mode'] == '1' ? 'checked' : ''; ?>>
                            <label for="toggle-maint" class="toggle-label block overflow-hidden h-6 rounded-full bg-slate-700 cursor-pointer <?php echo $current['maintenance_mode'] == '1' ? 'bg-emerald-500' : ''; ?>"></label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label class="font-bold block text-slate-200">Cho phép Đăng ký mới</label>
                            <p class="text-xs text-slate-400">Tắt chức năng này để ngăn người lạ tạo tài khoản mới.</p>
                        </div>
                        <div class="relative inline-block w-12 mr-2 align-middle select-none transition duration-200 ease-in">
                            <input type="checkbox" name="allow_registration" id="toggle-reg" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer transition-all duration-300 <?php echo $current['allow_registration'] == '1' ? 'right-0 border-emerald-400' : 'left-0 border-slate-400'; ?>" <?php echo $current['allow_registration'] == '1' ? 'checked' : ''; ?>>
                            <label for="toggle-reg" class="toggle-label block overflow-hidden h-6 rounded-full bg-slate-700 cursor-pointer <?php echo $current['allow_registration'] == '1' ? 'bg-emerald-500' : ''; ?>"></label>
                        </div>
                    </div>
                </div>

                <!-- Card: Thông báo & Giới hạn -->
                <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-lg mb-6">
                    <h3 class="text-lg font-bold text-white mb-4 border-b border-slate-700 pb-2">Thông báo & Cấu hình</h3>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-slate-300 mb-2">Thông báo toàn hệ thống (Banner)</label>
                        <input type="text" name="site_announcement" value="<?php echo htmlspecialchars($current['site_announcement']); ?>" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="VD: Hệ thống sẽ bảo trì vào 12h đêm nay...">
                        <p class="text-xs text-slate-500 mt-1">Để trống nếu không muốn hiển thị.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-300 mb-2">Giới hạn Upload File (MB)</label>
                        <input type="number" name="max_upload_size" value="<?php echo intval($current['max_upload_size']); ?>" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-indigo-500 outline-none" min="1" max="100">
                    </div>
                </div>

                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-bold shadow-lg shadow-indigo-500/30 transition-all w-full md:w-auto">
                    <i class="fa-solid fa-floppy-disk mr-2"></i> Lưu cấu hình
                </button>
            </form>

        </main>
    </div>
    
    <script>
        // Script đơn giản để tạo hiệu ứng toggle switch bằng JS nếu CSS không bắt kịp state change
        document.querySelectorAll('.toggle-checkbox').forEach(item => {
            item.addEventListener('change', event => {
                const label = event.target.nextElementSibling;
                if (event.target.checked) {
                    event.target.classList.remove('left-0', 'border-slate-400');
                    event.target.classList.add('right-0', 'border-emerald-400');
                    label.classList.add('bg-emerald-500');
                } else {
                    event.target.classList.remove('right-0', 'border-emerald-400');
                    event.target.classList.add('left-0', 'border-slate-400');
                    label.classList.remove('bg-emerald-500');
                }
            })
        })
    </script>
</body>
</html>