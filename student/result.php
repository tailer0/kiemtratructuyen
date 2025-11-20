<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) header('Location: /index.php');

// Giả sử submit_test.php redirect về đây kèm attempt_id hoặc lấy từ session
$attempt_id = $_GET['attempt_id'] ?? 0; 

// Lấy kết quả
$stmt = $pdo->prepare("
    SELECT ta.*, t.title 
    FROM test_attempts ta 
    JOIN tests t ON ta.test_id = t.id 
    WHERE ta.id = ? AND ta.user_id = ?
");
$stmt->execute([$attempt_id, $_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) die("Không tìm thấy kết quả.");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả thi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-50 h-screen flex items-center justify-center p-4">
    
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full text-center">
        <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6 text-4xl">
            <i class="fa-solid fa-trophy"></i>
        </div>
        
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Hoàn thành bài thi!</h1>
        <p class="text-gray-500 mb-6"><?php echo htmlspecialchars($result['title']); ?></p>
        
        <div class="bg-gray-50 rounded-xl p-6 mb-8 border border-gray-100">
            <p class="text-sm text-gray-500 uppercase tracking-wider mb-1">Điểm số của bạn</p>
            <div class="text-5xl font-bold text-indigo-600">
                <?php echo number_format($result['score'], 2); ?>
                <span class="text-lg text-gray-400 font-normal">/ 10</span>
            </div>
        </div>
        
        <div class="grid grid-cols-2 gap-4 mb-8 text-sm">
            <div class="text-gray-600">
                <i class="fa-regular fa-clock mr-2"></i>
                Bắt đầu: <?php echo date('H:i', strtotime($result['start_time'])); ?>
            </div>
            <div class="text-gray-600">
                <i class="fa-solid fa-check-double mr-2"></i>
                Nộp bài: <?php echo date('H:i', strtotime($result['end_time'])); ?>
            </div>
        </div>

        <a href="index.php" class="block w-full bg-indigo-600 text-white py-3 rounded-lg font-medium hover:bg-indigo-700 transition-colors">
            Về trang chủ
        </a>
    </div>

</body>
</html>