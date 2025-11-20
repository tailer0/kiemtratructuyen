<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: /index.php');
    exit();
}

// LOGIC THÔNG MINH: Phân tích các bài thi
// 1. Đề quá khó (TB < 4.0)
// 2. Đề quá dễ (TB > 9.0)
// 3. Tỷ lệ gian lận cao (> 20% số lượt thi có vi phạm)

$sql = "
    SELECT 
        t.id, t.title, u.name as teacher_name,
        COUNT(ta.id) as total_attempts,
        AVG(ta.score) as avg_score,
        (
            SELECT COUNT(DISTINCT cl.attempt_id) 
            FROM cheating_logs cl 
            JOIN test_attempts ta2 ON cl.attempt_id = ta2.id 
            WHERE ta2.test_id = t.id
        ) as cheating_count
    FROM tests t
    JOIN test_attempts ta ON t.id = ta.test_id
    JOIN users u ON t.teacher_id = u.id
    GROUP BY t.id
    HAVING total_attempts > 0 -- Chỉ xét bài đã có người làm
    ORDER BY avg_score ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hard_tests = array_filter($tests, fn($t) => $t['avg_score'] < 4.0);
$easy_tests = array_filter($tests, fn($t) => $t['avg_score'] > 9.0);
$cheating_tests = array_filter($tests, function($t) {
    return $t['total_attempts'] > 0 && ($t['cheating_count'] / $t['total_attempts'] * 100) > 20;
});
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Phân tích Chất lượng Đề thi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-900 text-slate-100 h-screen flex overflow-hidden">

    <!-- SIDEBAR (Đồng bộ) -->
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
            <a href="test_quality.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg bg-indigo-600 text-white shadow-lg shadow-indigo-500/20">
                <i class="fa-solid fa-chart-line w-6 text-center mr-2"></i> Phân tích Đề thi
            </a>
            <a href="settings.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-sliders w-6 text-center mr-2"></i> Cài đặt Hệ thống
            </a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden relative bg-slate-900">
        <header class="h-16 bg-slate-800 border-b border-slate-700 flex items-center justify-between px-6 shadow-md">
            <h1 class="text-lg font-bold text-white">Phân tích Chất lượng & Xu hướng</h1>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            
            <!-- Cảnh báo Đề Quá Khó -->
            <div class="mb-8">
                <h2 class="text-lg font-bold text-red-400 mb-4 flex items-center">
                    <i class="fa-solid fa-skull mr-2"></i> Cảnh báo: Đề thi quá khó (Điểm TB < 4.0)
                </h2>
                <?php if(empty($hard_tests)): ?>
                    <div class="p-4 bg-slate-800 rounded-lg border border-slate-700 text-slate-400 text-sm italic">Không có bài thi nào ở mức báo động đỏ.</div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach($hard_tests as $t): ?>
                            <div class="bg-slate-800 p-4 rounded-xl border-l-4 border-red-500 shadow-lg">
                                <h3 class="font-bold text-white truncate"><?php echo htmlspecialchars($t['title']); ?></h3>
                                <p class="text-xs text-slate-400 mt-1">GV: <?php echo htmlspecialchars($t['teacher_name']); ?></p>
                                <div class="mt-3 flex justify-between items-center">
                                    <span class="text-sm font-mono text-red-400">TB: <?php echo number_format($t['avg_score'], 2); ?></span>
                                    <span class="text-xs bg-slate-700 px-2 py-1 rounded"><?php echo $t['total_attempts']; ?> lượt thi</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cảnh báo Gian lận cao -->
            <div class="mb-8">
                <h2 class="text-lg font-bold text-orange-400 mb-4 flex items-center">
                    <i class="fa-solid fa-mask mr-2"></i> Cảnh báo: Tỷ lệ gian lận cao (> 20%)
                </h2>
                <?php if(empty($cheating_tests)): ?>
                    <div class="p-4 bg-slate-800 rounded-lg border border-slate-700 text-slate-400 text-sm italic">Các bài thi đều có tính bảo mật tốt.</div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach($cheating_tests as $t): 
                            $cheat_rate = ($t['cheating_count'] / $t['total_attempts']) * 100;
                        ?>
                            <div class="bg-slate-800 p-4 rounded-xl border-l-4 border-orange-500 shadow-lg">
                                <h3 class="font-bold text-white truncate"><?php echo htmlspecialchars($t['title']); ?></h3>
                                <div class="w-full bg-slate-700 rounded-full h-2 mt-3">
                                    <div class="bg-orange-500 h-2 rounded-full" style="width: <?php echo $cheat_rate; ?>%"></div>
                                </div>
                                <p class="text-xs text-orange-400 mt-1 text-right"><?php echo number_format($cheat_rate, 1); ?>% lượt thi vi phạm</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Đề dễ (Có thể lộ đề) -->
            <div class="mb-8">
                <h2 class="text-lg font-bold text-emerald-400 mb-4 flex items-center">
                    <i class="fa-solid fa-feather mr-2"></i> Lưu ý: Đề thi quá dễ (Điểm TB > 9.0)
                </h2>
                <?php if(empty($easy_tests)): ?>
                    <div class="p-4 bg-slate-800 rounded-lg border border-slate-700 text-slate-400 text-sm italic">Phân phối điểm hợp lý.</div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach($easy_tests as $t): ?>
                            <div class="bg-slate-800 p-4 rounded-xl border-l-4 border-emerald-500 shadow-lg">
                                <h3 class="font-bold text-white truncate"><?php echo htmlspecialchars($t['title']); ?></h3>
                                <p class="text-xs text-slate-400 mt-1">GV: <?php echo htmlspecialchars($t['teacher_name']); ?></p>
                                <div class="mt-3 flex justify-between items-center">
                                    <span class="text-sm font-mono text-emerald-400">TB: <?php echo number_format($t['avg_score'], 2); ?></span>
                                    <span class="text-xs bg-slate-700 px-2 py-1 rounded"><?php echo $t['total_attempts']; ?> lượt thi</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</body>
</html>