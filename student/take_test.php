<?php
require_once '../config.php';
// Bắt đầu session để có thể kiểm tra đăng nhập
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Lấy attempt_id từ URL
if (!isset($_GET['attempt_id'])) {
    die("Truy cập không hợp lệ. Thiếu attempt_id.");
}
$attempt_id = $_GET['attempt_id'];

// Lấy thông tin về lần làm bài này
$stmt = $pdo->prepare("SELECT * FROM test_attempts WHERE id = ?");
$stmt->execute([$attempt_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    die("Lần làm bài không tồn tại.");
}

// Kiểm tra xem bài thi đã nộp chưa
if ($attempt['end_time'] !== null) {
    die("Bạn đã hoàn thành bài kiểm tra này rồi. <a href='/index.php'>Quay về trang chủ</a>");
}

// Lấy thông tin bài test (để lấy thời gian)
$stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
$stmt->execute([$attempt['test_id']]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    die("Bài kiểm tra không tồn tại.");
}

// Lấy danh sách câu hỏi và câu trả lời
$stmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? ORDER BY id");
$stmt->execute([$test['id']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$answers_stmt = $pdo->prepare("SELECT * FROM answers WHERE question_id = ? ORDER BY id");

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đang làm bài kiểm tra: <?php echo htmlspecialchars($test['title']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* CSS cho luồng bắt đầu thi */
        #start-test-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            text-align: left;
            padding: 20px;
            box-sizing: border-box;
        }
        .start-test-content {
            max-width: 600px;
            background: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .start-test-content h1 {
            margin-top: 0;
            text-align: center;
        }
        .start-test-content ul {
            list-style-type: none;
            padding-left: 0;
        }
        .start-test-content li {
            position: relative;
            padding-left: 30px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .start-test-content li::before {
            content: '✔';
            color: #28a745;
            font-weight: bold;
            position: absolute;
            left: 0;
            top: 0;
        }
        .start-test-content li.warning::before {
            content: '⚠';
            color: #ffc107;
        }
        #start-test-button {
            width: 100%;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
            margin-top: 20px;
        }

        /* Ẩn nội dung bài thi ban đầu */
        #test-content, #timer, #proctoring-container {
            display: none;
        }

        /* CSS cho phần giám sát */
        #proctoring-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 200px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 999;
        }
        #webcam {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 8px 8px 0 0;
        }
        #status-box {
            background: #2c3e50;
            color: white;
            font-size: 13px;
            padding: 10px;
            text-align: center;
            min-height: 40px;
            box-sizing: border-box;
            line-height: 1.4;
        }
        #status-box[data-status="warning"] {
            background: #e74c3c;
        }
        #captureCanvas {
            display: none; /* Ẩn canvas dùng để chụp ảnh */
        }

        /* CSS cho đồng hồ */
        #timer {
            position: fixed;
            top: 80px; /* Dưới header */
            right: 20px;
            background: #ffc107;
            color: #333;
            padding: 12px 18px;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: bold;
            z-index: 998;
        }
    </style>
</head>
<body>

    <!-- PHẦN 1: MÀN HÌNH CHỜ BẮT ĐẦU -->
    <div id="start-test-overlay">
        <div class="start-test-content">
            <h1>Sẵn sàng làm bài?</h1>
            <p>Bài kiểm tra này được giám sát. Vui lòng tuân thủ các quy định sau:</p>
            <ul>
                <li>Bạn phải cấp quyền truy cập webcam và ở trong khung hình suốt thời gian làm bài.</li>
                <li>Hệ thống AI sẽ giám sát hướng nhìn, phát hiện nhiều người, hoặc không có ai.</li>
                <li class="warning">Không được phép chuyển tab, nhấp ra ngoài cửa sổ, hoặc dùng màn hình thứ 2.</li>
                <li class="warning">Không được phép sao chép, dán, hoặc sử dụng chuột phải.</li>
                <li class="warning">Mọi hành vi vi phạm sẽ được ghi lại và gửi cho giáo viên của bạn.</li>
            </ul>
            <p>Bài thi sẽ bắt đầu ở chế độ <strong>Toàn màn hình</strong>. Nhấn nút bên dưới khi bạn đã sẵn sàng.</p>
            <button id="start-test-button" class="button">Bắt đầu thi</button>
        </div>
    </div>

    <!-- PHẦN 2: NỘI DUNG BÀI THI (ẨN BAN ĐẦU) -->
    <div id="test-content">
        <!-- Header (để hiển thị tên người dùng nếu có) -->
        <?php include_once ROOT_PATH . '/_partials/header.php'; ?>

        <div class="container">
            <h1><?php echo htmlspecialchars($test['title']); ?></h1>
            <form id="test-form" action="/student/submit_test.php" method="POST">
                
                <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                
                <?php foreach ($questions as $q_index => $question): ?>
                    <div class="question-block">
                        <h3>Câu <?php echo $q_index + 1; ?>: <?php echo htmlspecialchars($question['question_text']); ?></h3>
                        
                        <?php
                        $answers_stmt->execute([$question['id']]);
                        $answers = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <?php foreach ($answers as $answer): ?>
                            <div class="answer-option">
                                <input type="radio" 
                                       name="answers[<?php echo $question['id']; ?>]" 
                                       value="<?php echo $answer['id']; ?>" 
                                       id="answer_<?php echo $answer['id']; ?>">
                                <label for="answer_<?php echo $answer['id']; ?>">
                                    <?php echo htmlspecialchars($answer['answer_text']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="button">Nộp bài</button>
            </form>
        </div>
    </div>

    <!-- PHẦN 3: CÁC YẾU TỐ GIÁM SÁT -->
    
    <!-- Đồng hồ đếm ngược -->
    <div id="timer">Đang tải...</div>

    <!-- Khung giám sát webcam -->
    <div id="proctoring-container">
        <video id="webcam" autoplay muted playsinline></video>
        <div id="status-box">Đang khởi tạo...</div>
    </div>

    <!-- Canvas ẩn để chụp ảnh -->
    <canvas id="captureCanvas"></canvas>  

    <!-- Nạp các thư viện AI -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-core"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-webgl"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/face-landmarks-detection"></script>

    <!-- Truyền biến PHP sang JavaScript -->
    <script>
        const DURATION = <?php echo (int)$test['duration_minutes'] * 60; ?>; // Chuyển phút sang giây
        const ATTEMPT_ID = <?php echo $attempt_id; ?>;
    </script>
    
    <!-- Nạp file JS giám sát (File bạn đang mở) -->
    <script src="/assets/js/take_test.js"></script>
    
</body>
</html>

