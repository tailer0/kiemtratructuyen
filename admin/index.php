<?php
session_start();
require_once '../config.php';

// Bảo vệ trang Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: /index.php');
    exit();
}

// --- 1. LẤY SỐ LIỆU TỔNG QUAN ---
try {
    $stats = [
        'users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
        'teachers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn(),
        'tests' => $pdo->query("SELECT COUNT(*) FROM tests")->fetchColumn(),
        'cheating_logs' => $pdo->query("SELECT COUNT(*) FROM cheating_logs")->fetchColumn()
    ];

    // --- 2. TÍNH NĂNG THÔNG MINH: TOP 5 BÀI THI CÓ TỶ LỆ GIAN LẬN CAO NHẤT ---
    // Phân tích "xu hướng xấu"
    $stmt = $pdo->prepare("
        SELECT t.title, COUNT(cl.id) as violation_count, u.name as teacher_name
        FROM cheating_logs cl
        JOIN test_attempts ta ON cl.attempt_id = ta.id
        JOIN tests t ON ta.test_id = t.id
        JOIN users u ON t.teacher_id = u.id
        GROUP BY t.id
        ORDER BY violation_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $risky_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. LOG GIAN LẬN MỚI NHẤT ---
    $stmt = $pdo->prepare("
        SELECT cl.*, ta.student_name, t.title as test_title
        FROM cheating_logs cl
        JOIN test_attempts ta ON cl.attempt_id = ta.id
        JOIN tests t ON ta.test_id = t.id
        ORDER BY cl.timestamp DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Lỗi hệ thống: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - OnlineTest vn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-900 text-slate-100 h-screen flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-slate-800 border-r border-slate-700 hidden md:flex flex-col z-10">
        <div class="h-16 flex items-center px-6 border-b border-slate-700">
            <i class="fa-solid fa-shield-cat text-indigo-500 text-2xl mr-2"></i>
            <span class="text-xl font-bold text-white">Admin<span class="text-indigo-500">Panel</span></span>
        </div>
        <nav class="flex-1 py-6 px-3 space-y-1">
            <a href="index.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg bg-indigo-600 text-white shadow-lg shadow-indigo-500/20">
                <i class="fa-solid fa-gauge-high w-6 text-center mr-2"></i> Trang quản lý
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
            <a href="settings.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-cog w-6 text-center mr-2"></i> Cài đặt Hệ thống
            </a>
        </nav>
        <div class="p-4 border-t border-slate-700">
            <a href="/auth/logout.php" class="flex items-center text-slate-400 hover:text-red-400 transition-colors text-sm font-medium">
                <i class="fa-solid fa-right-from-bracket mr-2"></i> Đăng xuất
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative bg-slate-900">
        <!-- Header -->
        <header class="h-16 bg-slate-800 border-b border-slate-700 flex items-center justify-between px-6 shadow-md">
            <h1 class="text-lg font-bold text-white">Tổng quan hệ thống</h1>
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="text-xs text-indigo-400">Super Admin</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center text-white font-bold">A</div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- Card Users -->
                <div class="bg-slate-800 p-5 rounded-2xl border border-slate-700 shadow-lg hover:border-indigo-500/50 transition-all group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Học sinh</p>
                            <h3 class="text-3xl font-bold text-white mt-1 group-hover:text-indigo-400 transition-colors"><?php echo $stats['users']; ?></h3>
                        </div>
                        <div class="p-3 bg-slate-700 rounded-xl text-indigo-400 group-hover:bg-indigo-500 group-hover:text-white transition-all">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                    </div>
                </div>

                <!-- Card Teachers -->
                <div class="bg-slate-800 p-5 rounded-2xl border border-slate-700 shadow-lg hover:border-emerald-500/50 transition-all group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Giáo viên</p>
                            <h3 class="text-3xl font-bold text-white mt-1 group-hover:text-emerald-400 transition-colors"><?php echo $stats['teachers']; ?></h3>
                        </div>
                        <div class="p-3 bg-slate-700 rounded-xl text-emerald-400 group-hover:bg-emerald-500 group-hover:text-white transition-all">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                    </div>
                </div>

                <!-- Card Tests -->
                <div class="bg-slate-800 p-5 rounded-2xl border border-slate-700 shadow-lg hover:border-amber-500/50 transition-all group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Bài kiểm tra</p>
                            <h3 class="text-3xl font-bold text-white mt-1 group-hover:text-amber-400 transition-colors"><?php echo $stats['tests']; ?></h3>
                        </div>
                        <div class="p-3 bg-slate-700 rounded-xl text-amber-400 group-hover:bg-amber-500 group-hover:text-white transition-all">
                            <i class="fa-solid fa-file-pen"></i>
                        </div>
                    </div>
                </div>

                <!-- Card Cheating (Alert) -->
                <div class="bg-slate-800 p-5 rounded-2xl border border-red-500/30 shadow-lg hover:border-red-500 transition-all group relative overflow-hidden">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-red-500/10 rounded-full blur-xl group-hover:bg-red-500/20 transition-all"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-red-400 text-xs uppercase font-bold tracking-wider">Phát hiện gian lận</p>
                            <h3 class="text-3xl font-bold text-white mt-1"><?php echo $stats['cheating_logs']; ?></h3>
                        </div>
                        <div class="p-3 bg-red-500/10 rounded-xl text-red-500 animate-pulse">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Chart Section -->
                <div class="lg:col-span-2 bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-lg">
                    <h3 class="text-lg font-bold text-white mb-4">Thống kê hoạt động</h3>
                    <div class="h-64">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>

                <!-- Risky Tests (Tính năng thông minh) -->
                <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-lg">
                    <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                        <i class="fa-solid fa-fire text-orange-500 mr-2"></i> Bài thi "Báo động"
                    </h3>
                    <div class="space-y-4">
                        <?php if(empty($risky_tests)): ?>
                            <p class="text-slate-500 text-sm text-center italic">Hệ thống trong sạch, chưa có báo động.</p>
                        <?php else: ?>
                            <?php foreach ($risky_tests as $test): ?>
                            <div class="flex items-center justify-between p-3 rounded-lg bg-slate-700/50 border border-slate-700 hover:border-orange-500/50 transition-colors">
                                <div class="flex-1 min-w-0 mr-3">
                                    <p class="text-sm font-bold text-slate-200 truncate"><?php echo htmlspecialchars($test['title']); ?></p>
                                    <p class="text-xs text-slate-500">GV: <?php echo htmlspecialchars($test['teacher_name']); ?></p>
                                </div>
                                <span class="bg-orange-500/10 text-orange-400 text-xs font-bold px-2 py-1 rounded">
                                    <?php echo $test['violation_count']; ?> vi phạm
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Logs Table -->
            <div class="bg-slate-800 rounded-2xl border border-slate-700 shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-white">Nhật ký giám sát thời gian thực (Mới nhất)</h3>
                    <a href="monitor_cheating.php" class="text-xs text-indigo-400 hover:text-indigo-300 font-medium">Xem tất cả <i class="fa-solid fa-arrow-right ml-1"></i></a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-slate-400">
                        <thead class="bg-slate-700/50 text-slate-200 uppercase text-xs font-bold">
                            <tr>
                                <th class="px-6 py-3">Thời gian</th>
                                <th class="px-6 py-3">Sinh viên</th>
                                <th class="px-6 py-3">Bài thi</th>
                                <th class="px-6 py-3">Loại vi phạm</th>
                                <th class="px-6 py-3">Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700">
                            <?php foreach ($recent_logs as $log): 
                                $log_color = $log['log_type'] == 'tab_switch' ? 'text-orange-400' : 'text-red-400';
                            ?>
                            <tr class="hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs"><?php echo date('H:i:s d/m', strtotime($log['timestamp'])); ?></td>
                                <td class="px-6 py-4 font-medium text-white"><?php echo htmlspecialchars($log['student_name']); ?></td>
                                <td class="px-6 py-4 truncate max-w-xs"><?php echo htmlspecialchars($log['test_title']); ?></td>
                                <td class="px-6 py-4 font-bold <?php echo $log_color; ?>"><?php echo htmlspecialchars($log['log_type']); ?></td>
                                <td class="px-6 py-4 truncate max-w-xs text-xs" title="<?php echo htmlspecialchars($log['details']); ?>">
                                    <?php echo htmlspecialchars($log['details']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <script>
        // Chart.js Config for Activity Chart
        const ctx = document.getElementById('activityChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'], // Mock Data
                datasets: [{
                    label: 'Lượt làm bài',
                    data: [12, 19, 3, 5, 2, 30, 45], // Mock data - Thay bằng dữ liệu thật nếu cần
                    borderColor: '#6366f1', // Indigo-500
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Vi phạm',
                    data: [1, 2, 0, 1, 0, 5, 8], // Mock data
                    borderColor: '#ef4444', // Red-500
                    backgroundColor: 'rgba(239, 68, 68, 0)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#94a3b8' } }
                },
                scales: {
                    y: { grid: { color: '#334155' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    </script>
</body>
</html>