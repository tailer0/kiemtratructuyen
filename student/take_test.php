<?php
require_once '../config.php';
// Bắt đầu session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- LOGIC TỰ ĐỘNG TẠO/TÌM ATTEMPT ID ---
if (isset($_GET['test_id']) && !isset($_GET['attempt_id'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit();
    }
    
    $test_id = $_GET['test_id'];
    $student_id = $_SESSION['user_id'];

    // 1. Resume bài chưa nộp
    $stmt = $pdo->prepare("SELECT id FROM test_attempts WHERE test_id = ? AND user_id = ? AND end_time IS NULL");
    $stmt->execute([$test_id, $student_id]);
    $existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_attempt) {
        header("Location: take_test.php?attempt_id=" . $existing_attempt['id']);
        exit();
    }

    // 2. Check đã làm chưa
    $stmt = $pdo->prepare("SELECT id FROM test_attempts WHERE test_id = ? AND user_id = ? AND end_time IS NOT NULL");
    $stmt->execute([$test_id, $student_id]);
    if ($stmt->fetch()) {
         die("Bạn đã hoàn thành bài kiểm tra này rồi. <a href='index.php'>Quay về trang chủ</a>");
    }

    // 3. Tạo lượt mới
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $user = $stmt->fetch();
    $mssv = explode('@', $user['email'])[0]; 

    $stmt = $pdo->prepare("INSERT INTO test_attempts (test_id, user_id, student_name, student_id, student_dob, start_time, ip_address) VALUES (?, ?, ?, ?, CURDATE(), NOW(), ?)");
    $stmt->execute([$test_id, $student_id, $user['name'], $mssv, $_SERVER['REMOTE_ADDR']]);
    
    $new_attempt_id = $pdo->lastInsertId();
    header("Location: take_test.php?attempt_id=" . $new_attempt_id);
    exit();
}

// Lấy attempt_id
if (!isset($_GET['attempt_id'])) die("Truy cập không hợp lệ. Thiếu attempt_id.");
$attempt_id = $_GET['attempt_id'];

