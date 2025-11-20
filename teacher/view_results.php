<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

$test_id = $_GET['test_id'] ?? 0;
$teacher_id = $_SESSION['user_id'];

// Lấy thông tin bài test
$stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? AND teacher_id = ?");
$stmt->execute([$test_id, $teacher_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) die("Không tìm thấy bài kiểm tra.");

// Lấy danh sách kết quả và đếm số vi phạm
$stmt = $pdo->prepare("
    SELECT ta.*, 
    (SELECT COUNT(*) FROM cheating_logs cl WHERE cl.attempt_id = ta.id) as violation_count,
    (SELECT COUNT(*) FROM cheating_logs cl WHERE cl.attempt_id = ta.id AND cl.timestamp > DATE_SUB(NOW(), INTERVAL 2 MINUTE)) as recent_violations
    FROM test_attempts ta 
    WHERE ta.test_id = ? 
    ORDER BY violation_count DESC, ta.student_name ASC
");
$stmt->execute([$test_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy logs để hiển thị realtime
$stmt = $pdo->prepare("SELECT MAX(id) FROM cheating_logs");
$stmt->execute();
$max_log_id = $stmt->fetchColumn() ?: 0;

// Lấy trước logs cho gallery
$stmt = $pdo->prepare("
    SELECT cl.* FROM cheating_logs cl 
    JOIN test_attempts ta ON cl.attempt_id = ta.id 
    WHERE ta.test_id = ? 
    ORDER BY cl.timestamp DESC
");
$stmt->execute([$test_id]);
$all_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logs_by_student = [];
foreach ($all_logs as $log) {
    $logs_by_student[$log['attempt_id']][] = $log;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Monitor: <?php echo htmlspecialchars($test['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .animate-pulse-red { animation: pulse-red 2s infinite; }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            50% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        
        .animate-bounce-slow { animation: bounce-slow 3s infinite; }
        @keyframes bounce-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .filter-btn.active { @apply bg-indigo-600 text-white shadow-lg; }
        
        .quick-action-btn {
            @apply transition-all duration-200 transform hover:scale-105 active:scale-95;
        }
        
        .student-card {
            transition: all 0.3s ease;
        }
        
        .student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .heatmap-cell {
            transition: all 0.2s ease;
        }
        
        .heatmap-cell:hover {
            transform: scale(1.1);
            z-index: 10;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .slide-in-right {
            animation: slideInRight 0.3s ease-out;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 text-slate-800 h-screen flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col z-10 shadow-lg">
        <div class="h-16 flex items-center px-6 border-b border-gray-100 bg-gradient-to-r from-indigo-600 to-purple-600">
            <i class="fa-solid fa-shield-halved text-white text-2xl mr-2"></i>
            <span class="text-xl font-bold text-white">Smart Monitor</span>
        </div>
        <nav class="flex-1 py-6 px-3 space-y-1 overflow-y-auto">
            <a href="index.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition-all">
                <i class="fa-solid fa-chart-line w-6 text-center mr-2 text-lg"></i> Dashboard
            </a>
            <a href="view_class.php?id=<?php echo $test['class_id']; ?>" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg bg-indigo-50 text-indigo-600">
                <i class="fa-solid fa-users w-6 text-center mr-2 text-lg"></i> Quay lại Lớp
            </a>
            <div class="pt-4 pb-2 px-3">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Quick Tools</p>
            </div>
            <button onclick="toggleBulkActions()" class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-600 hover:bg-amber-50 hover:text-amber-600 transition-all">
                <i class="fa-solid fa-bolt w-6 text-center mr-2 text-lg"></i> Hành động hàng loạt
            </button>
            <button onclick="exportViolationReport()" class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-600 hover:bg-green-50 hover:text-green-600 transition-all">
                <i class="fa-solid fa-file-export w-6 text-center mr-2 text-lg"></i> Xuất báo cáo
            </button>
        </nav>
        <div class="p-4 border-t border-gray-200 bg-slate-50">
            <div class="flex items-center">
                <div class="h-9 w-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold shadow-md">GV</div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Giáo viên'); ?></p>
                    <p class="text-xs text-slate-500">Đang giám sát</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 shadow-md z-20">
            <div class="flex items-center">
                <h1 class="text-xl font-bold text-slate-800 truncate max-w-md flex items-center">
                    <span class="bg-gradient-to-br from-indigo-500 to-purple-600 text-white p-2 rounded-lg mr-3 shadow-md">
                        <i class="fa-solid fa-eye"></i>
                    </span>
                    <?php echo htmlspecialchars($test['title']); ?>
                </h1>
                <span class="ml-4 px-3 py-1 rounded-full text-xs font-bold bg-gradient-to-r from-green-400 to-emerald-500 text-white shadow-lg flex items-center animate-pulse">
                    <i class="fa-solid fa-circle text-[8px] mr-1.5"></i> LIVE MONITORING
                </span>
            </div>
            <div class="flex gap-2">
                <button onclick="toggleViewMode()" class="hidden sm:inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                    <i class="fa-solid fa-table-cells mr-2 text-indigo-600"></i> <span id="view-mode-text">Chế độ lưới</span>
                </button>
                <a href="export_excel.php?test_id=<?php echo $test_id; ?>" class="hidden sm:inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all">
                    <i class="fa-solid fa-file-excel mr-2 text-green-600"></i> Xuất Excel
                </a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 bg-gradient-to-br from-slate-50 to-slate-100">
            
            <!-- Enhanced Stats Cards with Real-time Analytics -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-4 rounded-xl shadow-lg text-white transform hover:scale-105 transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase opacity-90">Tổng thí sinh</p>
                            <p class="text-3xl font-extrabold mt-1"><?php echo count($results); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                            <i class="fa-solid fa-users text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-green-500 to-emerald-600 p-4 rounded-xl shadow-lg text-white transform hover:scale-105 transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase opacity-90">Đang làm bài</p>
                            <p class="text-3xl font-extrabold mt-1" id="active-count">
                                <?php echo count(array_filter($results, fn($r) => empty($r['end_time']))); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm animate-pulse">
                            <i class="fa-solid fa-pen-to-square text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-red-500 to-red-600 p-4 rounded-xl shadow-lg text-white transform hover:scale-105 transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase opacity-90">Vi phạm nghiêm trọng</p>
                            <p class="text-3xl font-extrabold mt-1" id="critical-violations">
                                <?php echo count(array_filter($results, fn($r) => $r['violation_count'] > 3)); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                            <i class="fa-solid fa-triangle-exclamation text-2xl animate-bounce"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-amber-500 to-orange-600 p-4 rounded-xl shadow-lg text-white transform hover:scale-105 transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase opacity-90">Cần giám sát</p>
                            <p class="text-3xl font-extrabold mt-1" id="warning-count">
                                <?php echo count(array_filter($results, fn($r) => $r['violation_count'] > 0 && $r['violation_count'] <= 3)); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                            <i class="fa-solid fa-eye text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-br from-slate-700 to-slate-800 p-4 rounded-xl shadow-lg text-white transform hover:scale-105 transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase opacity-90">Tổng vi phạm</p>
                            <p class="text-3xl font-extrabold mt-1" id="total-violations">
                                <?php echo array_sum(array_column($results, 'violation_count')); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                            <i class="fa-solid fa-chart-line text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Smart Filter & Search Bar -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 mb-6">
                <div class="flex flex-col md:flex-row gap-4 items-center">
                    <div class="flex-1 relative">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="search-input" placeholder="Tìm kiếm theo tên, MSSV..." class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm" onkeyup="filterStudents()">
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <button class="filter-btn active px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 hover:bg-slate-100 transition-all" data-filter="all" onclick="setFilter('all')">
                            <i class="fa-solid fa-list mr-1"></i> Tất cả
                        </button>
                        <button class="filter-btn px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 hover:bg-slate-100 transition-all" data-filter="active" onclick="setFilter('active')">
                            <i class="fa-solid fa-pen text-green-600 mr-1"></i> Đang thi
                        </button>
                        <button class="filter-btn px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 hover:bg-slate-100 transition-all" data-filter="critical" onclick="setFilter('critical')">
                            <i class="fa-solid fa-exclamation-triangle text-red-600 mr-1"></i> Nghiêm trọng
                        </button>
                        <button class="filter-btn px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 hover:bg-slate-100 transition-all" data-filter="warning" onclick="setFilter('warning')">
                            <i class="fa-solid fa-exclamation-circle text-amber-600 mr-1"></i> Cảnh báo
                        </button>
                        <button class="filter-btn px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 hover:bg-slate-100 transition-all" data-filter="clean" onclick="setFilter('clean')">
                            <i class="fa-solid fa-check text-emerald-600 mr-1"></i> Sạch
                        </button>
                    </div>
                </div>
            </div>

            <!-- Student Table/Grid View -->
            <div id="table-view" class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
                    <h3 class="font-bold text-slate-700 flex items-center">
                        <i class="fa-solid fa-users-viewfinder mr-2 text-indigo-600"></i>
                        Giám sát thí sinh <span class="ml-2 text-sm font-normal text-slate-500">(<span id="filtered-count"><?php echo count($results); ?></span> thí sinh)</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-slate-600 text-xs uppercase font-semibold tracking-wider">
                            <tr>
                                <th class="px-6 py-3 text-left w-8">
                                    <input type="checkbox" id="select-all" onclick="toggleSelectAll()" class="rounded">
                                </th>
                                <th class="px-6 py-3 text-left">Sinh viên</th>
                                <th class="px-6 py-3 text-left">Điểm số</th>
                                <th class="px-6 py-3 text-left">Vi phạm</th>
                                <th class="px-6 py-3 text-left">Hoạt động gần đây</th>
                                <th class="px-6 py-3 text-left">Trạng thái</th>
                                <th class="px-6 py-3 text-right">Hành động nhanh</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100" id="results-body">
                            <?php foreach ($results as $row): 
                                $row_class = "hover:bg-slate-50 transition-all duration-300 student-row";
                                $border_class = "";
                                $status_class = "clean";
                                
                                if (!empty($row['end_time'])) {
                                    if ($row['violation_count'] > 3) {
                                        $border_class = "border-l-4 border-l-red-500";
                                        $status_class = "critical";
                                    } else if ($row['violation_count'] > 0) {
                                        $border_class = "border-l-4 border-l-amber-500";
                                        $status_class = "warning";
                                    }
                                } else {
                                    $border_class = "border-l-4 border-l-green-400";
                                    $status_class = "active";
                                    if ($row['violation_count'] > 3) $status_class .= " critical";
                                    else if ($row['violation_count'] > 0) $status_class .= " warning";
                                }
                                
                                $row_class .= " " . $border_class;
                                
                                $violation_badge = 'bg-slate-100 text-slate-600 border-slate-200';
                                if ($row['violation_count'] > 3) $violation_badge = 'bg-red-100 text-red-700 border-red-300 animate-pulse';
                                else if ($row['violation_count'] > 0) $violation_badge = 'bg-amber-100 text-amber-700 border-amber-300';
                            ?>
                            <tr id="row-<?php echo $row['id']; ?>" class="<?php echo $row_class; ?>" data-status="<?php echo $status_class; ?>" data-student-name="<?php echo strtolower($row['student_name']); ?>" data-student-id="<?php echo strtolower($row['student_id']); ?>">
                                <td class="px-6 py-4">
                                    <input type="checkbox" class="student-checkbox rounded" data-attempt-id="<?php echo $row['id']; ?>">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm mr-3 shadow-md">
                                            <?php echo mb_substr($row['student_name'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                            <div class="text-xs text-slate-500 font-mono"><?php echo htmlspecialchars($row['student_id']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($row['score'] !== null): ?>
                                        <span class="text-sm font-bold text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-200 shadow-sm"><?php echo number_format($row['score'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">Chưa có</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span id="badge-violation-<?php echo $row['id']; ?>" class="px-3 py-1.5 inline-flex text-xs font-bold rounded-full border <?php echo $violation_badge; ?> shadow-sm">
                                            <i class="fa-solid fa-exclamation-triangle mr-1"></i> <?php echo $row['violation_count']; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($row['recent_violations'] > 0): ?>
                                        <span class="text-xs bg-red-50 text-red-600 px-2 py-1 rounded-full border border-red-200 animate-pulse">
                                            <i class="fa-solid fa-clock mr-1"></i> <?php echo $row['recent_violations']; ?> vi phạm gần đây
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">Không có hoạt động</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if($row['end_time']): ?>
                                        <span class="inline-flex items-center text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg text-xs font-medium shadow-sm">
                                            <i class="fa-solid fa-flag-checkered mr-1.5"></i> Hoàn thành
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center text-green-600 bg-green-50 px-3 py-1.5 rounded-lg text-xs font-medium border border-green-200 shadow-sm">
                                            <i class="fa-solid fa-circle mr-1.5 text-[8px] animate-pulse"></i> Đang làm bài
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <?php $student_logs_json = isset($logs_by_student[$row['id']]) ? json_encode($logs_by_student[$row['id']]) : '[]'; ?>
                                        <button onclick='openProofGallery(<?php echo $student_logs_json; ?>, "<?php echo htmlspecialchars($row['student_name']); ?>")' class="quick-action-btn text-white bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 px-3 py-1.5 rounded-lg shadow-md transition-all" title="Xem bằng chứng">
                                            <i class="fa-solid fa-images"></i>
                                        </button>
                                        <button onclick="openQuickChat(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_name']); ?>')" class="quick-action-btn text-white bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 px-3 py-1.5 rounded-lg shadow-md transition-all" title="Nhắn tin nhanh">
                                            <i class="fa-solid fa-bolt"></i>
                                        </button>
                                        <?php if(!$row['end_time']): ?>
                                        <button onclick="stopExam(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_name']); ?>')" class="quick-action-btn text-white bg-gradient-to-r from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 px-3 py-1.5 rounded-lg shadow-md transition-all" title="Đình chỉ">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Grid View (Hidden by default) -->
            <div id="grid-view" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <?php foreach ($results as $row): 
                    $violation_color = $row['violation_count'] > 3 ? 'from-red-500 to-red-600' : ($row['violation_count'] > 0 ? 'from-amber-500 to-orange-600' : 'from-emerald-500 to-green-600');
                ?>
                <div class="student-card bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden">
                    <div class="bg-gradient-to-r <?php echo $violation_color; ?> h-2"></div>
                    <div class="p-4">
                        <div class="flex items-center mb-3">
                            <div class="h-12 w-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg shadow-md">
                                <?php echo mb_substr($row['student_name'], 0, 1); ?>
                            </div>
                            <div class="ml-3 flex-1 min-w-0">
                                <div class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                <div class="text-xs text-slate-500 font-mono"><?php echo htmlspecialchars($row['student_id']); ?></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="bg-slate-50 rounded-lg p-2 text-center">
                                <div class="text-xs text-slate-500 font-medium">Điểm</div>
                                <div class="text-lg font-bold text-indigo-600">
                                    <?php echo $row['score'] !== null ? number_format($row['score'], 1) : '--'; ?>
                                </div>
                            </div>
                            <div class="bg-slate-50 rounded-lg p-2 text-center">
                                <div class="text-xs text-slate-500 font-medium">Vi phạm</div>
                                <div class="text-lg font-bold <?php echo $row['violation_count'] > 3 ? 'text-red-600' : ($row['violation_count'] > 0 ? 'text-amber-600' : 'text-emerald-600'); ?>">
                                    <?php echo $row['violation_count']; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2 justify-between">
                            <?php $student_logs_json = isset($logs_by_student[$row['id']]) ? json_encode($logs_by_student[$row['id']]) : '[]'; ?>
                            <button onclick='openProofGallery(<?php echo $student_logs_json; ?>, "<?php echo htmlspecialchars($row['student_name']); ?>")' class="flex-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 px-2 py-1.5 rounded-lg text-xs font-medium transition-all">
                                <i class="fa-solid fa-images"></i>
                            </button>
                            <button onclick="openQuickChat(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_name']); ?>')" class="flex-1 bg-blue-50 hover:bg-blue-100 text-blue-600 px-2 py-1.5 rounded-lg text-xs font-medium transition-all">
                                <i class="fa-solid fa-bolt"></i>
                            </button>
                            <?php if(!$row['end_time']): ?>
                            <button onclick="stopExam(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_name']); ?>')" class="flex-1 bg-red-50 hover:bg-red-100 text-red-600 px-2 py-1.5 rounded-lg text-xs font-medium transition-all">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </main>
    </div>

    <!-- NOTIFICATION CONTAINER -->
    <div id="notification-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 w-96 pointer-events-none"></div>

    <!-- QUICK CHAT MODAL WITH TEMPLATES -->
    <div id="quick-chat-modal" class="fixed inset-0 bg-slate-900/60 z-50 hidden flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden flex flex-col max-h-[85vh]">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-blue-500 to-cyan-600 flex justify-between items-center">
                <h3 class="font-bold text-white text-lg flex items-center">
                    <i class="fa-solid fa-bolt mr-2"></i>
                    Quick Chat: <span id="quick-chat-student-name" class="ml-2"></span>
                </h3>
                <button onclick="closeQuickChat()" class="text-white/80 hover:text-white transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            
            <!-- Message Templates -->
            <div class="px-6 py-4 bg-slate-50 border-b">
                <p class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-3">Tin nhắn mẫu</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <button onclick="useTemplate('⚠️ CẢNH BÁO: Vui lòng tập trung vào bài thi!')" class="text-left bg-white hover:bg-amber-50 border border-amber-200 hover:border-amber-400 rounded-lg px-4 py-3 text-sm transition-all shadow-sm group">
                        <div class="font-bold text-amber-700 group-hover:text-amber-800 flex items-center">
                            <i class="fa-solid fa-triangle-exclamation mr-2"></i> Cảnh báo chung
                        </div>
                        <div class="text-xs text-slate-600 mt-1">Nhắc nhở tập trung vào bài thi</div>
                    </button>
                    
                    <button onclick="useTemplate('🚨 Phát hiện chuyển tab! Vui lòng quay lại màn hình thi ngay.')" class="text-left bg-white hover:bg-red-50 border border-red-200 hover:border-red-400 rounded-lg px-4 py-3 text-sm transition-all shadow-sm group">
                        <div class="font-bold text-red-700 group-hover:text-red-800 flex items-center">
                            <i class="fa-solid fa-window-restore mr-2"></i> Vi phạm chuyển tab
                        </div>
                        <div class="text-xs text-slate-600 mt-1">Yêu cầu quay lại màn hình thi</div>
                    </button>
                    
                    <button onclick="useTemplate('📱 CẢNH BÁO: Phát hiện sử dụng điện thoại. Vui lòng cất ngay!')" class="text-left bg-white hover:bg-red-50 border border-red-200 hover:border-red-400 rounded-lg px-4 py-3 text-sm transition-all shadow-sm group">
                        <div class="font-bold text-red-700 group-hover:text-red-800 flex items-center">
                            <i class="fa-solid fa-mobile-screen mr-2"></i> Phát hiện điện thoại
                        </div>
                        <div class="text-xs text-slate-600 mt-1">Yêu cầu cất thiết bị ngay lập tức</div>
                    </button>
                    
                    <button onclick="useTemplate('👁️ Camera không rõ! Vui lòng điều chỉnh để thấy rõ khuôn mặt.')" class="text-left bg-white hover:bg-orange-50 border border-orange-200 hover:border-orange-400 rounded-lg px-4 py-3 text-sm transition-all shadow-sm group">
                        <div class="font-bold text-orange-700 group-hover:text-orange-800 flex items-center">
                            <i class="fa-solid fa-camera mr-2"></i> Vấn đề camera
                        </div>
                        <div class="text-xs text-slate-600 mt-1">Yêu cầu điều chỉnh góc camera</div>
                    </button>
                    
                    <button onclick="useTemplate('👤 Phát hiện có người khác trong khung hình. Làm bài một mình!')" class="text-left bg-white hover:bg-red-50 border border-red-200 hover:border-red-400 rounded-lg px-4 py-3 text-sm transition-all shadow-sm group">
                        <div class="font-bold text-red-700 group-hover:text-red-800 flex items-center">
                            <i class="fa-solid fa-user-group mr-2"></i> Nhiều người
                        </div>
                        <div class="text-xs text-slate-600 mt-1">Phát hiện người khác trong khung hình</div>
                    </button>
                    
                    <button onclick="useTemplate('⏱️ Bạn còn ít thời gian. Hãy tập trung hoàn thành bài thi!')" class="text-left bg-white hover:bg-blue-50 border border-blue-200 hover:border-blue-400 rounded-lg px-4 py-3 text-sm transition-all shadow-sm group">
                        <div class="font-bold text-blue-700 group-hover:text-blue-800 flex items-center">
                            <i class="fa-solid fa-clock mr-2"></i> Nhắc thời gian
                        </div>
                        <div class="text-xs text-slate-600 mt-1">Nhắc nhở về thời gian còn lại</div>
                    </button>
                    
                    <button onclick="useTemplate('⛔ VI PHẠM NGHIÊM TRỌNG! Nếu tiếp tục, bài thi sẽ bị đình chỉ.')" class="text-left bg-white hover:bg-red-50 border border-red-300 hover:border-red-500 rounded-lg px-4 py-3 text-sm transition-all shadow-sm group">
                        <div class="font-bold text-red-800 group-hover:text-red-900 flex items-center">
                            <i class="fa-solid fa-ban mr-2"></i> Cảnh báo nghiêm khắc
                        </div>
                        <div class="text-xs text-slate-600 mt-1">Cảnh báo có thể đình chỉ</div>
                    </button>
                    
                    <button onclick="useTemplate('✅ Tốt lắm! Hãy tiếp tục làm bài nghiêm túc như vậy.')" class="text-left bg-white hover:bg-green-50 border border-green-200 hover:border-green-400 rounded-lg px-4 py-3 text-sm transition-all shadow-sm group">
                        <div class="font-bold text-green-700 group-hover:text-green-800 flex items-center">
                            <i class="fa-solid fa-thumbs-up mr-2"></i> Khen ngợi
                        </div>
                        <div class="text-xs text-slate-600 mt-1">Động viên thí sinh làm tốt</div>
                    </button>
                </div>
            </div>
            
            <div id="quick-chat-messages" class="flex-1 overflow-y-auto p-6 space-y-3 bg-white min-h-[250px]"></div>
            
            <div class="p-4 border-t bg-slate-50">
                <form id="quick-chat-form" onsubmit="sendQuickMessage(event)" class="flex gap-2">
                    <input type="hidden" id="quick-chat-attempt-id">
                    <input type="text" id="quick-chat-input" class="flex-1 border border-slate-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm" placeholder="Nhập tin nhắn hoặc chọn mẫu..." required autocomplete="off">
                    <button type="submit" class="bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 text-white px-6 py-3 rounded-lg shadow-lg transition-all transform hover:scale-105">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- GALLERY MODAL -->
    <div id="gallery-modal" class="fixed inset-0 bg-slate-900/90 z-[60] hidden flex items-center justify-center backdrop-blur-md">
        <button onclick="closeGallery()" class="absolute top-6 right-6 text-white/70 hover:text-white text-3xl transition-colors z-10">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl mx-4 overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-indigo-500 to-purple-600 flex justify-between items-center">
                <h3 class="font-bold text-white text-lg flex items-center">
                    <i class="fa-solid fa-shield-halved mr-2"></i>
                    Bằng chứng vi phạm: <span id="gallery-student-name" class="ml-2"></span>
                </h3>
            </div>
            <div id="gallery-content" class="flex-1 overflow-y-auto p-6 bg-slate-100">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="gallery-grid"></div>
                <p id="gallery-empty" class="text-center text-slate-500 mt-10 hidden">
                    <i class="fa-solid fa-check-circle text-4xl text-emerald-500 mb-3"></i>
                    <br>Thí sinh này chưa có vi phạm nào.
                </p>
            </div>
        </div>
    </div>

    <!-- BULK ACTIONS PANEL -->
    <div id="bulk-panel" class="fixed bottom-0 left-0 right-0 bg-white border-t-2 border-indigo-500 shadow-2xl z-40 transform translate-y-full transition-transform duration-300">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="font-bold text-slate-700">
                        <span id="selected-count">0</span> thí sinh đã chọn
                    </span>
                    <button onclick="clearSelection()" class="text-sm text-slate-500 hover:text-slate-700">
                        <i class="fa-solid fa-xmark mr-1"></i> Bỏ chọn
                    </button>
                </div>
                <div class="flex gap-3">
                    <button onclick="bulkSendMessage()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-all shadow-md">
                        <i class="fa-solid fa-comment-dots mr-2"></i> Gửi tin nhắn hàng loạt
                    </button>
                    <button onclick="bulkWarning()" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-medium transition-all shadow-md">
                        <i class="fa-solid fa-triangle-exclamation mr-2"></i> Cảnh báo chung
                    </button>
                    <button onclick="bulkStop()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-all shadow-md">
                        <i class="fa-solid fa-ban mr-2"></i> Đình chỉ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let lastLogId = <?php echo $max_log_id; ?>;
        let currentChatAttemptId = null;
        const testId = <?php echo $test_id; ?>;
        const currentTeacherName = "<?php echo addslashes($_SESSION['user_name'] ?? 'GV'); ?>";
        let currentFilter = 'all';
        let viewMode = 'table'; // 'table' or 'grid'

        // Polling for updates
        function pollUpdates() {
            fetch(`api_monitor.php?action=fetch_updates&test_id=${testId}&last_log_id=${lastLogId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.new_logs.length > 0) {
                        data.new_logs.forEach(log => { 
                            showNotification(log); 
                            updateRowStatus(log); 
                        });
                        lastLogId = data.last_log_id;
                        updateStats();
                    }
                })
                .catch(err => console.error('Polling error:', err));
        }
        setInterval(pollUpdates, 3000);

        function showNotification(log) {
            const container = document.getElementById('notification-container');
            const notif = document.createElement('div');
            notif.className = 'pointer-events-auto bg-white border-l-4 border-red-500 shadow-2xl rounded-r-xl p-4 flex items-start transform transition-all duration-500 translate-x-full mb-3 slide-in-right';
            
            let imgHtml = log.proof_image ? `<div class="mt-2 rounded-lg border-2 border-slate-200 h-24 w-full bg-black flex items-center justify-center overflow-hidden shadow-md"><img src="${log.proof_image}" class="object-cover w-full h-full cursor-pointer hover:scale-110 transition-transform" onclick="viewFullImage('${log.proof_image}')"></div>` : '';
            
            const icon = log.log_type === 'tab_switch' ? 
                '<i class="fa-solid fa-window-restore text-orange-500 mt-1 text-xl"></i>' : 
                '<i class="fa-solid fa-triangle-exclamation text-red-600 mt-1 animate-bounce text-xl"></i>';
            
            notif.innerHTML = `
                <div class="mr-3 text-lg">${icon}</div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-sm truncate text-slate-800">${log.student_name}</h4>
                    <p class="text-xs text-red-600 font-medium mt-0.5">${log.details || 'Vi phạm'}</p>
                    <p class="text-[10px] text-slate-400 mt-1">${new Date(log.timestamp).toLocaleTimeString('vi-VN')}</p>
                    ${imgHtml}
                </div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 ml-2 text-lg">&times;</button>
            `;
            
            container.appendChild(notif);
            setTimeout(() => notif.classList.remove('translate-x-full'), 100);
            setTimeout(() => { 
                notif.classList.add('translate-x-full'); 
                setTimeout(() => notif.remove(), 500); 
            }, 10000);
        }

        function updateRowStatus(log) {
            const row = document.getElementById(`row-${log.attempt_id}`);
            if (row) {
                row.classList.add('animate-pulse-red', 'bg-red-50');
                setTimeout(() => {
                    row.classList.remove('animate-pulse-red');
                }, 5000);
                
                const badge = document.getElementById(`badge-violation-${log.attempt_id}`);
                if(badge) {
                    badge.className = "px-3 py-1.5 inline-flex text-xs font-bold rounded-full border bg-red-100 text-red-700 border-red-300 shadow-sm animate-pulse";
                    badge.innerHTML = `<i class="fa-solid fa-exclamation-triangle mr-1"></i> Vi phạm mới!`;
                }
            }
        }

        function updateStats() {
            // Update statistics in real-time
            fetch(`api_monitor.php?action=get_stats&test_id=${testId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('total-violations').textContent = data.total_violations;
                        document.getElementById('active-count').textContent = data.active_students;
                        document.getElementById('critical-violations').textContent = data.critical_violations;
                        document.getElementById('warning-count').textContent = data.warning_students;
                    }
                });
        }

        // Filter functions
        function setFilter(filter) {
            currentFilter = filter;
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
            filterStudents();
        }

        function filterStudents() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            const rows = document.querySelectorAll('.student-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const status = row.dataset.status;
                const name = row.dataset.studentName;
                const id = row.dataset.studentId;
                
                let matchesFilter = false;
                if (currentFilter === 'all') matchesFilter = true;
                else if (currentFilter === 'active') matchesFilter = status.includes('active');
                else if (currentFilter === 'critical') matchesFilter = status.includes('critical');
                else if (currentFilter === 'warning') matchesFilter = status.includes('warning') && !status.includes('critical');
                else if (currentFilter === 'clean') matchesFilter = status === 'clean' || (status.includes('active') && !status.includes('critical') && !status.includes('warning'));
                
                const matchesSearch = name.includes(searchTerm) || id.includes(searchTerm);
                
                if (matchesFilter && matchesSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            document.getElementById('filtered-count').textContent = visibleCount;
        }

        // View mode toggle
        function toggleViewMode() {
            const tableView = document.getElementById('table-view');
            const gridView = document.getElementById('grid-view');
            const viewModeText = document.getElementById('view-mode-text');
            
            if (viewMode === 'table') {
                viewMode = 'grid';
                tableView.classList.add('hidden');
                gridView.classList.remove('hidden');
                viewModeText.textContent = 'Chế độ bảng';
            } else {
                viewMode = 'table';
                tableView.classList.remove('hidden');
                gridView.classList.add('hidden');
                viewModeText.textContent = 'Chế độ lưới';
            }
        }

        // Quick Chat functions
        function openQuickChat(id, name) {
            currentChatAttemptId = id;
            document.getElementById('quick-chat-attempt-id').value = id;
            document.getElementById('quick-chat-student-name').textContent = name;
            document.getElementById('quick-chat-modal').classList.remove('hidden');
            loadQuickChatHistory(id);
        }

        function closeQuickChat() {
            document.getElementById('quick-chat-modal').classList.add('hidden');
            currentChatAttemptId = null;
        }

        function useTemplate(message) {
            document.getElementById('quick-chat-input').value = message;
            document.getElementById('quick-chat-input').focus();
        }

        function loadQuickChatHistory(id) {
            const chatBox = document.getElementById('quick-chat-messages');
            chatBox.innerHTML = '<div class="flex justify-center py-4"><i class="fa-solid fa-circle-notch fa-spin text-blue-500 text-2xl"></i></div>';
            
            fetch(`api_monitor.php?action=get_chat&attempt_id=${id}`)
                .then(r => r.json())
                .then(d => {
                    chatBox.innerHTML = '';
                    if (d.messages && d.messages.length > 0) {
                        d.messages.forEach(m => appendQuickMessage(m));
                    } else {
                        chatBox.innerHTML = '<div class="text-center text-slate-400 py-8"><i class="fa-solid fa-comments text-4xl mb-2"></i><p>Chưa có tin nhắn</p></div>';
                    }
                    chatBox.scrollTop = chatBox.scrollHeight;
                });
        }

        function appendQuickMessage(msg) {
            const chatBox = document.getElementById('quick-chat-messages');
            const isMe = msg.sender_type === 'teacher';
            const time = new Date(msg.timestamp).toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'});
            
            chatBox.insertAdjacentHTML('beforeend', `
                <div class="flex ${isMe ? 'justify-end' : 'justify-start'} animate-fadeIn">
                    <div class="max-w-[75%]">
                        <div class="rounded-2xl px-4 py-3 text-sm shadow-md ${isMe ? 'bg-gradient-to-r from-blue-500 to-cyan-600 text-white' : 'bg-white text-slate-800 border border-slate-200'}">
                            ${msg.message}
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1 ${isMe ? 'text-right' : 'text-left'}">${time}</p>
                    </div>
                </div>
            `);
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        function sendQuickMessage(e) {
            e.preventDefault();
            const inp = document.getElementById('quick-chat-input');
            const txt = inp.value.trim();
            if(!txt) return;
            
            const fd = new FormData();
            fd.append('attempt_id', currentChatAttemptId);
            fd.append('message', txt);
            
            fetch('api_monitor.php?action=send_message', {method:'POST', body:fd})
                .then(r => r.json())
                .then(d => {
                    if(d.status === 'success') {
                        appendQuickMessage({
                            sender_type: 'teacher',
                            message: txt,
                            timestamp: new Date().toISOString()
                        });
                        inp.value = '';
                    }
                });
        }

        // Gallery functions
        function openProofGallery(logs, studentName) {
            const modal = document.getElementById('gallery-modal');
            const grid = document.getElementById('gallery-grid');
            const emptyMsg = document.getElementById('gallery-empty');
            
            document.getElementById('gallery-student-name').textContent = studentName;
            grid.innerHTML = '';
            
            const logsWithImages = logs.filter(l => l.proof_image);
            
            if (logsWithImages.length === 0) {
                emptyMsg.classList.remove('hidden');
                grid.classList.add('hidden');
            } else {
                emptyMsg.classList.add('hidden');
                grid.classList.remove('hidden');
                
                logsWithImages.forEach(log => {
                    const item = document.createElement('div');
                    item.className = 'group relative bg-white p-2 rounded-xl shadow-md border border-slate-200 cursor-pointer hover:border-indigo-500 hover:shadow-xl transition-all transform hover:scale-105';
                    item.innerHTML = `
                        <div class="aspect-video bg-slate-900 rounded-lg overflow-hidden relative">
                            <img src="${log.proof_image}" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition-opacity" onclick="viewFullImage('${log.proof_image}')">
                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/40 backdrop-blur-sm">
                                <span class="bg-white text-slate-800 text-xs px-3 py-1.5 rounded-full font-bold shadow-lg">
                                    <i class="fa-solid fa-lock mr-1"></i> Xem chi tiết
                                </span>
                            </div>
                        </div>
                        <p class="text-xs font-bold text-red-600 mt-2 truncate" title="${log.details}">
                            <i class="fa-solid fa-exclamation-triangle mr-1"></i> ${log.details}
                        </p>
                        <p class="text-[10px] text-slate-400 mt-1">
                            <i class="fa-solid fa-clock mr-1"></i> ${new Date(log.timestamp).toLocaleString('vi-VN')}
                        </p>
                    `;
                    grid.appendChild(item);
                });
            }
            
            modal.classList.remove('hidden');
        }

        function closeGallery() {
            document.getElementById('gallery-modal').classList.add('hidden');
        }

        function viewFullImage(src) {
            const w = window.open("", "_blank", "toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=no,resizable=yes,width=900,height=700,top=50,left=50");
            const watermarkText = currentTeacherName.toUpperCase();

            w.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                <title>Bằng chứng vi phạm (Chế độ Bảo mật)</title>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body { 
                        margin: 0; padding: 0; 
                        background-color: #0f172a; 
                        display: flex; justify-content: center; align-items: center; 
                        height: 100vh; overflow: hidden; 
                        user-select: none; -webkit-user-select: none; 
                        font-family: sans-serif;
                    }
                    canvas { 
                        max-width: 95%; max-height: 95%; 
                        box-shadow: 0 0 30px rgba(0,0,0,0.5);
                        border: 1px solid #334155;
                        border-radius: 8px;
                    }
                    @media print { body { display: none; } }
                    .overlay {
                        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                        background: transparent; z-index: 9999;
                    }
                </style>
                </head>
                <body>
                    <canvas id="secureCanvas"></canvas>
                    <div class="overlay"></div>
                    <script>
                        document.addEventListener('contextmenu', e => e.preventDefault());
                        document.addEventListener('keydown', function(e) {
                            if (e.key === 'F12' || (e.ctrlKey && (e.key === 's' || e.key === 'p' || e.key === 'u' || e.key === 'c'))) { 
                                e.preventDefault(); alert('CẢNH BÁO: Tính năng này bị vô hiệu hóa vì lý do bảo mật.'); return false;
                            }
                            if (e.key === 'PrintScreen') {
                                navigator.clipboard.writeText('');
                                alert('CẢNH BÁO: Không được phép chụp màn hình.');
                                document.body.style.display = 'none';
                                setTimeout(() => document.body.style.display = 'flex', 1000);
                            }
                            if (e.shiftKey && e.key.toLowerCase() === 's') { 
                                 e.preventDefault(); alert('CẢNH BÁO: Không được phép chụp màn hình.'); return false;
                            }
                            if (e.metaKey && e.shiftKey) { e.preventDefault(); return false; } 
                        });

                        window.addEventListener('blur', () => { 
                            document.body.style.filter = 'blur(20px) grayscale(100%)'; 
                            document.title = "Đã ẩn nội dung"; 
                        });
                        window.addEventListener('focus', () => { 
                            document.body.style.filter = 'none'; 
                            document.title = "Bằng chứng vi phạm (Chế độ Bảo mật)"; 
                        });

                        const canvas = document.getElementById('secureCanvas');
                        const ctx = canvas.getContext('2d');
                        const img = new Image();
                        img.src = "${src}";
                        
                        img.onload = function() {
                            canvas.width = img.width;
                            canvas.height = img.height;
                            ctx.drawImage(img, 0, 0);
                            
                            ctx.globalAlpha = 0.4;
                            const fontSize = Math.max(20, Math.floor(canvas.width / 10)); 
                            ctx.font = 'bold ' + fontSize + 'px Arial';
                            ctx.fillStyle = 'rgba(255, 255, 255, 0.8)';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            
                            const text = "${watermarkText}";
                            ctx.shadowColor = "black";
                            ctx.shadowBlur = 10;
                            ctx.fillText(text, canvas.width / 2, canvas.height / 2);
                            
                            ctx.shadowBlur = 0;
                            ctx.globalAlpha = 0.05;
                            ctx.fillStyle = 'black';
                            ctx.fillRect(0, 0, canvas.width, canvas.height);
                        };

                        setInterval(() => { 
                            const start = performance.now(); 
                            debugger; 
                            if (performance.now() - start > 100) { 
                                window.close(); 
                            } 
                        }, 2000);
                    <\/script>
                </body>
                </html>
            `);
            w.document.close();
        }

        // Bulk actions
        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(cb => {
                if (cb.closest('tr').style.display !== 'none') {
                    cb.checked = selectAll.checked;
                }
            });
            updateBulkPanel();
        }

        function updateBulkPanel() {
            const selected = document.querySelectorAll('.student-checkbox:checked').length;
            document.getElementById('selected-count').textContent = selected;
            
            const panel = document.getElementById('bulk-panel');
            if (selected > 0) {
                panel.style.transform = 'translateY(0)';
            } else {
                panel.style.transform = 'translateY(100%)';
            }
        }

        function clearSelection() {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('select-all').checked = false;
            updateBulkPanel();
        }

        function toggleBulkActions() {
            const panel = document.getElementById('bulk-panel');
            if (panel.style.transform === 'translateY(0px)') {
                panel.style.transform = 'translateY(100%)';
            } else {
                panel.style.transform = 'translateY(0)';
            }
        }

        function getSelectedAttempts() {
            const selected = [];
            document.querySelectorAll('.student-checkbox:checked').forEach(cb => {
                selected.push(cb.dataset.attemptId);
            });
            return selected;
        }

        function bulkSendMessage() {
            const selected = getSelectedAttempts();
            if (selected.length === 0) return;
            
            const message = prompt(`Nhập tin nhắn gửi đến ${selected.length} thí sinh:`);
            if (!message) return;
            
            const fd = new FormData();
            fd.append('attempt_ids', JSON.stringify(selected));
            fd.append('message', message);
            
            fetch('api_monitor.php?action=bulk_send_message', {method: 'POST', body: fd})
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        alert(`Đã gửi tin nhắn đến ${selected.length} thí sinh!`);
                        clearSelection();
                    }
                });
        }

        function bulkWarning() {
            const selected = getSelectedAttempts();
            if (selected.length === 0) return;
            
            if (!confirm(`Gửi cảnh báo đến ${selected.length} thí sinh?`)) return;
            
            const message = '⚠️ CẢNH BÁO: Vui lòng tập trung vào bài thi và tuân thủ quy định!';
            
            const fd = new FormData();
            fd.append('attempt_ids', JSON.stringify(selected));
            fd.append('message', message);
            
            fetch('api_monitor.php?action=bulk_send_message', {method: 'POST', body: fd})
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        alert(`Đã gửi cảnh báo đến ${selected.length} thí sinh!`);
                        clearSelection();
                    }
                });
        }

        function bulkStop() {
            const selected = getSelectedAttempts();
            if (selected.length === 0) return;
            
            if (!confirm(`ĐÌNH CHỈ ${selected.length} thí sinh? Hành động này không thể hoàn tác!`)) return;
            
            const fd = new FormData();
            fd.append('attempt_ids', JSON.stringify(selected));
            
            fetch('api_monitor.php?action=bulk_stop_exam', {method: 'POST', body: fd})
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        alert(`Đã đình chỉ ${selected.length} thí sinh!`);
                        location.reload();
                    }
                });
        }

        function stopExam(id, name) {
            if(confirm(`ĐÌNH CHỈ BÀI THI: ${name}?\n\nThí sinh sẽ bị buộc nộp bài ngay lập tức.`)) {
                const fd = new FormData();
                fd.append('attempt_id', id);
                fetch('api_monitor.php?action=stop_exam', {method:'POST', body:fd})
                    .then(r => r.json())
                    .then(d => {
                        if(d.status === 'success') {
                            alert('Đã đình chỉ thành công!');
                            location.reload();
                        }
                    });
            }
        }

        function exportViolationReport() {
            window.location.href = `export_violation_report.php?test_id=${testId}`;
        }

        // Event listeners
        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', updateBulkPanel);
        });

        // Auto-refresh stats every 10 seconds
        setInterval(updateStats, 10000);

        // Initialize
        updateStats();
    </script>
</body>
</html>