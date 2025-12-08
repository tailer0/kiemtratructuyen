<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) header('Location: /index.php');

$attempt_id = $_GET['attempt_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// 1. Lấy thông tin chung
$stmt = $pdo->prepare("
    SELECT ta.*, t.title, t.max_score 
    FROM test_attempts ta 
    JOIN tests t ON ta.test_id = t.id 
    WHERE ta.id = ? AND ta.user_id = ?
");
$stmt->execute([$attempt_id, $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) die("Không tìm thấy kết quả.");

$test_id = $result['test_id'];
// Tính toán thời gian làm bài
$start = strtotime($result['start_time']);
$end = strtotime($result['end_time']);
$duration_seconds = $end - $start;
$duration_min = floor($duration_seconds / 60);
$duration_sec = $duration_seconds % 60;
$time_str = ($duration_min > 0 ? "{$duration_min} phút " : "") . "{$duration_sec} giây";

// 2. Lấy dữ liệu câu hỏi và đáp án
$stmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? ORDER BY id ASC");
$stmt->execute([$test_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT a.* FROM answers a 
    JOIN questions q ON a.question_id = q.id 
    WHERE q.test_id = ?
");
$stmt->execute([$test_id]);
$all_answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$answers_by_question = [];
foreach ($all_answers as $ans) {
    $answers_by_question[$ans['question_id']][] = $ans;
}

$stmt = $pdo->prepare("SELECT question_id, answer_id FROM user_answers WHERE attempt_id = ?");
$stmt->execute([$attempt_id]);
$user_choices_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user_choices = [];
foreach ($user_choices_raw as $uc) {
    $user_choices[$uc['question_id']][] = $uc['answer_id'];
}

// Thống kê sơ bộ số câu đúng để hiển thị Dashboard
$total_questions = count($questions);
$total_correct_count = 0;

// Tính trước logic đúng sai cho từng câu để dùng cho Dashboard
$processed_questions = [];
foreach ($questions as $q) {
    $q_id = $q['id'];
    $q_answers = $answers_by_question[$q_id] ?? [];
    $user_selected_ids = $user_choices[$q_id] ?? [];
    
    $correct_ids = array_map(function($a) { return $a['id']; }, array_filter($q_answers, function($a) { return $a['is_correct'] == 1; }));
    
    sort($user_selected_ids);
    sort($correct_ids);
    
    $is_correct = ($user_selected_ids === $correct_ids);
    if ($is_correct) $total_correct_count++;
    
    $processed_questions[] = [
        'data' => $q,
        'answers' => $q_answers,
        'user_selected' => $user_selected_ids,
        'is_correct' => $is_correct
    ];
}

$accuracy = $total_questions > 0 ? round(($total_correct_count / $total_questions) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả chi tiết</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> 
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .filter-btn.active { background-color: #4f46e5; color: white; border-color: #4f46e5; }
    </style>
</head>
<body class="py-8 px-4 min-h-screen">
    
    <div class="max-w-4xl mx-auto space-y-6">

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-green-800 px-6 py-4 flex justify-between items-center">
                <h1 class="text-white font-bold text-lg"><i class="fa-solid fa-chart-pie mr-2"></i> Kết quả bài thi</h1>
                <a href="index.php" class="text-indigo-100 hover:text-white text-sm font-medium transition-colors">
                    <i class="fa-solid fa-house mr-1"></i> Trang chủ
                </a>
            </div>

            <div class="p-6">
                <h2 class="text-center text-xl font-semibold text-gray-800 mb-6"><?php echo htmlspecialchars($result['title']); ?></h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-indigo-50 rounded-xl p-5 text-center border border-indigo-100 relative overflow-hidden">
                        <div class="text-gray-500 text-sm font-medium uppercase tracking-wider mb-1">Điểm số</div>
                        <div class="text-4xl font-bold text-indigo-600">
                            <?php echo number_format($result['score'], 2); ?>
                        </div>
                        <div class="text-xs text-indigo-400 mt-1">Thang điểm 10</div>
                    </div>

                    <div class="bg-green-50 rounded-xl p-5 text-center border border-green-100">
                        <div class="text-gray-500 text-sm font-medium uppercase tracking-wider mb-1">Độ chính xác</div>
                        <div class="text-4xl font-bold text-green-600">
                            <?php echo $total_correct_count; ?>/<php echo $total_questions; ?>
                        </div>
                        <div class="text-xs text-green-500 mt-1"><?php echo $accuracy; ?>% câu trả lời đúng</div>
                    </div>

                    <div class="bg-orange-50 rounded-xl p-5 text-center border border-orange-100">
                        <div class="text-gray-500 text-sm font-medium uppercase tracking-wider mb-1">Thời gian</div>
                        <div class="text-3xl font-bold text-orange-600 mt-1">
                            <?php echo $time_str; ?>
                        </div>
                        <div class="text-xs text-orange-400 mt-2">Hoàn thành bài thi</div>
                    </div>
                </div>

                <div class="mb-2">
                    <div class="flex justify-between text-xs font-medium text-gray-500 mb-1">
                        <span>Tiến độ hoàn thành</span>
                        <span><?php echo $accuracy; ?>%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-1000" style="width: <?php echo $accuracy; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 justify-center md:justify-start">
            <button onclick="filterQuestions('all')" class="filter-btn active px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-all shadow-sm">
                Tất cả câu hỏi
            </button>
            <button onclick="filterQuestions('correct')" class="filter-btn px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-green-50 hover:text-green-700 hover:border-green-200 transition-all shadow-sm">
                <i class="fa-solid fa-check text-green-500 mr-1"></i> Chỉ xem câu đúng
            </button>
            <button onclick="filterQuestions('incorrect')" class="filter-btn px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-red-50 hover:text-red-700 hover:border-red-200 transition-all shadow-sm">
                <i class="fa-solid fa-xmark text-red-500 mr-1"></i> Chỉ xem câu sai
            </button>
        </div>

        <div class="space-y-6" id="question-list">
            <?php 
            $q_index = 1;
            foreach ($processed_questions as $item): 
                $q = $item['data'];
                $is_correct = $item['is_correct'];
                $status_class = $is_correct ? 'border-l-4 border-l-green-500' : 'border-l-4 border-l-red-500';
                $data_status = $is_correct ? 'correct' : 'incorrect';
            ?>
                
                <div class="question-item bg-white rounded-xl shadow-sm border border-gray-100 p-6 <?php echo $status_class; ?>" data-status="<?php echo $data_status; ?>">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex gap-3">
                            <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center bg-gray-100 text-gray-600 rounded-full font-bold text-sm">
                                <?php echo $q_index++; ?>
                            </span>
                            <div>
                                <h3 class="text-gray-800 font-medium text-lg leading-snug">
                                    <?php echo htmlspecialchars($q['question_text'] ?? $q['content'] ?? 'Câu hỏi...'); ?>
                                </h3>
                                <div class="mt-1 text-xs text-gray-400">
                                    Dạng: <?php echo ($q['question_type'] == 'checkbox') ? 'Chọn nhiều đáp án' : 'Chọn 1 đáp án'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($is_correct): ?>
                            <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full whitespace-nowrap">
                                ĐÚNG <i class="fa-solid fa-check ml-1"></i>
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-full whitespace-nowrap">
                                SAI <i class="fa-solid fa-xmark ml-1"></i>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 gap-3 pl-11">
                        <?php foreach ($item['answers'] as $ans): 
                            $is_selected = in_array($ans['id'], $item['user_selected']);
                            $is_key = ($ans['is_correct'] == 1);
                            
                            // Style Logic
                            $wrapper_class = "relative border rounded-lg p-3 flex items-center transition-all ";
                            $icon_feedback = "";

                            if ($is_selected && $is_key) {
                                // Chọn ĐÚNG
                                $wrapper_class .= "bg-green-50 border-green-500 ring-1 ring-green-500";
                                $icon_feedback = '<i class="fa-solid fa-circle-check text-green-500 text-xl absolute right-3"></i>';
                            } elseif ($is_selected && !$is_key) {
                                // Chọn SAI
                                $wrapper_class .= "bg-red-50 border-red-500 ring-1 ring-red-500";
                                $icon_feedback = '<i class="fa-solid fa-circle-xmark text-red-500 text-xl absolute right-3"></i>';
                            } elseif (!$is_selected && $is_key) {
                                // Đáp án đúng mà không chọn (Đáp án hệ thống)
                                $wrapper_class .= "bg-indigo-50 border-indigo-300 border-dashed";
                                $icon_feedback = '<span class="absolute right-3 text-xs font-bold text-indigo-600 bg-white px-2 py-1 border border-indigo-200 rounded shadow-sm">ĐÁP ÁN ĐÚNG</span>';
                            } else {
                                // Đáp án thường
                                $wrapper_class .= "bg-white border-gray-200 hover:bg-gray-50 opacity-60";
                            }
                        ?>
                            <div class="<?php echo $wrapper_class; ?>">
                                <div class="w-5 h-5 rounded border mr-3 flex items-center justify-center 
                                    <?php echo $is_selected ? ($is_key ? 'bg-green-500 border-green-500' : 'bg-red-500 border-red-500') : 'border-gray-400 bg-white'; ?>">
                                    <?php if($is_selected): ?>
                                        <i class="fa-solid fa-check text-white text-xs"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <span class="text-sm text-gray-700 font-medium pr-20">
                                    <?php echo htmlspecialchars($ans['answer_text'] ?? $ans['content'] ?? ''); ?>
                                </span>
                                <?php echo $icon_feedback; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>
        
        <div class="text-center pt-6 pb-12">
            <p class="text-gray-400 text-sm">Hệ thống thi trắc nghiệm trực tuyến</p>
        </div>

    </div>

    <script>
        function filterQuestions(type) {
            // 1. Update trạng thái nút bấm
            const buttons = document.querySelectorAll('.filter-btn');
            buttons.forEach(btn => {
                btn.classList.remove('active', 'bg-indigo-600', 'text-white', 'border-indigo-600');
                btn.classList.add('bg-white', 'text-gray-700');
                
                // Nếu là nút đang click, thêm style active
                if (btn.textContent.toLowerCase().includes(type === 'all' ? 'tất cả' : (type === 'correct' ? 'câu đúng' : 'câu sai'))) {
                     // Note: Logic này đơn giản hóa, thực tế dùng class active như CSS ở trên
                }
            });
            
            // Xử lý class active thủ công qua event click target (đơn giản hơn)
            event.currentTarget.classList.remove('bg-white', 'text-gray-700');
            event.currentTarget.classList.add('active');


            // 2. Ẩn/Hiện câu hỏi
            const questions = document.querySelectorAll('.question-item');
            
            questions.forEach(q => {
                if (type === 'all') {
                    q.style.display = 'block';
                    q.classList.add('fade-in'); // Thêm hiệu ứng nếu muốn
                } else {
                    if (q.getAttribute('data-status') === type) {
                        q.style.display = 'block';
                    } else {
                        q.style.display = 'none';
                    }
                }
            });
        }
    </script>
</body>
</html>