<?php
require_once '../config.php';
// Bảo vệ trang, đảm bảo có attempt_id hợp lệ
if (!isset($_GET['attempt_id'])) {
    header('Location: /index.php');
    exit();
}

$attempt_id = $_GET['attempt_id'];

// Lấy thông tin về lần làm bài và bài test
$stmt = $pdo->prepare("
    SELECT t.title, t.duration_minutes, a.start_time
    FROM test_attempts a
    JOIN tests t ON a.test_id = t.id
    WHERE a.id = ?
");
$stmt->execute([$attempt_id]);
$attempt = $stmt->fetch();

if (!$attempt) {
    die("Không tìm thấy lần làm bài này.");
}

// Lấy danh sách câu hỏi và câu trả lời
$stmt = $pdo->prepare("
    SELECT q.id as question_id, q.question_text, ans.id as answer_id, ans.answer_text
    FROM questions q
    JOIN answers ans ON q.id = ans.question_id
    WHERE q.test_id = (SELECT test_id FROM test_attempts WHERE id = ?)
    ORDER BY q.id, ans.id
");
$stmt->execute([$attempt_id]);
$qa_pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$questions = [];
foreach ($qa_pairs as $pair) {
    $questions[$pair['question_id']]['text'] = $pair['question_text'];
    $questions[$pair['question_id']]['answers'][] = [
        'id' => $pair['answer_id'],
        'text' => $pair['answer_text']
    ];
}

$duration_seconds = $attempt['duration_minutes'] * 60;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Làm bài kiểm tra: <?php echo htmlspecialchars($attempt['title']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <!-- *** BƯỚC 1.1: THÊM CÁC THƯ VIỆN CẦN THIẾT *** -->
    <!-- TensorFlow.js Core -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-core"></script>
    <!-- Backend (chọn WebGL để có hiệu năng tốt nhất) -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-webgl"></script>
    <!-- Face Landmarks Detection model -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/face-landmarks-detection"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-converter"></script>
</head>
<body>
    <div class="container test-taking-page">
        <div class="main-content">
            <div class="test-header">
                <h1><?php echo htmlspecialchars($attempt['title']); ?></h1>
                <div id="timer" class="timer">Thời gian: --:--</div>
            </div>

            <form id="test-form" action="submit_test.php" method="POST">
                <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                
                <?php $q_index = 0; ?>
                <?php foreach ($questions as $qid => $q_data): ?>
                    <div class="question-block">
                        <h4>Câu <?php echo ++$q_index; ?>: <?php echo htmlspecialchars($q_data['text']); ?></h4>
                        <div class="answers-container">
                            <?php foreach ($q_data['answers'] as $answer): ?>
                                <label>
                                    <input type="radio" name="answers[<?php echo $qid; ?>]" value="<?php echo $answer['id']; ?>" required>
                                    <?php echo htmlspecialchars($answer['text']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="button">Nộp bài</button>
            </form>
        </div>

        <!-- *** BƯỚC 1.2: THÊM KHU VỰC GIÁM SÁT *** -->
        <div class="monitoring-sidebar">
            <h4>Giám sát</h4>
            <div id="monitoring-container">
                <div id="status-box">Đang khởi tạo camera...</div>
                <video id="webcam" autoplay playsinline muted></video>
                <canvas id="captureCanvas" style="display: none;"></canvas>
            </div>
            <p class="warning">Vui lòng nhìn thẳng vào màn hình và không rời khỏi vị trí trong suốt quá trình làm bài.</p>
        </div>
    </div>

    <script>
        const DURATION = <?php echo $duration_seconds; ?>;
        const ATTEMPT_ID = <?php echo $attempt_id; ?>;
    </script>
    <script src="/assets/js/take_test.js"></script>
</body>
</html>

