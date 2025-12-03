<?php
session_start();
require_once '../config.php';

// Kiểm tra quyền truy cập
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
    header('Location: /index.php');
    exit();
}

$class_id = $_GET['id'] ?? 0;
$student_id = $_SESSION['user_id'];

// Kiểm tra thành viên
$stmt = $pdo->prepare("SELECT * FROM class_members WHERE class_id = ? AND user_id = ?");
$stmt->execute([$class_id, $student_id]);
if (!$stmt->fetch()) {
    die("Bạn chưa tham gia lớp này.");
}

// Lấy thông tin lớp học và giáo viên
$stmt = $pdo->prepare("
    SELECT c.*, u.name as teacher_name 
    FROM classes c 
    JOIN users u ON c.teacher_id = u.id 
    WHERE c.id = ?
");
$stmt->execute([$class_id]);
$class = $stmt->fetch();

if (!$class) die("Lớp học không tồn tại.");

// Lấy danh sách bài thi và kết quả tốt nhất của học sinh
// Ta cần lấy thêm attempt_id để tạo link xem kết quả
$stmt = $pdo->prepare("
    SELECT t.*, 
    (SELECT score FROM test_attempts ta WHERE ta.test_id = t.id AND ta.user_id = ? ORDER BY ta.score DESC LIMIT 1) as my_score,
    (SELECT id FROM test_attempts ta WHERE ta.test_id = t.id AND ta.user_id = ? ORDER BY ta.score DESC LIMIT 1) as attempt_id,
    (SELECT end_time FROM test_attempts ta WHERE ta.test_id = t.id AND ta.user_id = ? ORDER BY ta.score DESC LIMIT 1) as submit_time
    FROM tests t 
    WHERE t.class_id = ? AND t.status != 'draft'
    ORDER BY t.created_at DESC
");
$stmt->execute([$student_id, $student_id, $student_id, $class_id]);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lớp: <?php echo htmlspecialchars($class['class_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        .test-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

    <!-- Header Section -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-700 pb-24 pt-12 px-4 shadow-lg">
        <div class="max-w-5xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 text-white">
                <div>
                    <a href="index.php" class="inline-flex items-center text-indigo-100 hover:text-white mb-4 transition-colors">
                        <i class="fa-solid fa-arrow-left mr-2"></i> Quay lại Dashboard
                    </a>
                    <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight"><?php echo htmlspecialchars($class['class_name']); ?></h1>
                    <p class="text-indigo-100 mt-2 flex items-center">
                        <i class="fa-solid fa-chalkboard-user mr-2"></i> GV: <?php echo htmlspecialchars($class['teacher_name']); ?>
                    </p>
                </div>
                <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/20">
                    <div class="text-center">
                        <p class="text-xs text-indigo-200 uppercase font-bold tracking-wider">Mã lớp</p>
                        <p class="text-2xl font-mono font-bold mt-1"><?php echo htmlspecialchars($class['class_code']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-5xl mx-auto px-4 -mt-16 mb-12">
        
        <!-- Class Description (Optional) -->
        <?php if(!empty($class['class_description'])): ?>
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 border-l-4 border-indigo-500">
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wide mb-2">Mô tả lớp học</h3>
            <p class="text-slate-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($class['class_description'])); ?></p>
        </div>
        <?php endif; ?>

        <!-- Test List -->
        <div class="space-y-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-xl font-bold text-slate-800 flex items-center">
                    <i class="fa-solid fa-file-signature text-indigo-600 mr-2"></i> Danh sách bài kiểm tra
                </h2>
                <span class="text-sm font-medium text-slate-500 bg-white px-3 py-1 rounded-full shadow-sm border border-slate-200">
                    <?php echo count($tests); ?> bài thi
                </span>
            </div>

            <?php if (empty($tests)): ?>
                <div class="bg-white rounded-xl shadow-sm p-12 text-center border border-dashed border-slate-300">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
                        <i class="fa-regular fa-folder-open text-3xl text-slate-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-slate-900">Chưa có bài kiểm tra nào</h3>
                    <p class="text-slate-500 mt-1">Giáo viên chưa đăng bài kiểm tra nào cho lớp này.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tests as $test): 
                    // Xác định trạng thái hiển thị
                    $is_done = ($test['my_score'] !== null);
                    $is_open = ($test['status'] == 'published');
                    
                    // Màu sắc & Icon dựa trên trạng thái
                    if ($is_done) {
                        $status_color = "bg-emerald-100 text-emerald-700 border-emerald-200";
                        $status_text = "Đã hoàn thành";
                        $status_icon = "fa-check-circle";
                    } elseif ($is_open) {
                        $status_color = "bg-indigo-100 text-indigo-700 border-indigo-200";
                        $status_text = "Đang mở";
                        $status_icon = "fa-clock";
                    } else {
                        $status_color = "bg-slate-100 text-slate-600 border-slate-200";
                        $status_text = "Đã đóng";
                        $status_icon = "fa-lock";
                    }
                ?>
                <div class="test-card bg-white rounded-xl shadow-sm border border-slate-200 p-5 transition-all duration-200 flex flex-col md:flex-row items-start md:items-center gap-4">
                    
                    <!-- Icon bên trái -->
                    <div class="flex-shrink-0">
                        <div class="w-14 h-14 rounded-xl flex items-center justify-center <?php echo $is_done ? 'bg-emerald-500' : ($is_open ? 'bg-indigo-500' : 'bg-slate-400'); ?> text-white shadow-md">
                            <i class="fa-solid fa-file-lines text-2xl"></i>
                        </div>
                    </div>

                    <!-- Thông tin bài thi -->
                    <div class="flex-1 min-w-0 w-full">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 mb-1">
                            <h3 class="text-lg font-bold text-slate-800 truncate" title="<?php echo htmlspecialchars($test['title']); ?>">
                                <?php echo htmlspecialchars($test['title']); ?>
                            </h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_color; ?>">
                                <i class="fa-solid <?php echo $status_icon; ?> mr-1.5"></i> <?php echo $status_text; ?>
                            </span>
                        </div>
                        
                        <div class="flex items-center gap-4 text-sm text-slate-500">
                            <span class="flex items-center"><i class="fa-regular fa-clock mr-1.5"></i> <?php echo $test['duration_minutes']; ?> phút</span>
                            <span class="flex items-center"><i class="fa-regular fa-calendar mr-1.5"></i> <?php echo date('d/m/Y', strtotime($test['created_at'])); ?></span>
                        </div>
                    </div>

                    <!-- Điểm số & Hành động -->
                    <div class="flex-shrink-0 w-full md:w-auto flex flex-row md:flex-col items-center md:items-end justify-between md:justify-center gap-3 mt-3 md:mt-0 border-t md:border-t-0 border-slate-100 pt-3 md:pt-0">
                        
                        <?php if ($is_done): ?>
                            <!-- Hiển thị điểm -->
                            <div class="text-right">
                                <p class="text-xs text-slate-400 font-medium uppercase">Kết quả</p>
                                <p class="text-2xl font-extrabold text-emerald-600 leading-none"><?php echo number_format($test['my_score'], 1); ?> <span class="text-sm font-medium text-emerald-500">đ</span></p>
                            </div>
                            
                            <!-- Nút Xem Kết Quả -->
                            <a href="result.php?attempt_id=<?php echo $test['attempt_id']; ?>" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-emerald-700 bg-emerald-100 hover:bg-emerald-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors w-full md:w-auto">
                                <i class="fa-solid fa-square-poll-vertical mr-2"></i> Xem chi tiết
                            </a>

                        <?php elseif ($is_open): ?>
                            <!-- Trạng thái chưa làm -->
                            <div class="hidden md:block text-right">
                                <p class="text-xs text-slate-400 font-medium uppercase">Trạng thái</p>
                                <p class="text-sm font-bold text-indigo-600">Sẵn sàng</p>
                            </div>

                            <!-- Nút Làm Bài -->
                            <a href="take_test.php?test_id=<?php echo $test['id']; ?>" class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-bold rounded-lg text-white bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 shadow-md hover:shadow-lg transform transition-all active:scale-95 w-full md:w-auto">
                                <i class="fa-solid fa-play mr-2"></i> Bắt đầu thi
                            </a>

                        <?php else: ?>
                            <!-- Trạng thái Đóng -->
                            <div class="text-right">
                                <p class="text-xs text-slate-400 font-medium uppercase">Trạng thái</p>
                                <p class="text-sm font-bold text-slate-500">Hết hạn</p>
                            </div>
                            <button disabled class="inline-flex items-center justify-center px-4 py-2 border border-slate-200 text-sm font-medium rounded-lg text-slate-400 bg-slate-100 cursor-not-allowed w-full md:w-auto">
                                <i class="fa-solid fa-ban mr-2"></i> Không thể làm
                            </button>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>