// Lấy thông tin lần thi
$stmt = $pdo->prepare("SELECT * FROM test_attempts WHERE id = ?");
$stmt->execute([$attempt_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) die("Lần làm bài không tồn tại.");

// CHECK ĐÌNH CHỈ (Server-side check) - Nếu vào lại trang mà đã bị ban thì hiện luôn
if ($attempt['status'] === 'suspended') {
    die("
        <div style='height:100vh; display:flex; align-items:center; justify-content:center; background-color:#450a0a; color:white; flex-direction:column; text-align:center; font-family:sans-serif;'>
            <h1 style='font-size:3rem; margin-bottom:1rem;'>⛔ ĐÃ BỊ ĐÌNH CHỈ</h1>
            <p style='font-size:1.2rem;'>Hệ thống ghi nhận vi phạm quy chế thi quá số lần cho phép.</p>
            <p style='font-size:1.2rem; font-weight:bold; margin-top:10px;'>Điểm bài thi: 0</p>
            <a href='index.php' style='margin-top:20px; padding:10px 20px; border-radius:5px; background:white; color:#450a0a; font-weight:bold; text-decoration:none;'>Về trang chủ</a>
        </div>
    ");
}

if ($attempt['end_time'] !== null) {
    die("Bạn đã hoàn thành bài kiểm tra này rồi. <a href='/student/index.php'>Quay về trang chủ</a>");
}

// Lấy bài test
$stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
$stmt->execute([$attempt['test_id']]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) die("Bài kiểm tra không tồn tại.");

// Lấy câu hỏi
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
    <title>Làm bài: <?php echo htmlspecialchars($test['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <style>
        /* CSS Overlay & Layout */
        #start-test-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.98);
            display: flex; align-items: center; justify-content: center;
            z-index: 2000; text-align: left; padding: 20px; box-sizing: border-box;
        }
        .start-test-content {
            max-width: 600px; background: #fff; padding: 40px;
            border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        #test-content, #timer, #proctoring-container { display: none; }
        
        #proctoring-container {
            position: fixed; bottom: 20px; right: 20px; width: 200px;
            border-radius: 8px; overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 999;
        }
        #webcam { width: 100%; height: auto; display: block; }
        #status-box {
            background: #2c3e50; color: white; font-size: 13px;
            padding: 10px; text-align: center; min-height: 40px;
        }
        #captureCanvas { display: none; }
        #timer {
            position: fixed; top: 20px; right: 20px;
            background: #ffc107; color: #333; padding: 12px 18px;
            border-radius: 5px; font-size: 1.1rem; font-weight: bold; z-index: 998;
        }
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .button { background-color: #4f46e5; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .button:hover { background-color: #4338ca; }
        
        /* CSS Toast Animation */
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOutRight { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
        .toast-enter { animation: slideInRight 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
        .toast-exit { animation: fadeOutRight 0.4s ease-in forwards; }
    </style>
</head>
<body class="bg-gray-100">

    <div id="start-test-overlay">
        <div class="start-test-content">
            <h1 class="text-2xl font-bold mb-4 text-center">Sẵn sàng làm bài?</h1>
            <p class="mb-4">Hệ thống giám sát AI đang hoạt động:</p>
            <ul class="list-disc pl-5 space-y-2 mb-6">
                <li>Ở trong khung hình camera.</li>
                <li>Không chuyển tab, không copy/paste.</li>
                <li>Không sử dụng điện thoại/tài liệu.</li>
            </ul>
            <button id="start-test-button" class="button w-full py-3 text-lg font-bold">Bắt đầu thi</button>
        </div>
    </div>

    <div id="test-content">
        <div class="bg-white shadow-sm py-4 px-6 mb-6 flex justify-between items-center">
            <div class="font-bold text-lg">OnlineTest</div>
            <div><?php echo htmlspecialchars($attempt['student_name']); ?></div>
        </div>

        <div class="container pb-20">
            <h1 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-4"><?php echo htmlspecialchars($test['title']); ?></h1>
            
            <form id="test-form" action="/student/submit_test.php" method="POST">
                <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                
                <?php foreach ($questions as $q_index => $question): ?>
                    <div class="bg-white p-6 rounded-lg shadow-sm mb-6 border border-gray-200">
                        <div class="flex gap-3 mb-4">
                            <span class="bg-gray-100 font-bold px-2 py-1 rounded h-fit text-sm">Câu <?php echo $q_index + 1; ?></span>
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-800"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></h3>
                                <?php if ($question['question_type'] === 'listening' && !empty($question['audio_path'])): ?>
                                    <audio controls class="w-full max-w-md mt-3"><source src="<?php echo htmlspecialchars($question['audio_path']); ?>"></audio>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php
                        $answers_stmt->execute([$question['id']]);
                        $answers = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <div class="space-y-2 pl-10">
                        <?php foreach ($answers as $answer): 
                             $inputType = ($question['question_type'] === 'multiple_choice') ? 'checkbox' : 'radio';
                             $inputName = ($question['question_type'] === 'multiple_choice') ? "answers[{$question['id']}][]" : "answers[{$question['id']}]";
                        ?>
                            <div class="flex items-center">
                                <input type="<?php echo $inputType; ?>" name="<?php echo $inputName; ?>" value="<?php echo $answer['id']; ?>" id="ans_<?php echo $answer['id']; ?>" class="mr-2 h-4 w-4">
                                <label for="ans_<?php echo $answer['id']; ?>" class="text-gray-700 cursor-pointer"><?php echo htmlspecialchars($answer['answer_text']); ?></label>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <button type="button" onclick="document.getElementById('test-form').submit()" class="button text-lg">Nộp bài</button>
            </form>
        </div>
    </div>

    <div id="timer" class="shadow-lg">Loading...</div>
    <div id="proctoring-container">
        <video id="webcam" autoplay muted playsinline></video>
        <div id="status-box">Đang khởi tạo AI...</div>
    </div>
    <canvas id="captureCanvas"></canvas>  

    <div id="toast-container" class="fixed top-24 right-5 z-[5000] flex flex-col gap-3 pointer-events-none w-96"></div>

    <div id="alert-modal" class="fixed inset-0 bg-black/60 z-[3000] hidden flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-sm w-full mx-4 text-center border-t-4 border-red-500">
            <h3 class="text-xl font-bold mb-2">Thông báo từ Giám thị</h3>
            <p id="alert-message" class="text-slate-600 mb-6 bg-slate-50 p-3 rounded-lg italic">...</p>
            <button onclick="document.getElementById('alert-modal').classList.add('hidden')" class="bg-red-600 text-white px-6 py-2 rounded">Đã hiểu</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/face-landmarks-detection"></script>

    <script>
        const DURATION = <?php echo (int)$test['duration_minutes'] * 60; ?>;
        const ATTEMPT_ID = <?php echo $attempt_id; ?>;
    </script>
    
    <script src="/assets/js/take_test.js"></script>

    <script>
        function checkExamStatus() {
            fetch(`check_status.php?attempt_id=${ATTEMPT_ID}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        // 1. Kiểm tra bị đình chỉ (CHỈ dùng logic này để hiện màn hình đỏ)
                        if (data.exam_status === 'suspended') {
                            if (typeof window.showSuspendedScreen === 'function') {
                                window.showSuspendedScreen("Vi phạm quy chế thi quá nhiều lần.", "Đã bị đình chỉ");
                            } else {
                                // Fallback màn hình đỏ
                                document.body.innerHTML = `
                                    <div style="position:fixed; inset:0; background:#450a0a; color:white; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; z-index:99999;">
                                        <h1 style="font-size:32px; font-weight:bold; color:#fca5a5;">ĐÌNH CHỈ THI</h1>
                                        <p>Bạn đã bị đình chỉ do vi phạm quy chế.</p>
                                        <a href="index.php" style="margin-top:20px; background:white; color:#450a0a; padding:10px 20px; border-radius:5px; text-decoration:none; font-weight:bold;">Về trang chủ</a>
                                    </div>
                                `;
                            }
                            return; 
                        }
                        
                        // 2. Tin nhắn giáo viên
                        if (data.new_messages && data.new_messages.length > 0) {
                            data.new_messages.forEach(msg => {
                                document.getElementById('alert-message').textContent = msg.message;
                                document.getElementById('alert-modal').classList.remove('hidden');
                                document.getElementById('alert-modal').style.display = 'flex';
                            });
                        }

                        // 3. Đã nộp bài
                        if (data.is_finished) {
                            window.location.href = 'result.php?attempt_id=' + ATTEMPT_ID;
                        }
                    }
                })
                .catch(e => console.error("Status check failed"));
        }
        
        // Chạy kiểm tra mỗi 3 giây
        setInterval(checkExamStatus, 3000);
    </script>
</body>
</html>