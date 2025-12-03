<?php
require_once '../config.php';
// Bắt đầu session để có thể kiểm tra đăng nhập
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- [MỚI] LOGIC TỰ ĐỘNG TẠO/TÌM ATTEMPT ID ---
// Khắc phục lỗi "Thiếu attempt_id" khi vào từ trang danh sách lớp
if (isset($_GET['test_id']) && !isset($_GET['attempt_id'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit();
    }
    
    $test_id = $_GET['test_id'];
    $student_id = $_SESSION['user_id'];

    // 1. Kiểm tra xem có bài đang làm dở không (chưa nộp) -> Resume
    $stmt = $pdo->prepare("SELECT id FROM test_attempts WHERE test_id = ? AND user_id = ? AND end_time IS NULL");
    $stmt->execute([$test_id, $student_id]);
    $existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_attempt) {
        header("Location: take_test.php?attempt_id=" . $existing_attempt['id']);
        exit();
    }

    // 2. Kiểm tra xem đã nộp bài chưa (Nếu quy định chỉ làm 1 lần)
    $stmt = $pdo->prepare("SELECT id FROM test_attempts WHERE test_id = ? AND user_id = ? AND end_time IS NOT NULL");
    $stmt->execute([$test_id, $student_id]);
    if ($stmt->fetch()) {
         // Đã làm rồi -> Chuyển sang xem kết quả hoặc thông báo
         die("Bạn đã hoàn thành bài kiểm tra này rồi. <a href='index.php'>Quay về trang chủ</a>");
    }

    // 3. Tạo lượt làm bài mới
    // Lấy thông tin sinh viên từ bảng users
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $user = $stmt->fetch();
    
    // Tạm lấy phần đầu email làm MSSV (hoặc bạn có thể sửa logic này)
    $mssv = explode('@', $user['email'])[0]; 

    $stmt = $pdo->prepare("INSERT INTO test_attempts (test_id, user_id, student_name, student_id, student_dob, start_time, ip_address) VALUES (?, ?, ?, ?, CURDATE(), NOW(), ?)");
    $stmt->execute([$test_id, $student_id, $user['name'], $mssv, $_SERVER['REMOTE_ADDR']]);
    
    $new_attempt_id = $pdo->lastInsertId();
    
    // Chuyển hướng lại chính trang này với attempt_id vừa tạo
    header("Location: take_test.php?attempt_id=" . $new_attempt_id);
    exit();
}
// -----------------------------------------------------

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
// KIỂM TRA TRẠNG THÁI ĐÌNH CHỈ NGAY KHI LOAD TRANG
if ($attempt['status'] === 'suspended') {
    die("
        <div style='font-family: sans-serif; text-align: center; margin-top: 50px; color: #b91c1c;'>
            <h1 style='font-size: 4rem; margin-bottom:0;'>⛔</h1>
            <h2 style='font-size: 2rem;'>BẠN ĐÃ BỊ ĐÌNH CHỈ THI!</h2>
            <p>Hệ thống ghi nhận vi phạm quy chế thi quá số lần cho phép.</p>
            <p><b>Điểm bài thi: 0</b></p>
            <br>
            <a href='index.php' style='text-decoration: none; background: #333; color: white; padding: 10px 20px; border-radius: 5px;'>Về trang chủ</a>
        </div>
    ");
}
// Kiểm tra xem bài thi đã nộp chưa
if ($attempt['end_time'] !== null) {
    die("Bạn đã hoàn thành bài kiểm tra này rồi. <a href='/student/index.php'>Quay về trang chủ</a>");
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
    
    <!-- Thêm Tailwind CSS để hỗ trợ các Popup mới -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <style>
        /* --- GIỮ NGUYÊN CSS CŨ CỦA BẠN --- */
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
        #status-box[data-status="warning"] { background: #e74c3c; }
        #captureCanvas { display: none; }

        #timer {
            position: fixed; top: 20px; right: 20px; /* Chỉnh lại top cho đẹp */
            background: #ffc107; color: #333; padding: 12px 18px;
            border-radius: 5px; font-size: 1.1rem; font-weight: bold; z-index: 998;
        }

        /* --- [MỚI] CSS CHO POPUP CẢNH BÁO & REALTIME --- */
        .animate-bounce-small { animation: bounce-small 0.5s; }
        @keyframes bounce-small {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        /* Ghi đè một số style của template cũ nếu cần để không vỡ layout */
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .button { background-color: #4f46e5; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .button:hover { background-color: #4338ca; }
    </style>
</head>
<body class="bg-gray-100">

    <!-- PHẦN 1: MÀN HÌNH CHỜ BẮT ĐẦU -->
    <div id="start-test-overlay">
        <div class="start-test-content">
            <h1 class="text-2xl font-bold mb-4 text-center">Sẵn sàng làm bài?</h1>
            <p class="mb-4">Bài kiểm tra này được giám sát. Vui lòng tuân thủ các quy định sau:</p>
            <ul class="list-disc pl-5 space-y-2 mb-6">
                <li>Bạn phải cấp quyền truy cập webcam và ở trong khung hình suốt thời gian làm bài.</li>
                <li>Hệ thống AI sẽ giám sát hướng nhìn, phát hiện nhiều người, hoặc không có ai.</li>
                <li class="text-amber-600 font-semibold">Không được phép chuyển tab, nhấp ra ngoài cửa sổ.</li>
                <li class="text-amber-600 font-semibold">Không được phép sao chép, dán, hoặc sử dụng chuột phải.</li>
                <li class="text-amber-600 font-semibold">Mọi hành vi vi phạm sẽ được ghi lại và gửi cho giáo viên.</li>
            </ul>
            <p class="mb-4">Bài thi sẽ bắt đầu ở chế độ <strong>Toàn màn hình</strong>. Nhấn nút bên dưới khi bạn đã sẵn sàng.</p>
            <button id="start-test-button" class="button w-full py-3 text-lg font-bold">Bắt đầu thi</button>
        </div>
    </div>

    <!-- PHẦN 2: NỘI DUNG BÀI THI (ẨN BAN ĐẦU) -->
    <div id="test-content">
        <!-- Header giả lập -->
        <div class="bg-white shadow-sm py-4 px-6 mb-6 flex justify-between items-center">
            <div class="font-bold text-lg">OnlineTest</div>
            <div><?php echo htmlspecialchars($attempt['student_name']); ?></div>
        </div>

        <div class="container pb-20">
            <h1 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-4"><?php echo htmlspecialchars($test['title']); ?></h1>
            
            <form id="test-form" action="/student/submit_test.php" method="POST">
                <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                <input type="hidden" name="test_id" value="<?php echo $test['id']; ?>">
                
                <?php foreach ($questions as $q_index => $question): ?>
                    <div class="bg-white p-6 rounded-lg shadow-sm mb-6 border border-gray-200">
                        <div class="flex gap-3 mb-4">
                            <span class="bg-gray-100 text-gray-700 font-bold px-2 py-1 rounded h-fit text-sm whitespace-nowrap">Câu <?php echo $q_index + 1; ?></span>
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-800"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></h3>
                                
                                <!-- [MỚI] Hiển thị Audio nếu có -->
                                <?php if ($question['question_type'] === 'listening' && !empty($question['audio_path'])): ?>
                                    <div class="mt-3">
                                        <audio controls class="w-full max-w-md">
                                            <source src="<?php echo htmlspecialchars($question['audio_path']); ?>" type="audio/mpeg">
                                            Trình duyệt không hỗ trợ audio.
                                        </audio>
                                    </div>
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
                            <div class="answer-option flex items-center">
                                <input type="<?php echo $inputType; ?>" 
                                       name="<?php echo $inputName; ?>" 
                                       value="<?php echo $answer['id']; ?>" 
                                       id="answer_<?php echo $answer['id']; ?>"
                                       class="mr-2 h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                <label for="answer_<?php echo $answer['id']; ?>" class="text-gray-700 cursor-pointer select-none">
                                    <?php echo htmlspecialchars($answer['answer_text']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button type="button" onclick="submitExam()" class="button text-lg w-full md:w-auto">Nộp bài</button>
            </form>
        </div>
    </div>

    <!-- PHẦN 3: CÁC YẾU TỐ GIÁM SÁT -->
    
    <!-- Đồng hồ đếm ngược -->
    <div id="timer" class="shadow-lg">Loading...</div>

    <!-- Khung giám sát webcam (Giữ nguyên ID) -->
    <div id="proctoring-container">
        <video id="webcam" autoplay muted playsinline></video>
        <div id="status-box">Đang khởi tạo AI...</div>
    </div>

    <!-- Canvas ẩn để chụp ảnh -->
    <canvas id="captureCanvas"></canvas>  

    <!-- [MỚI] POPUP CẢNH BÁO TỪ GIÁO VIÊN -->
    <div id="alert-modal" class="fixed inset-0 bg-black/60 z-[3000] hidden flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-sm w-full mx-4 text-center animate-bounce-small border-t-4 border-red-500">
            <div class="w-16 h-16 bg-red-50 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                <i class="fa-solid fa-bell"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Thông báo từ Giám thị</h3>
            <p id="alert-message" class="text-slate-600 mb-6 bg-slate-50 p-3 rounded-lg italic">...</p>
            <button onclick="closeAlert()" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 w-full font-medium transition-colors">
                Đã hiểu, tôi sẽ tiếp tục làm bài
            </button>
        </div>
    </div>

    <!-- Nạp các thư viện AI -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-core"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-webgl"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-converter"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/face-landmarks-detection"></script>

    <!-- Truyền biến PHP sang JavaScript -->
    <script>
        const DURATION = <?php echo (int)$test['duration_minutes'] * 60; ?>; // Chuyển phút sang giây
        const ATTEMPT_ID = <?php echo $attempt_id; ?>;
    </script>
    
    <!-- Nạp file JS giám sát cũ của bạn -->
    <script src="/assets/js/take_test.js"></script>

    <!-- [MỚI] Script bổ sung: Đồng bộ & Realtime Polling -->
    <script>
        function checkExamStatus() {
            fetch(`check_status.php?attempt_id=${ATTEMPT_ID}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        // 1. Kiểm tra bị đình chỉ
                        if (data.exam_status === 'suspended') {
                            // Xóa nội dung trang để chặn thao tác
                            document.body.innerHTML = '';
                            document.body.style.background = '#450a0a';
                            
                            // Hiển thị thông báo và chuyển trang
                            alert("⛔ BẠN ĐÃ BỊ ĐÌNH CHỈ THI!\n\nLý do: Vi phạm quy chế thi quá nhiều lần.\nĐiểm bài thi: 0\n\nHệ thống sẽ đưa bạn về trang chủ.");
                            window.location.href = 'index.php';
                            return;
                        }
                        // 2. Kiểm tra đã nộp bài (do hết giờ hoặc GV thu bài)
                        if (data.is_finished) {
                            window.location.href = 'result.php?attempt_id=' + ATTEMPT_ID;
                        }
                    }
                })
                .catch(e => console.error("Status check failed:", e));
        }
        
        // Chạy kiểm tra mỗi 3 giây
        setInterval(checkExamStatus, 3000);

        // 1. Logic Đồng hồ đếm ngược & Nút Bắt đầu (Cập nhật lại để đồng bộ với overlay cũ)
        let timeLeft = DURATION;
        const timerEl = document.getElementById('timer');

        // Gán sự kiện cho nút bắt đầu (nếu file js cũ chưa gán hoặc để ghi đè logic hiển thị)
        document.getElementById('start-test-button').addEventListener('click', function() {
            // Logic hiển thị giao diện (giả sử file js cũ cũng xử lý phần này, ta thêm phần timer)
            document.getElementById('start-test-overlay').style.display = 'none';
            document.getElementById('test-content').style.display = 'block';
            document.getElementById('timer').style.display = 'block';
            document.getElementById('proctoring-container').style.display = 'block';

            // Bắt đầu đếm ngược
            const countdown = setInterval(() => {
                const hours = Math.floor(timeLeft / 3600);
                const minutes = Math.floor((timeLeft % 3600) / 60);
                const seconds = timeLeft % 60;
                
                let timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                if (hours > 0) timeString = `${hours.toString().padStart(2, '0')}:${timeString}`;
                
                timerEl.textContent = timeString;
                
                // Cảnh báo sắp hết giờ
                if (timeLeft <= 300) { 
                    timerEl.style.color = '#e74c3c';
                    timerEl.style.animation = 'pulse 1s infinite'; // Cần thêm keyframes pulse nếu chưa có
                }

                if (--timeLeft < 0) {
                    clearInterval(countdown);
                    alert('Hết giờ làm bài!');
                    submitExam();
                }
            }, 1000);
            
            // Bắt đầu Polling nhận tin nhắn
            startRealtimePolling();
        });

        function submitExam() {
            document.getElementById('test-form').submit();
        }

        // 2. Realtime Polling (Nhận tin nhắn & Lệnh dừng từ Giáo viên)
        function startRealtimePolling() {
            setInterval(() => {
                fetch(`api_test.php?action=check_status&attempt_id=${ATTEMPT_ID}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // A. Bị giáo viên dừng thi
                            if (data.force_stop) {
                                alert('BÀI THI ĐÃ BỊ ĐÌNH CHỈ BỞI GIÁM THỊ!');
                                submitExam();
                            }
                            // B. Có tin nhắn mới
                            if (data.new_messages && data.new_messages.length > 0) {
                                data.new_messages.forEach(msg => {
                                    showAlert(msg.message);
                                });
                            }
                        }
                    })
                    .catch(e => console.error("Connection error", e));
            }, 3000); // Kiểm tra mỗi 3 giây
        }

        // 3. Xử lý Popup Cảnh báo
        function showAlert(msg) {
            document.getElementById('alert-message').textContent = msg;
            document.getElementById('alert-modal').classList.remove('hidden');
            document.getElementById('alert-modal').style.display = 'flex'; // Đảm bảo hiện nếu class hidden không đủ mạnh
        }
        function closeAlert() {
            document.getElementById('alert-modal').classList.add('hidden');
            document.getElementById('alert-modal').style.display = 'none';
        }
    </script>
    <script src="../js/exam_security.js"></script>

