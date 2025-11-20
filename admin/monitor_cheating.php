<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: /index.php');
    exit();
}

// Lấy toàn bộ log gian lận kèm thông tin chi tiết
$sql = "
    SELECT cl.*, ta.student_name, t.title as test_title, u.name as teacher_name
    FROM cheating_logs cl
    JOIN test_attempts ta ON cl.attempt_id = ta.id
    JOIN tests t ON ta.test_id = t.id
    JOIN users u ON t.teacher_id = u.id
    ORDER BY cl.timestamp DESC
    LIMIT 100
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giám sát Vi phạm - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
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
            <a href="monitor_cheating.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg bg-indigo-600 text-white shadow-lg shadow-indigo-500/20">
                <i class="fa-solid fa-triangle-exclamation w-6 text-center mr-2"></i> Giám sát Vi phạm
            </a>
            <a href="test_quality.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-chart-line w-6 text-center mr-2"></i> Phân tích Đề thi
            </a>
            <a href="settings.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-cog w-6 text-center mr-2"></i> Cài đặt Hệ thống
            </a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden relative bg-slate-900">
        <header class="h-16 bg-slate-800 border-b border-slate-700 flex items-center justify-between px-6 shadow-md">
            <h1 class="text-lg font-bold text-white">Nhật ký Vi phạm Hệ thống</h1>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="logs-container">
                <?php foreach ($logs as $log): 
                    $border_color = $log['log_type'] == 'tab_switch' ? 'border-orange-500' : 'border-red-500';
                    $icon = $log['log_type'] == 'tab_switch' ? 'fa-window-restore text-orange-500' : 'fa-users-viewfinder text-red-500';
                ?>
                <div class="bg-slate-800 rounded-xl border-l-4 <?php echo $border_color; ?> p-4 shadow-lg hover:bg-slate-750 transition-colors">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid <?php echo $icon; ?> text-lg"></i>
                            <span class="font-bold text-white text-sm"><?php echo htmlspecialchars($log['student_name']); ?></span>
                        </div>
                        <span class="text-xs text-slate-400 font-mono"><?php echo date('H:i d/m', strtotime($log['timestamp'])); ?></span>
                    </div>
                    
                    <p class="text-slate-300 text-sm mb-2 line-clamp-2" title="<?php echo htmlspecialchars($log['details']); ?>">
                        <?php echo htmlspecialchars($log['details']); ?>
                    </p>

                    <?php if ($log['proof_image']): ?>
                    <div class="mt-2 rounded-lg overflow-hidden h-32 border border-slate-600 bg-black relative group cursor-pointer">
                        <img src="<?php echo htmlspecialchars($log['proof_image']); ?>" class="w-full h-full object-cover opacity-70 group-hover:opacity-100 transition-opacity">
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="bg-black/70 text-white text-xs px-2 py-1 rounded">Bằng chứng</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3 pt-3 border-t border-slate-700 flex justify-between items-center text-xs text-slate-500">
                        <span>Bài thi: <?php echo htmlspecialchars($log['test_title']); ?></span>
                        <span>GV: <?php echo htmlspecialchars($log['teacher_name']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if(empty($logs)): ?>
                <div class="text-center text-slate-500 mt-20">
                    <i class="fa-solid fa-check-circle text-4xl text-emerald-500 mb-4"></i>
                    <p>Hệ thống sạch sẽ, chưa ghi nhận vi phạm nào.</p>
                </div>
            <?php endif; ?>

        </main>
    </div>
</body>
</html>