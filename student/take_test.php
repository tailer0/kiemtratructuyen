<?php
require_once '../config.php';

// Bảo vệ trang: Yêu cầu phải có attempt_id
if (!isset($_GET['attempt_id'])) {
    die("Truy cập không hợp lệ.");
}
$attempt_id = $_GET['attempt_id'];

// Lấy thông tin về lần làm bài này
$stmt = $pdo->prepare("SELECT * FROM test_attempts WHERE id = ?");
$stmt->execute([$attempt_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    die("Lần làm bài không tồn tại.");
}
$test_id = $attempt['test_id'];

// Lấy thông tin bài test (đặc biệt là thời gian)
$stmt = $pdo->prepare("SELECT title, duration_minutes FROM tests WHERE id = ?");
$stmt->execute([$test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    die("Bài kiểm tra không tồn tại.");
}

// Lấy danh sách câu hỏi và các lựa chọn trả lời
$stmt = $pdo->prepare("
    SELECT q.id as question_id, q.question_text, a.id as answer_id, a.answer_text 
    FROM questions q
    JOIN answers a ON q.id = a.question_id
    WHERE q.test_id = ?
    ORDER BY q.id, a.id
");
$stmt->execute([$test_id]);
$qa_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sắp xếp lại dữ liệu thành mảng câu hỏi và câu trả lời lồng nhau
$questions = [];
foreach ($qa_raw as $row) {
    if (!isset($questions[$row['question_id']])) {
        $questions[$row['question_id']] = [
            'id' => $row['question_id'],
            'text' => $row['question_text'],
            'answers' => []
        ];
    }
    $questions[$row['question_id']]['answers'][] = [
        'id' => $row['answer_id'],
        'text' => $row['answer_text']
    ];
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Làm bài kiểm tra: <?php echo htmlspecialchars($test['title']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .monitoring-section {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        #webcam {
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($test['title']); ?></h1>
        <div id="timer" class="timer">Thời gian: --:--</div>
        
        <form id="test-form" action="submit_test.php" method="POST">
            <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
            
            <?php $q_number = 1; ?>
            <?php foreach ($questions as $question): ?>
            <div class="question-container">
                <p><strong>Câu <?php echo $q_number++; ?>:</strong> <?php echo htmlspecialchars($question['text']); ?></p>
                <?php foreach ($question['answers'] as $answer): ?>
                <div class="answer">
                    <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="<?php echo $answer['id']; ?>" id="ans-<?php echo $answer['id']; ?>">
                    <label for="ans-<?php echo $answer['id']; ?>"><?php echo htmlspecialchars($answer['text']); ?></label>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            
            <button type="submit" class="button">Nộp bài</button>
        </form>

        <div class="monitoring-section">
            <h4>Giám sát</h4>
            <div id="status-box" style="color: red; font-weight: bold; margin-bottom: 5px;">Đang khởi tạo...</div>
            <video id="webcam" width="320" height="240" autoplay muted></video>
            <p style="font-size: 0.8em; margin-top: 5px;">Vui lòng nhìn thẳng vào màn hình và không rời khỏi vị trí trong suốt quá trình làm bài.</p>
        </div>
        
        <!-- Canvas ẩn để chụp ảnh -->
        <canvas id="captureCanvas" style="display:none;"></canvas>
    </div>

    <!-- Thư viện TensorFlow.js -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-core"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-webgl"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/face-landmarks-detection"></script>

    <!-- Truyền biến từ PHP sang JS -->
    <script>
        const DURATION = <?php echo (int)$test['duration_minutes'] * 60; ?>;
        const ATTEMPT_ID = <?php echo $attempt_id; ?>;
    </script>
    
    <!-- Mã JS giám sát -->
    <script src="/assets/js/take_test.js"></script>
</body>
</html>