<script>
    // Lấy ID lượt thi từ PHP (giả sử bạn có biến $attempt_id trong file php)
    const currentAttemptId = <?php echo $attempt_id; ?>;
    
    // Kích hoạt giám sát ngay khi trang tải xong
    document.addEventListener('DOMContentLoaded', function() {
        startExamMonitor(currentAttemptId);
    });
    // HÀM KIỂM TRA TRẠNG THÁI LIÊN TỤC
    function checkSuspensionStatus() {
            // Gọi đến file check_status.php mà tôi đã cung cấp trước đó
            fetch(`check_status.php?attempt_id=${currentAttemptId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Nếu phát hiện bị đình chỉ
                        if (data.exam_status === 'suspended') {
                            // Xóa giao diện làm bài
                            document.body.innerHTML = `
                                <div style="height:100vh; display:flex; align-items:center; justify-content:center; background-color:#450a0a; color:white; flex-direction:column; text-align:center;">
                                    <h1 style="font-size:3rem; margin-bottom:1rem;">⛔ ĐÃ BỊ ĐÌNH CHỈ</h1>
                                    <p style="font-size:1.2rem;">Bạn đã vi phạm quy chế thi quá số lần cho phép.</p>
                                    <p style="font-size:1.2rem; font-weight:bold; margin-top:10px;">Điểm bài thi: 0</p>
                                    <button onclick="window.location.href='index.php'" style="margin-top:20px; padding:10px 20px; border-radius:5px; background:white; color:#450a0a; font-weight:bold; cursor:pointer;">Về trang chủ</button>
                                </div>
                            `;
                            // Dừng kiểm tra
                            clearInterval(suspensionInterval);
                        }
                    }
                })
                .catch(err => console.log('Check status error (ignore if offline):', err));
        }

        // Chạy mỗi 3 giây
        const suspensionInterval = setInterval(checkSuspensionStatus, 3000);
</script>
</body>
</html>