<?php
require_once '../config.php';
require_once ROOT_PATH . '/_partials/Header.php';
// Trang này cần attempt_id để hoạt động
if (!isset($_GET['attempt_id'])) {
    die("Không tìm thấy lần làm bài nào.");
}

$attempt_id = $_GET['attempt_id'];

// Lấy thông tin về lần làm bài và bài test
$stmt = $pdo->prepare("
    SELECT t.title, t.duration_minutes 
    FROM test_attempts ta
    JOIN tests t ON ta.test_id = t.id
    WHERE ta.id = ?
");
$stmt->execute([$attempt_id]);
$test_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test_info) {
    die("Thông tin bài kiểm tra không hợp lệ.");
}

// Lấy danh sách câu hỏi và câu trả lời
$stmt = $pdo->prepare("
    SELECT q.id as question_id, q.question_text, a.id as answer_id, a.answer_text
    FROM questions q
    JOIN answers a ON q.id = a.question_id
    WHERE q.test_id = (SELECT test_id FROM test_attempts WHERE id = ?)
    ORDER BY q.id, a.id
");
$stmt->execute([$attempt_id]);
$qa_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sắp xếp lại dữ liệu thành mảng câu hỏi -> câu trả lời
$questions = [];
foreach ($qa_raw as $row) {
    $questions[$row['question_id']]['text'] = $row['question_text'];
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
    <title>Làm bài kiểm tra: <?php echo htmlspecialchars($test_info['title']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* CSS riêng cho trang làm bài */
        #proctoring-container {
            position: fixed;
            bottom: 10px;
            right: 10px;
            border: 2px solid #0056b3;
            border-radius: 5px;
            overflow: hidden;
        }
        #webcam, #output {
            width: 200px;
            height: 150px;
        }
        #timer {
            position: fixed;
            top: 80px;
            right: 20px;
            background: #ffc107;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 1.2rem;
        }
    </style>
</head>
<body data-attempt-id="<?php echo $attempt_id; ?>">
    <div id="timer"></div>

    <div class="container">
        <h1><?php echo htmlspecialchars($test_info['title']); ?></h1>
        <p>Vui lòng không thoát khỏi trang này trong quá trình làm bài.</p>
        
        <form id="test-form" action="/student/submit_test.php" method="POST">
            <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
            
            <?php $q_num = 1; foreach ($questions as $qid => $qdata): ?>
                <div class="question-block">
                    <h4>Câu <?php echo $q_num++; ?>: <?php echo htmlspecialchars($qdata['text']); ?></h4>
                    <?php foreach ($qdata['answers'] as $answer): ?>
                        <div>
                            <input type="radio" name="answers[<?php echo $qid; ?>]" value="<?php echo $answer['id']; ?>" id="ans-<?php echo $answer['id']; ?>" required>
                            <label for="ans-<?php echo $answer['id']; ?>"><?php echo htmlspecialchars($answer['text']); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit">Nộp bài</button>
        </form>
    </div>

    <!-- Container cho Giám sát Webcam -->
    <div id="proctoring-container">
        <video id="webcam" autoplay muted playsinline></video>
        <canvas id="output" style="display: none;"></canvas>
    </div>

    <!-- Tải các thư viện cần thiết cho giám sát -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.11.0/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/pose-detection@2.0.0/dist/pose-detection.min.js"></script>

    <!-- Tải mã JS giám sát -->
    <script src="/assets/js/take_test.js"></script>

    <!-- Script đếm ngược thời gian -->
    <script>
        const durationMinutes = <?php echo $test_info['duration_minutes']; ?>;
        const timerElement = document.getElementById('timer');
        let timeLeft = durationMinutes * 60;

        const timerInterval = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            let seconds = timeLeft % 60;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            timerElement.textContent = `${minutes}:${seconds}`;
            timeLeft--;

            if (timeLeft < 0) {
                clearInterval(timerInterval);
                alert("Hết giờ làm bài! Bài của bạn sẽ được nộp tự động.");
                document.getElementById('test-form').submit();
            }
        }, 1000);
    </script>
</body>
</html>
