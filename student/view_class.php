<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
    header('Location: /index.php');
    exit();
}

$class_id = $_GET['id'] ?? 0;
$student_id = $_SESSION['user_id'];

// Check member
$stmt = $pdo->prepare("SELECT * FROM class_members WHERE class_id = ? AND user_id = ?");
$stmt->execute([$class_id, $student_id]);
if (!$stmt->fetch()) {
    die("Bạn chưa tham gia lớp này.");
}

$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch();

// Lấy bài thi
$stmt = $pdo->prepare("
    SELECT t.*, 
    (SELECT score FROM test_attempts ta WHERE ta.test_id = t.id AND ta.user_id = ? ORDER BY ta.score DESC LIMIT 1) as my_score
    FROM tests t 
    WHERE t.class_id = ? AND t.status != 'draft'
    ORDER BY t.created_at DESC
");
$stmt->execute([$student_id, $class_id]);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lớp: <?php echo htmlspecialchars($class['class_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-50 text-slate-800 min-h-screen p-4 md:p-8">
    <div class="max-w-5xl mx-auto">
        <div class="mb-6 flex items-center gap-3">
            <a href="index.php" class="text-slate-500 hover:text-indigo-600"><i class="fa-solid fa-arrow-left"></i> Quay lại</a>
            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($class['class_name']); ?></h1>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bài kiểm tra</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thời gian</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Kết quả</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Hành động</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($tests as $test): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($test['title']); ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo $test['duration_minutes']; ?> phút</td>
                        <td class="px-6 py-4">
                            <?php if ($test['status'] == 'published'): ?>
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Đang mở</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Đã đóng</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right text-sm font-bold text-indigo-600">
                            <?php echo $test['my_score'] !== null ? $test['my_score'] : '--'; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <?php if ($test['my_score'] !== null): ?>
                                <span class="text-gray-500 text-sm italic">Đã làm</span>
                            <?php elseif ($test['status'] == 'published'): ?>
                                <a href="take_test.php?test_id=<?php echo $test['id']; ?>" class="text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                    Làm bài
                                </a>
                            <?php else: ?>
                                <span class="text-gray-400 text-sm">Hết hạn</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>