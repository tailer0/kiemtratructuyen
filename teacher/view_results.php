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

// --- XỬ LÝ LƯU CẤU HÌNH ĐÌNH CHỈ (AUTO BAN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_rules') {
    $rules = [
        'tab_switch' => intval($_POST['max_tab_switch']),
        'right_click' => intval($_POST['max_right_click']),
        'copy_paste' => intval($_POST['max_copy_paste']),
        'face_missing' => intval($_POST['max_face_missing'])
    ];
    $json_rules = json_encode($rules);
    
    // Cập nhật vào database
    $stmt = $pdo->prepare("UPDATE tests SET suspension_rules = ? WHERE id = ?");
    $stmt->execute([$json_rules, $test_id]);
    
    // Reload trang để áp dụng
    header("Location: view_results.php?test_id=$test_id");
    exit();
}

$current_rules = json_decode($test['suspension_rules'] ?? '{}', true);

// --- LẤY DANH SÁCH KẾT QUẢ & CHI TIẾT LỖI ---
// Sử dụng GROUP_CONCAT để lấy danh sách các loại lỗi (log_type) của từng học sinh
$stmt = $pdo->prepare("
    SELECT ta.*, 
    (SELECT COUNT(*) FROM cheating_logs cl WHERE cl.attempt_id = ta.id) as violation_count,
    (SELECT COUNT(*) FROM cheating_logs cl WHERE cl.attempt_id = ta.id AND cl.timestamp > DATE_SUB(NOW(), INTERVAL 2 MINUTE)) as recent_violations,
    (SELECT GROUP_CONCAT(log_type) FROM cheating_logs cl WHERE cl.attempt_id = ta.id) as violation_types
    FROM test_attempts ta 
    WHERE ta.test_id = ? 
    ORDER BY 
        CASE WHEN ta.status = 'suspended' THEN 1 ELSE 2 END, -- Ưu tiên hiện người bị đình chỉ lên đầu
        violation_count DESC, 
        ta.student_name ASC
");
$stmt->execute([$test_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: Đếm số lượng từng loại lỗi từ chuỗi GROUP_CONCAT
function countErrorTypes($type_string) {
    if (!$type_string) return [];
    $types = explode(',', $type_string);
    return array_count_values($types);
}

// Lấy logs log mới nhất để phục vụ polling realtime
$stmt = $pdo->prepare("SELECT MAX(id) FROM cheating_logs");
$stmt->execute();
$max_log_id = $stmt->fetchColumn() ?: 0;

// Lấy logs cho gallery (Xem ảnh bằng chứng)
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
        
        /* Style đặc biệt cho trạng thái bị đình chỉ */
        .student-row.suspended { background-color: #fff1f2; border-left-color: #e11d48 !important; }
        .student-row.suspended td { opacity: 0.8; }
        .student-row.suspended td:nth-child(2) { opacity: 1; } /* Giữ tên sáng rõ */
        
        .quick-action-btn { @apply transition-all duration-200 transform hover:scale-105 active:scale-95; }
        .student-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .slide-in-right { animation: slideInRight 0.3s ease-out; }
        
        .animate-bounce-slow { animation: bounce-slow 3s infinite; }
        @keyframes bounce-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
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
            
            <!-- NÚT CÀI ĐẶT AUTO BAN (QUAN TRỌNG) -->
            <button onclick="document.getElementById('rules-modal').classList.remove('hidden')" class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-white bg-gradient-to-r from-rose-500 to-pink-600 hover:from-rose-600 hover:to-pink-700 shadow-md transition-all mt-4 mb-2">
                <i class="fa-solid fa-robot w-6 text-center mr-2 text-lg"></i> Cấu hình Auto Ban
            </button>

            <div class="pt-4 pb-2 px-3">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Công cụ nhanh</p>
            </div>
            <button onclick="toggleBulkActions()" class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-600 hover:bg-amber-50 hover:text-amber-600 transition-all">
                <i class="fa-solid fa-bolt w-6 text-center mr-2 text-lg"></i> Hành động hàng loạt
            </button>
            <button onclick="export_excel.php?test_id=<?php echo $test_id; ?>" class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-600 hover:bg-green-50 hover:text-green-600 transition-all">
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
                
                <!-- Badge trạng thái Auto Ban -->
                <?php if(!empty($current_rules) && array_sum($current_rules) > 0): ?>
                    <span class="ml-4 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-700 border border-rose-200 flex items-center shadow-sm">
                        <i class="fa-solid fa-robot mr-1.5"></i> AUTO BAN: ON
                    </span>
                <?php else: ?>
                    <span class="ml-4 px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200 flex items-center">
                        <i class="fa-solid fa-power-off mr-1.5"></i> AUTO BAN: OFF
                    </span>
                <?php endif; ?>

                <span class="ml-2 px-3 py-1 rounded-full text-xs font-bold bg-gradient-to-r from-green-400 to-emerald-500 text-white shadow-lg flex items-center animate-pulse">
                    <i class="fa-solid fa-circle text-[8px] mr-1.5"></i> LIVE
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
            
            <!-- Stats Cards -->
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
                                <?php echo count(array_filter($results, fn($r) => empty($r['end_time']) && $r['status'] != 'suspended')); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm animate-pulse">
                            <i class="fa-solid fa-pen-to-square text-2xl"></i>
                        </div>
                    </div>
                </div>
                <!-- Card Bị Đình Chỉ (Thống kê mới) -->
                <div class="bg-gradient-to-br from-rose-600 to-pink-700 p-4 rounded-xl shadow-lg text-white transform hover:scale-105 transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase opacity-90">Bị đình chỉ</p>
                            <p class="text-3xl font-extrabold mt-1" id="suspended-count">
                                <?php echo count(array_filter($results, fn($r) => $r['status'] == 'suspended')); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                            <i class="fa-solid fa-ban text-2xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-amber-500 to-orange-600 p-4 rounded-xl shadow-lg text-white transform hover:scale-105 transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase opacity-90">Cảnh báo</p>
                            <p class="text-3xl font-extrabold mt-1" id="warning-count">
                                <?php echo count(array_filter($results, fn($r) => $r['violation_count'] > 0 && $r['status'] != 'suspended')); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                            <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-slate-700 to-slate-800 p-4 rounded-xl shadow-lg text-white transform hover:scale-105 transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase opacity-90">Tổng lỗi</p>
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

            <!-- Filter Bar -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 mb-6">
                <div class="flex flex-col md:flex-row gap-4 items-center">
                    <div class="flex-1 relative">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="search-input" placeholder="Tìm kiếm theo tên, MSSV..." class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm" onkeyup="filterStudents()">
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <button class="filter-btn active px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 hover:bg-slate-100 transition-all" data-filter="all" onclick="setFilter('all')">Tất cả</button>
                        <button class="filter-btn px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 hover:bg-slate-100 transition-all" data-filter="active" onclick="setFilter('active')"><i class="fa-solid fa-pen text-green-600 mr-1"></i> Đang thi</button>
                        <button class="filter-btn px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 hover:bg-slate-100 transition-all" data-filter="suspended" onclick="setFilter('suspended')"><i class="fa-solid fa-ban text-rose-600 mr-1"></i> Bị đình chỉ</button>
                    </div>
                </div>
            </div>

            <!-- Student Table View -->
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
                                <th class="px-6 py-3 text-left">Trạng thái</th>
                                <th class="px-6 py-3 text-left">Chi tiết lỗi (Loại & Số lượng)</th>
                                <th class="px-6 py-3 text-left">Tổng lỗi</th>
                                <th class="px-6 py-3 text-right">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100" id="results-body">
                            <?php foreach ($results as $row): 
                                $is_suspended = ($row['status'] === 'suspended');
                                $row_class = "hover:bg-slate-50 transition-all duration-300 student-row";
                                // Border trái thể hiện trạng thái
                                $border_class = $is_suspended ? "border-l-4 border-l-rose-600 suspended" : ($row['end_time'] ? "border-l-4 border-l-slate-400" : "border-l-4 border-l-green-400");
                                
                                // Phân tích lỗi
                                $violation_counts = countErrorTypes($row['violation_types']);
                            ?>
                            <tr id="row-<?php echo $row['id']; ?>" class="<?php echo $row_class . ' ' . $border_class; ?>" data-status="<?php echo $is_suspended ? 'suspended' : ($row['end_time'] ? 'finished' : 'active'); ?>" data-student-name="<?php echo strtolower($row['student_name']); ?>" data-student-id="<?php echo strtolower($row['student_id']); ?>">
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
                                    <?php if ($is_suspended): ?>
                                        <span class="inline-flex items-center text-rose-700 bg-rose-100 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm border border-rose-200 animate-pulse">
                                            <i class="fa-solid fa-ban mr-1.5"></i> ĐÌNH CHỈ (0đ)
                                        </span>
                                    <?php elseif ($row['score'] !== null): ?>
                                        <span class="text-sm font-bold text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-200 shadow-sm"><?php echo number_format($row['score'], 2); ?></span>
                                    <?php elseif ($row['end_time']): ?>
                                        <span class="inline-flex items-center text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg text-xs font-medium">
                                            <i class="fa-solid fa-flag-checkered mr-1.5"></i> Đã nộp
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center text-green-600 bg-green-50 px-3 py-1.5 rounded-lg text-xs font-medium border border-green-200 shadow-sm">
                                            <i class="fa-solid fa-circle mr-1.5 text-[8px] animate-pulse"></i> Đang thi
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex gap-2 items-center flex-wrap">
                                        <?php if(isset($violation_counts['tab_switch'])): ?>
                                            <div class="flex items-center text-amber-700 bg-amber-50 px-2 py-1 rounded border border-amber-200 text-xs font-bold" title="Chuyển tab">
                                                <i class="fa-solid fa-window-restore mr-1.5"></i> 
                                                Tab: <?php echo $violation_counts['tab_switch']; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(isset($violation_counts['right_click'])): ?>
                                            <div class="flex items-center text-orange-700 bg-orange-50 px-2 py-1 rounded border border-orange-200 text-xs font-bold" title="Chuột phải">
                                                <i class="fa-solid fa-computer-mouse mr-1.5"></i>
                                                Click: <?php echo $violation_counts['right_click']; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(isset($violation_counts['copy_paste'])): ?>
                                            <div class="flex items-center text-red-700 bg-red-50 px-2 py-1 rounded border border-red-200 text-xs font-bold" title="Copy/Paste">
                                                <i class="fa-solid fa-copy mr-1.5"></i>
                                                Copy: <?php echo $violation_counts['copy_paste']; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(empty($violation_counts)): ?>
                                            <span class="text-xs text-slate-400 italic"><i class="fa-solid fa-check mr-1"></i> Không có vi phạm</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-lg font-bold <?php echo $row['violation_count'] > 0 ? 'text-red-600' : 'text-slate-400'; ?>">
                                        <?php echo $row['violation_count']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <?php $student_logs_json = isset($logs_by_student[$row['id']]) ? json_encode($logs_by_student[$row['id']]) : '[]'; ?>
                                        <button onclick='openProofGallery(<?php echo $student_logs_json; ?>, "<?php echo htmlspecialchars($row['student_name']); ?>")' class="quick-action-btn text-white bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 px-3 py-1.5 rounded-lg shadow-md" title="Xem bằng chứng">
                                            <i class="fa-solid fa-images"></i>
                                        </button>
                                        <button onclick="openQuickChat(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_name']); ?>')" class="quick-action-btn text-white bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 px-3 py-1.5 rounded-lg shadow-md" title="Nhắn tin">
                                            <i class="fa-solid fa-bolt"></i>
                                        </button>
                                        
                                        <!-- Nút Đình chỉ chỉ hiện khi chưa bị đình chỉ và chưa nộp bài -->
                                        <?php if(!$is_suspended && empty($row['end_time'])): ?>
                                        <button onclick="stopExam(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_name']); ?>')" class="quick-action-btn text-white bg-gradient-to-r from-rose-500 to-red-600 hover:from-rose-600 hover:to-red-700 px-3 py-1.5 rounded-lg shadow-md" title="Đình chỉ ngay">
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

            <!-- Grid View (Giữ nguyên logic cũ nhưng thêm style cho suspended) -->
            <div id="grid-view" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <?php foreach ($results as $row): 
                    $is_suspended = ($row['status'] === 'suspended');
                    $violation_color = $is_suspended ? 'from-rose-600 to-pink-700' : ($row['violation_count'] > 3 ? 'from-red-500 to-red-600' : ($row['violation_count'] > 0 ? 'from-amber-500 to-orange-600' : 'from-emerald-500 to-green-600'));
                ?>
                <div class="student-card bg-white rounded-xl shadow-md border border-slate-200 overflow-hidden <?php echo $is_suspended ? 'ring-2 ring-rose-500' : ''; ?>">
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
                        
                        <!-- Thêm thông báo đình chỉ trong Grid View -->
                        <?php if($is_suspended): ?>
                            <div class="bg-rose-100 text-rose-700 px-3 py-2 rounded-lg text-center font-bold text-sm mb-3 border border-rose-200">
                                <i class="fa-solid fa-ban mr-1"></i> BỊ ĐÌNH CHỈ (0 điểm)
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div class="bg-slate-50 rounded-lg p-2 text-center">
                                    <div class="text-xs text-slate-500 font-medium">Điểm</div>
                                    <div class="text-lg font-bold text-indigo-600">
                                        <?php echo $row['score'] !== null ? number_format($row['score'], 1) : '--'; ?>
                                    </div>
                                </div>
                                <div class="bg-slate-50 rounded-lg p-2 text-center">
                                    <div class="text-xs text-slate-500 font-medium">Vi phạm</div>
                                    <div class="text-lg font-bold text-red-600">
                                        <?php echo $row['violation_count']; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="flex gap-2 justify-between">
                            <?php $student_logs_json = isset($logs_by_student[$row['id']]) ? json_encode($logs_by_student[$row['id']]) : '[]'; ?>
                            <button onclick='openProofGallery(<?php echo $student_logs_json; ?>, "<?php echo htmlspecialchars($row['student_name']); ?>")' class="flex-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 px-2 py-1.5 rounded-lg text-xs font-medium transition-all">
                                <i class="fa-solid fa-images"></i>
                            </button>
                            <button onclick="openQuickChat(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_name']); ?>')" class="flex-1 bg-blue-50 hover:bg-blue-100 text-blue-600 px-2 py-1.5 rounded-lg text-xs font-medium transition-all">
                                <i class="fa-solid fa-bolt"></i>
                            </button>
                            <?php if(!$is_suspended && empty($row['end_time'])): ?>
                            <button onclick="stopExam(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_name']); ?>')" class="flex-1 bg-rose-50 hover:bg-rose-100 text-rose-600 px-2 py-1.5 rounded-lg text-xs font-medium transition-all">
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

    <!-- MODAL CẤU HÌNH LUẬT AUTO BAN (MỚI THÊM VÀO) -->
    <div id="rules-modal" class="fixed inset-0 bg-slate-900/60 z-50 hidden flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 animate-bounce-slow relative transform transition-all">
            <button onclick="document.getElementById('rules-modal').classList.add('hidden')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full p-1 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
            <h3 class="text-lg font-bold text-slate-800 mb-2 flex items-center">
                <i class="fa-solid fa-robot text-rose-600 mr-2"></i> Cấu hình Tự động Đình chỉ (Auto Ban)
            </h3>
            <p class="text-sm text-slate-500 mb-6 bg-slate-50 p-3 rounded-lg border border-slate-200 leading-relaxed">
                <i class="fa-solid fa-circle-info text-blue-500 mr-1"></i> 
                Hệ thống sẽ <b>TỰ ĐỘNG</b> đình chỉ thi, chấm 0 điểm và buộc nộp bài ngay lập tức nếu thí sinh vi phạm vượt quá giới hạn bên dưới.
            </p>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_rules">
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-3 bg-amber-50 rounded-lg border border-amber-100 hover:border-amber-300 transition-colors">
                        <label class="text-sm font-bold text-amber-800 flex items-center">
                            <i class="fa-solid fa-window-restore w-6"></i> Chuyển tab tối đa:
                        </label>
                        <div class="flex items-center">
                            <input type="number" name="max_tab_switch" value="<?php echo $current_rules['tab_switch'] ?? 0; ?>" class="w-20 border border-amber-300 rounded-md px-2 py-1 text-center font-bold text-amber-700 focus:ring-2 focus:ring-amber-500 outline-none shadow-sm" min="0">
                            <span class="ml-2 text-xs text-amber-600 font-medium">lần</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-orange-50 rounded-lg border border-orange-100 hover:border-orange-300 transition-colors">
                        <label class="text-sm font-bold text-orange-800 flex items-center">
                            <i class="fa-solid fa-computer-mouse w-6"></i> Chuột phải tối đa:
                        </label>
                        <div class="flex items-center">
                            <input type="number" name="max_right_click" value="<?php echo $current_rules['right_click'] ?? 0; ?>" class="w-20 border border-orange-300 rounded-md px-2 py-1 text-center font-bold text-orange-700 focus:ring-2 focus:ring-orange-500 outline-none shadow-sm" min="0">
                            <span class="ml-2 text-xs text-orange-600 font-medium">lần</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-red-50 rounded-lg border border-red-100 hover:border-red-300 transition-colors">
                        <label class="text-sm font-bold text-red-800 flex items-center">
                            <i class="fa-solid fa-copy w-6"></i> Copy/Paste tối đa:
                        </label>
                        <div class="flex items-center">
                            <input type="number" name="max_copy_paste" value="<?php echo $current_rules['copy_paste'] ?? 0; ?>" class="w-20 border border-red-300 rounded-md px-2 py-1 text-center font-bold text-red-700 focus:ring-2 focus:ring-red-500 outline-none shadow-sm" min="0">
                            <span class="ml-2 text-xs text-red-600 font-medium">lần</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg border border-purple-100 hover:border-purple-300 transition-colors">
                        <label class="text-sm font-bold text-purple-800 flex items-center">
                            <i class="fa-solid fa-user-xmark w-6"></i> Mất mặt (Face) tối đa:
                        </label>
                        <div class="flex items-center">
                            <input type="number" name="max_face_missing" value="<?php echo $current_rules['face_missing'] ?? 0; ?>" class="w-20 border border-purple-300 rounded-md px-2 py-1 text-center font-bold text-purple-700 focus:ring-2 focus:ring-purple-500 outline-none shadow-sm" min="0">
                            <span class="ml-2 text-xs text-purple-600 font-medium">lần</span>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-4 italic text-center">* Điền số 0 để tắt tính năng tự động phạt cho lỗi đó.</p>
                
                <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="document.getElementById('rules-modal').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg font-medium transition-colors">Hủy</button>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg hover:shadow-lg hover:from-indigo-700 hover:to-purple-700 font-bold transition-all transform hover:scale-105 flex items-center">
                        <i class="fa-solid fa-save mr-2"></i> Lưu Cấu hình
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- NOTIFICATION CONTAINER -->
    <div id="notification-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 w-96 pointer-events-none"></div>

    <!-- QUICK CHAT MODAL -->
    <div id="quick-chat-modal" class="fixed inset-0 bg-slate-900/60 z-50 hidden flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden flex flex-col max-h-[85vh]">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-blue-500 to-cyan-600 flex justify-between items-center">
                <h3 class="font-bold text-white text-lg flex items-center">
                    <i class="fa-solid fa-bolt mr-2"></i> Quick Chat: <span id="quick-chat-student-name" class="ml-2"></span>
                </h3>
                <button onclick="closeQuickChat()" class="text-white/80 hover:text-white transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <!-- Phần tin nhắn mẫu -->
            <div class="px-6 py-4 bg-slate-50 border-b">
                <p class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-3">Tin nhắn mẫu</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <button onclick="useTemplate('CẢNH BÁO: Vui lòng tập trung vào bài thi!')" class="text-left bg-white hover:bg-amber-50 border border-amber-200 hover:border-amber-400 rounded-lg px-4 py-2 text-xs transition-all shadow-sm font-medium text-amber-700">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> Cảnh báo chung
                    </button>
                    <button onclick="useTemplate('Phát hiện chuyển tab! Vui lòng quay lại ngay.')" class="text-left bg-white hover:bg-red-50 border border-red-200 hover:border-red-400 rounded-lg px-4 py-2 text-xs transition-all shadow-sm font-medium text-red-700">
                        <i class="fa-solid fa-window-restore mr-1"></i> Chuyển tab
                    </button>
                    <button onclick="useTemplate('Camera không rõ! Vui lòng chỉnh lại.')" class="text-left bg-white hover:bg-orange-50 border border-orange-200 hover:border-orange-400 rounded-lg px-4 py-2 text-xs transition-all shadow-sm font-medium text-orange-700">
                        <i class="fa-solid fa-camera mr-1"></i> Camera
                    </button>
                    <button onclick="useTemplate('Sắp hết giờ. Hãy nộp bài!')" class="text-left bg-white hover:bg-blue-50 border border-blue-200 hover:border-blue-400 rounded-lg px-4 py-2 text-xs transition-all shadow-sm font-medium text-blue-700">
                        <i class="fa-solid fa-clock mr-1"></i> Nhắc giờ
                    </button>
                </div>
            </div>
            <div id="quick-chat-messages" class="flex-1 overflow-y-auto p-6 space-y-3 bg-white min-h-[200px]"></div>
            <div class="p-4 border-t bg-slate-50">
                <form id="quick-chat-form" onsubmit="sendQuickMessage(event)" class="flex gap-2">
                    <input type="hidden" id="quick-chat-attempt-id">
                    <input type="text" id="quick-chat-input" class="flex-1 border border-slate-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Nhập tin nhắn..." required autocomplete="off">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </div>

    <!-- GALLERY MODAL -->
    <div id="gallery-modal" class="fixed inset-0 bg-slate-900/90 z-[60] hidden flex items-center justify-center backdrop-blur-md">
        <button onclick="closeGallery()" class="absolute top-6 right-6 text-white/70 hover:text-white text-3xl transition-colors z-10"><i class="fa-solid fa-xmark"></i></button>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl mx-4 overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b bg-gradient-to-r from-indigo-500 to-purple-600 flex justify-between items-center">
                <h3 class="font-bold text-white text-lg flex items-center"><i class="fa-solid fa-shield-halved mr-2"></i> Bằng chứng vi phạm: <span id="gallery-student-name" class="ml-2"></span></h3>
            </div>
            <div id="gallery-content" class="flex-1 overflow-y-auto p-6 bg-slate-100">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="gallery-grid"></div>
                <p id="gallery-empty" class="text-center text-slate-500 mt-10 hidden"><i class="fa-solid fa-check-circle text-4xl text-emerald-500 mb-3"></i><br>Thí sinh này chưa có vi phạm nào.</p>
            </div>
        </div>
    </div>

    <!-- BULK ACTIONS PANEL -->
    <div id="bulk-panel" class="fixed bottom-0 left-0 right-0 bg-white border-t-2 border-indigo-500 shadow-2xl z-40 transform translate-y-full transition-transform duration-300">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <span class="font-bold text-slate-700"><span id="selected-count">0</span> thí sinh đã chọn</span>
                <button onclick="clearSelection()" class="text-sm text-slate-500 hover:text-slate-700"><i class="fa-solid fa-xmark mr-1"></i> Bỏ chọn</button>
            </div>
            <div class="flex gap-3">
                <button onclick="bulkSendMessage()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-all shadow-md"><i class="fa-solid fa-comment-dots mr-2"></i> Gửi tin nhắn</button>
                <button onclick="bulkStop()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-all shadow-md"><i class="fa-solid fa-ban mr-2"></i> Đình chỉ</button>
            </div>
        </div>
    </div>

    <script>
        let lastLogId = <?php echo $max_log_id; ?>;
        let currentChatAttemptId = null;
        const testId = <?php echo $test_id; ?>;
        const currentTeacherName = "<?php echo addslashes($_SESSION['user_name'] ?? 'GV'); ?>";
        let currentFilter = 'all';

        // Polling for updates (Cập nhật realtime)
        function pollUpdates() {
            fetch(`api_monitor.php?action=fetch_updates&test_id=${testId}&last_log_id=${lastLogId}`)
                .then(res => res.text()) // Get text first to debug
                .then(text => {
                    try {
                        const data = JSON.parse(text); // Try to parse
                        if (data.status === 'success' && data.new_logs.length > 0) {
                            data.new_logs.forEach(log => { 
                                showNotification(log); 
                                // Hiệu ứng đỏ khi có lỗi mới
                                const row = document.getElementById(`row-${log.attempt_id}`);
                                if (row) row.classList.add('animate-pulse-red');
                            });
                            lastLogId = data.last_log_id;
                            // Reload trang sau 2s để cập nhật số liệu chính xác nếu có vi phạm mới
                            setTimeout(() => location.reload(), 2000);
                        }
                    } catch (e) {
                        // If parse fails, logs the raw text (likely PHP error)
                        console.error('JSON Parse Error:', e, 'Response:', text);
                    }
                })
                .catch(err => console.error('Polling error:', err));
        }
        setInterval(pollUpdates, 5000);

        function showNotification(log) {
            const container = document.getElementById('notification-container');
            const notif = document.createElement('div');
            notif.className = 'pointer-events-auto bg-white border-l-4 border-red-500 shadow-2xl rounded-r-xl p-4 flex items-start mb-3 slide-in-right';
            notif.innerHTML = `
                <div class="mr-3 text-lg"><i class="fa-solid fa-triangle-exclamation text-red-600 mt-1 animate-bounce"></i></div>
                <div class="flex-1">
                    <h4 class="font-bold text-sm text-slate-800">${log.student_name}</h4>
                    <p class="text-xs text-red-600 font-medium">${log.details || 'Vi phạm mới'}</p>
                </div>`;
            container.appendChild(notif);
            setTimeout(() => notif.remove(), 5000);
        }

        function stopExam(id, name) {
            if(confirm(`XÁC NHẬN ĐÌNH CHỈ: ${name}?\n\nHành động này sẽ:\n1. Chấm bài thi 0 điểm.\n2. Buộc nộp bài ngay lập tức.\n3. Không thể hoàn tác.`)) {
                const fd = new FormData();
                fd.append('attempt_id', id);
                // Lưu ý: Bạn cần đảm bảo api_monitor.php case 'stop_exam' đã xử lý set status='suspended' và score=0
                fetch('api_monitor.php?action=stop_exam', {method:'POST', body:fd})
                    .then(r => r.json())
                    .then(d => {
                        if(d.status === 'success') {
                            alert('Đã đình chỉ thành công!');
                            location.reload();
                        } else {
                            alert('Lỗi: ' + (d.message || 'Không thể đình chỉ'));
                        }
                    });
            }
        }

        // Các hàm hỗ trợ khác (View Mode, Filter, Chat, Gallery...)
        function toggleViewMode() {
            document.getElementById('table-view').classList.toggle('hidden');
            document.getElementById('grid-view').classList.toggle('hidden');
        }

        function setFilter(filter) {
            currentFilter = filter;
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
            const rows = document.querySelectorAll('.student-row');
            rows.forEach(row => {
                const status = row.dataset.status;
                if (filter === 'all') row.style.display = '';
                else if (filter === 'active' && status === 'active') row.style.display = '';
                else if (filter === 'suspended' && status === 'suspended') row.style.display = '';
                else row.style.display = 'none';
            });
        }

        // Chat & Message Template Functions
        function useTemplate(msg) {
            document.getElementById('quick-chat-input').value = msg;
            document.getElementById('quick-chat-input').focus();
        }

        function openQuickChat(id, name) {
            currentChatAttemptId = id;
            document.getElementById('quick-chat-student-name').innerText = name;
            document.getElementById('quick-chat-attempt-id').value = id;
            document.getElementById('quick-chat-modal').classList.remove('hidden');
            loadQuickChatHistory(id);
        }
        function closeQuickChat() { document.getElementById('quick-chat-modal').classList.add('hidden'); }
        
        function loadQuickChatHistory(id) {
            const chatBox = document.getElementById('quick-chat-messages');
            chatBox.innerHTML = '<div class="flex justify-center py-4"><i class="fa-solid fa-circle-notch fa-spin text-blue-500 text-xl"></i></div>';
            
            fetch(`api_monitor.php?action=get_chat&attempt_id=${id}`)
                .then(r => r.json())
                .then(d => {
                    chatBox.innerHTML = '';
                    if (d.messages && d.messages.length > 0) {
                        d.messages.forEach(m => appendQuickMessage(m));
                    } else {
                        chatBox.innerHTML = '<div class="text-center text-slate-400 py-8 text-sm"><i class="fa-solid fa-comments text-3xl mb-2 block"></i>Chưa có tin nhắn</div>';
                    }
                    chatBox.scrollTop = chatBox.scrollHeight;
                });
        }

        function appendQuickMessage(msg) {
            const chatBox = document.getElementById('quick-chat-messages');
            const isMe = msg.sender_type === 'teacher';
            const time = new Date(msg.timestamp).toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'});
            
            chatBox.insertAdjacentHTML('beforeend', `
                <div class="flex ${isMe ? 'justify-end' : 'justify-start'} mb-2">
                    <div class="max-w-[80%]">
                        <div class="rounded-xl px-3 py-2 text-sm shadow-sm ${isMe ? 'bg-blue-600 text-white' : 'bg-gray-100 text-slate-800'}">
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
        
        // Gallery Functions
        function openProofGallery(logs, name) {
            document.getElementById('gallery-student-name').innerText = name;
            const grid = document.getElementById('gallery-grid');
            grid.innerHTML = '';
            if(!logs || logs.length === 0) {
                document.getElementById('gallery-empty').classList.remove('hidden');
            } else {
                document.getElementById('gallery-empty').classList.add('hidden');
                logs.filter(l=>l.proof_image).forEach(l => {
                    grid.innerHTML += `
                        <div class="bg-black rounded-lg overflow-hidden relative group cursor-pointer shadow-md hover:shadow-xl transition-all" onclick="window.open('${l.proof_image}')">
                            <img src="${l.proof_image}" class="w-full h-40 object-cover opacity-80 group-hover:opacity-100 transition-opacity">
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-2">
                                <p class="text-white text-xs truncate"><i class="fa-solid fa-triangle-exclamation text-red-400 mr-1"></i> ${l.details || 'Vi phạm'}</p>
                                <p class="text-slate-300 text-[10px]">${new Date(l.timestamp).toLocaleTimeString()}</p>
                            </div>
                        </div>`;
                });
            }
            document.getElementById('gallery-modal').classList.remove('hidden');
        }
        function closeGallery() { document.getElementById('gallery-modal').classList.add('hidden'); }

        // Bulk Actions Toggle
        function toggleBulkActions() {
            const panel = document.getElementById('bulk-panel');
            panel.classList.toggle('translate-y-full');
        }
        function toggleSelectAll() {
            const checked = document.getElementById('select-all').checked;
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = checked);
            updateBulkCount();
        }
        document.querySelectorAll('.student-checkbox').forEach(cb => cb.addEventListener('change', updateBulkCount));
        function updateBulkCount() {
            const count = document.querySelectorAll('.student-checkbox:checked').length;
            document.getElementById('selected-count').innerText = count;
            if(count > 0) document.getElementById('bulk-panel').classList.remove('translate-y-full');
            else document.getElementById('bulk-panel').classList.add('translate-y-full');
        }
        function clearSelection() {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('select-all').checked = false;
            updateBulkCount();
        }
        function bulkSendMessage() {
            const selected = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.dataset.attemptId);
            if(selected.length === 0) return;
            const msg = prompt(`Gửi tin nhắn đến ${selected.length} thí sinh:`);
            if(msg) {
                const fd = new FormData();
                fd.append('attempt_ids', JSON.stringify(selected));
                fd.append('message', msg);
                fetch('api_monitor.php?action=bulk_send_message', {method:'POST', body:fd}).then(() => { alert('Đã gửi!'); clearSelection(); });
            }
        }
        function bulkStop() {
            const selected = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.dataset.attemptId);
            if(selected.length === 0) return;
            if(confirm(`ĐÌNH CHỈ ${selected.length} thí sinh đã chọn?`)) {
                const fd = new FormData();
                fd.append('attempt_ids', JSON.stringify(selected));
                fetch('api_monitor.php?action=bulk_stop_exam', {method:'POST', body:fd}).then(() => { alert('Đã đình chỉ!'); location.reload(); });
            }
        }
    </script>
</body>
</html>