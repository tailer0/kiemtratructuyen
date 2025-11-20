<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

// Kiểm tra quyền truy cập
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

$test_id = isset($_GET['test_id']) ? intval($_GET['test_id']) : 0;
$teacher_id = $_SESSION['user_id'];
$error_message = '';

// --- 1. LẤY DỮ LIỆU BÀI THI ---
$stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? AND teacher_id = ?");
$stmt->execute([$test_id, $teacher_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    die("Bài kiểm tra không tồn tại hoặc bạn không có quyền sửa.");
}

$class_id = $test['class_id'];

// --- 2. XỬ LÝ UPDATE KHI SUBMIT FORM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_update_test'])) {
    $title = $_POST['title'] ?? '';
    $duration = $_POST['duration'] ?? 0;
    $questions = $_POST['questions'] ?? [];
    
    if (empty($title) || empty($questions) || !is_numeric($duration) || $duration <= 0) {
        $error_message = "Vui lòng nhập đầy đủ thông tin: Tiêu đề, Thời gian và ít nhất 1 câu hỏi.";
    } else {
        try {
            $pdo->beginTransaction();

            // A. Cập nhật thông tin cơ bản của bài thi
            $stmt = $pdo->prepare("UPDATE tests SET title = ?, duration_minutes = ? WHERE id = ?");
            $stmt->execute([$title, $duration, $test_id]);

            // B. Xử lý câu hỏi: XÓA CŨ - THÊM MỚI
            // Lưu ý: Cách này giúp đồng bộ ID dễ dàng nhưng sẽ làm mất liên kết với kết quả thi cũ (nếu có).
            // Nếu cần giữ lịch sử thi, bạn nên dùng Soft Delete hoặc cập nhật ID câu hỏi thay vì xóa.
            
            // 1. Xóa đáp án cũ
            $stmt = $pdo->prepare("DELETE a FROM answers a JOIN questions q ON a.question_id = q.id WHERE q.test_id = ?");
            $stmt->execute([$test_id]);
            
            // 2. Xóa câu hỏi cũ
            $stmt = $pdo->prepare("DELETE FROM questions WHERE test_id = ?");
            $stmt->execute([$test_id]);

            // C. Thêm lại câu hỏi mới từ form
            foreach ($questions as $q_key => $question) {
                // Bỏ qua câu hỏi rỗng
                if (!isset($question['text']) || trim($question['text']) === '') continue;

                $question_text = trim($question['text']);
                $question_type = $question['type'] ?? 'single_choice';
                $audio_path = null;

                // Xử lý File Audio
                if ($question_type === 'listening') {
                    // Trường hợp 1: Có file upload mới -> Dùng file mới
                    if (isset($_FILES['questions']['name'][$q_key]['audio']) && $_FILES['questions']['error'][$q_key]['audio'] === UPLOAD_ERR_OK) {
                         $audioTmpName = $_FILES['questions']['tmp_name'][$q_key]['audio'];
                         $audioOriginalName = $_FILES['questions']['name'][$q_key]['audio'];
                         $fileExtension = strtolower(pathinfo($audioOriginalName, PATHINFO_EXTENSION));
                         
                         // Tạo tên file unique
                         $audioFileName = 'audio_' . $test_id . '_' . $q_key . '_' . uniqid() . '.' . $fileExtension;
                         $audioUploadDir = ROOT_PATH . '/uploads/audio/';
                         
                         if (!is_dir($audioUploadDir)) mkdir($audioUploadDir, 0777, true);
                         
                         if (move_uploaded_file($audioTmpName, $audioUploadDir . $audioFileName)) {
                             $audio_path = '/uploads/audio/' . $audioFileName;
                         }
                    } 
                    // Trường hợp 2: Không có file mới nhưng có đường dẫn cũ -> Giữ nguyên
                    elseif (!empty($question['existing_audio_path'])) {
                        $audio_path = $question['existing_audio_path'];
                    }
                }

                // Insert câu hỏi vào DB
                $stmt = $pdo->prepare("INSERT INTO questions (test_id, question_text, question_type, audio_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$test_id, $question_text, $question_type, $audio_path]);
                $question_id = $pdo->lastInsertId();

                // Xử lý đáp án đúng
                $correct_answers = $question['correct'] ?? [];
                // Đảm bảo luôn là mảng để xử lý đồng nhất (kể cả radio button)
                if (!is_array($correct_answers)) $correct_answers = [$correct_answers];
                $correct_answers = array_map('strval', $correct_answers); // Chuyển về string để so sánh

                // Insert các đáp án
                if (isset($question['answers']) && is_array($question['answers'])) {
                    foreach ($question['answers'] as $a_key => $answer_text) {
                        $trimmed_answer = trim((string)$answer_text);
                        if (empty($trimmed_answer)) continue;
                        
                        // Kiểm tra xem index này có nằm trong mảng đáp án đúng không
                        $is_correct = in_array((string)$a_key, $correct_answers, true) ? 1 : 0;
                        
                        $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                        $stmt->execute([$question_id, $trimmed_answer, $is_correct]);
                    }
                }
            }

            $pdo->commit();
            
            $_SESSION['success_msg'] = "Cập nhật bài kiểm tra thành công!";
            header("Location: view_class.php?id=" . $class_id);
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Lỗi cập nhật: " . $e->getMessage();
        }
    }
}

// --- 3. CHUẨN BỊ DỮ LIỆU ĐỂ HIỂN THỊ LÊN FORM (JS) ---
$stmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? ORDER BY id ASC");
$stmt->execute([$test_id]);
$db_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$questions_data = [];
foreach ($db_questions as $q) {
    $stmt_a = $pdo->prepare("SELECT * FROM answers WHERE question_id = ? ORDER BY id ASC");
    $stmt_a->execute([$q['id']]);
    $answers = $stmt_a->fetchAll(PDO::FETCH_ASSOC);
    
    $answers_text = [];
    $correct_indices = [];
    
    foreach ($answers as $idx => $ans) {
        $answers_text[] = $ans['answer_text'];
        if ($ans['is_correct']) {
            $correct_indices[] = (string)$idx;
        }
    }
    
    // Cấu trúc dữ liệu khớp với logic của Javascript
    $questions_data[] = [
        'text' => $q['question_text'],
        'type' => $q['question_type'],
        'audio_required' => !empty($q['audio_path']),
        'existing_audio_path' => $q['audio_path'], // Quan trọng: để giữ file cũ nếu không upload mới
        'answers' => $answers_text,
        'correct' => ($q['question_type'] === 'single_choice') ? ($correct_indices[0] ?? null) : $correct_indices
    ];
}

// Chuyển mảng PHP sang JSON an toàn
$initial_data_json = json_encode([
    'title' => $test['title'],
    'duration' => $test['duration_minutes'],
    'questions' => $questions_data
], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa bài kiểm tra: <?php echo htmlspecialchars($test['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .question-block { @apply bg-white border border-gray-200 rounded-lg p-5 mb-5 relative shadow-sm transition-all; }
        .question-block:hover { @apply shadow-md border-indigo-200; }
    </style>
</head>
<body class="bg-gray-50 text-slate-800 min-h-screen p-4 md:p-8">

    <div class="max-w-5xl mx-auto bg-white rounded-xl shadow-lg overflow-hidden">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 p-6 flex justify-between items-center sticky top-0 z-10 bg-opacity-95 backdrop-blur-sm">
            <div>
                <a href="view_class.php?id=<?php echo $class_id; ?>" class="text-slate-500 hover:text-indigo-600 font-medium flex items-center gap-2 mb-1 transition-colors">
                    <i class="fa-solid fa-arrow-left"></i> Hủy & Quay lại
                </a>
                <h1 class="text-2xl font-bold text-slate-800">Sửa bài kiểm tra</h1>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <!-- Thông báo lỗi -->
            <?php if ($error_message): ?>
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-lg p-4 mb-6 flex items-center">
                    <i class="fa-solid fa-circle-exclamation mr-3 text-xl"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Cảnh báo -->
            <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-lg p-4 mb-6 text-sm flex items-start">
                <i class="fa-solid fa-triangle-exclamation mr-2 mt-0.5"></i>
                <div>
                    <strong>Lưu ý quan trọng:</strong><br>
                    Việc lưu thay đổi sẽ xóa toàn bộ câu hỏi cũ và tạo lại câu hỏi mới. <br>
                    Nếu học sinh đã làm bài, dữ liệu chi tiết từng câu trả lời có thể bị mất liên kết (nhưng điểm tổng vẫn còn).
                </div>
            </div>

            <!-- Form Sửa -->
            <form id="edit-test-form" method="POST" enctype="multipart/form-data">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tên bài kiểm tra <span class="text-red-500">*</span></label>
                        <input type="text" id="title" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Thời gian (phút) <span class="text-red-500">*</span></label>
                        <input type="number" id="duration" name="duration" min="1" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>

                <div id="questions-container" class="space-y-6">
                    <!-- Các câu hỏi sẽ được Javascript render vào đây -->
                </div>

                <div class="mt-6 flex justify-between items-center pt-6 border-t border-gray-200">
                    <button type="button" id="add-question" class="text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-5 py-2.5 rounded-lg font-medium transition-colors border border-indigo-200">
                        <i class="fa-solid fa-plus-circle mr-2"></i>Thêm câu hỏi
                    </button>
                    <button type="submit" name="submit_update_test" class="bg-indigo-600 text-white px-8 py-3 rounded-lg font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all">
                        <i class="fa-solid fa-floppy-disk mr-2"></i>Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Thẻ chứa dữ liệu JSON từ PHP -->
    <div id="initial-data-holder" class="hidden" data-json="<?php echo htmlspecialchars($initial_data_json, ENT_QUOTES, 'UTF-8'); ?>"></div>

    <!-- Javascript xử lý giao diện động -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const questionsContainer = document.getElementById('questions-container');
            const addQuestionBtn = document.getElementById('add-question');
            const titleInput = document.getElementById('title');
            const durationInput = document.getElementById('duration');
            let questionIndex = 0;

            // Hàm thêm 1 khối câu hỏi vào giao diện
            function addQuestion(questionData = null) {
                const qIndex = questionIndex++;
                const questionBlock = document.createElement('div');
                questionBlock.className = 'question-block'; 
                questionBlock.dataset.index = qIndex;

                const questionText = questionData?.text ?? '';
                const questionType = questionData?.type ?? 'single_choice';
                const existingAudioPath = questionData?.existing_audio_path ?? '';

                questionBlock.innerHTML = `
                    <div class="flex justify-between items-start mb-3">
                        <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2 py-1 rounded uppercase tracking-wider">Câu hỏi <span class="q-number">${qIndex + 1}</span></span>
                        <button type="button" class="remove-question text-gray-400 hover:text-red-500 transition-colors"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <div class="mb-4">
                        <textarea name="questions[${qIndex}][text]" required rows="2" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-slate-700" placeholder="Nhập nội dung câu hỏi...">${questionText}</textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase">Loại câu hỏi</label>
                            <select name="questions[${qIndex}][type]" class="question-type-select w-full p-2 border border-gray-300 rounded-md text-sm bg-white">
                                <option value="single_choice" ${questionType === 'single_choice' ? 'selected' : ''}>Trắc nghiệm (1 đáp án)</option>
                                <option value="multiple_choice" ${questionType === 'multiple_choice' ? 'selected' : ''}>Trắc nghiệm (Nhiều đáp án)</option>
                                <option value="listening" ${questionType === 'listening' ? 'selected' : ''}>Nghe hiểu (Audio)</option>
                            </select>
                        </div>
                        <div class="audio-upload ${questionType === 'listening' ? '' : 'hidden'}">
                            <label class="block text-xs font-semibold text-slate-500 mb-1 uppercase">File âm thanh</label>
                            <input type="file" name="questions[${qIndex}][audio]" accept=".mp3,.wav,.ogg,.m4a" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 mb-1">
                            
                            <!-- Input ẩn để giữ đường dẫn file cũ -->
                            <input type="hidden" name="questions[${qIndex}][existing_audio_path]" value="${existingAudioPath}">
                            
                            ${existingAudioPath ? `<div class="text-xs text-green-600 bg-green-50 p-1 rounded inline-block"><i class="fa-solid fa-check"></i> Đã có audio (Tải mới để thay thế)</div>` : ''}
                        </div>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-100">
                        <div class="flex justify-between items-center mb-2">
                            <label class="text-xs font-bold text-slate-500 uppercase">Các đáp án</label>
                            <button type="button" class="add-answer text-xs text-indigo-600 hover:text-indigo-800 font-medium"><i class="fa-solid fa-plus"></i> Thêm lựa chọn</button>
                        </div>
                        <div class="answers-container space-y-2"></div>
                    </div>
                `;
                questionsContainer.appendChild(questionBlock);

                const answersContainer = questionBlock.querySelector('.answers-container');
                const typeSelect = questionBlock.querySelector('.question-type-select');

                // Logic thêm các đáp án vào câu hỏi
                if (questionData?.answers && Array.isArray(questionData.answers) && questionData.answers.length > 0) {
                    // Chuẩn hóa mảng đáp án đúng thành mảng chuỗi để dễ so sánh
                    let correctAnswers = [];
                    if (Array.isArray(questionData.correct)) {
                         correctAnswers = questionData.correct.map(String);
                    } else if (questionData.correct !== null && questionData.correct !== undefined) {
                         correctAnswers = [String(questionData.correct)];
                    }

                    questionData.answers.forEach((ansText, index) => {
                        const isCorrect = correctAnswers.includes(String(index));
                        addAnswer(answersContainer, qIndex, questionType, ansText, isCorrect);
                    });
                } else {
                    // Nếu tạo mới thì mặc định 4 đáp án rỗng
                    addAnswer(answersContainer, qIndex, questionType);
                    addAnswer(answersContainer, qIndex, questionType);
                    addAnswer(answersContainer, qIndex, questionType);
                    addAnswer(answersContainer, qIndex, questionType);
                }

                // Gắn sự kiện thay đổi loại câu hỏi (Hiện/Ẩn Audio, đổi Radio <-> Checkbox)
                if (typeSelect) {
                    typeSelect.addEventListener('change', handleQuestionTypeChange);
                }
            }

            function addAnswer(container, qIdx, questionType, answerText = '', isChecked = false) {
                if (!container) return;
                const answerIndex = container.children.length;
                const answerOption = document.createElement('div');
                answerOption.className = 'answer-option flex items-center gap-2 group';

                // Loại input: Radio cho trắc nghiệm 1 đáp án, Checkbox cho nhiều đáp án
                const inputType = (questionType === 'multiple_choice') ? 'checkbox' : 'radio';
                const inputName = (questionType === 'multiple_choice') ? `questions[${qIdx}][correct][]` : `questions[${qIdx}][correct]`;

                answerOption.innerHTML = `
                    <div class="flex items-center h-10">
                        <input type="${inputType}" name="${inputName}" value="${answerIndex}" ${isChecked ? 'checked' : ''} class="correct-check w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500 cursor-pointer">
                    </div>
                    <input type="text" name="questions[${qIdx}][answers][${answerIndex}]" placeholder="Đáp án ${String.fromCharCode(65 + answerIndex)}" value="${answerText}" required class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded hover:border-gray-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                    <button type="button" class="remove-answer text-gray-300 hover:text-red-500 p-2 opacity-0 group-hover:opacity-100 transition-all"><i class="fa-solid fa-xmark"></i></button>
                `;
                container.appendChild(answerOption);
            }

            // --- XỬ LÝ SỰ KIỆN (Click button Thêm/Xóa) ---
            addQuestionBtn.addEventListener('click', () => {
                addQuestion();
                updateQuestionNumbers();
            });

            questionsContainer.addEventListener('click', function(e) {
                const target = e.target.closest('button');
                if (!target) return;

                // Thêm đáp án
                if (target.classList.contains('add-answer')) {
                    const block = target.closest('.question-block');
                    const type = block.querySelector('.question-type-select').value;
                    addAnswer(block.querySelector('.answers-container'), block.dataset.index, type);
                } 
                // Xóa câu hỏi
                else if (target.classList.contains('remove-question')) {
                    if (confirm('Xóa câu hỏi này?')) {
                        target.closest('.question-block').remove();
                        updateQuestionNumbers();
                    }
                } 
                // Xóa đáp án
                else if (target.classList.contains('remove-answer')) {
                    const row = target.closest('.answer-option');
                    const container = row.parentElement;
                    if (container.children.length > 2) {
                        row.remove();
                        updateAnswerPlaceholders(container);
                    } else {
                        alert('Cần tối thiểu 2 đáp án.');
                    }
                }
            });

            function handleQuestionTypeChange(e) {
                const block = e.target.closest('.question-block');
                const newType = e.target.value;
                
                // Xử lý phần upload audio
                const audioDiv = block.querySelector('.audio-upload');
                if (newType === 'listening') audioDiv.classList.remove('hidden');
                else audioDiv.classList.add('hidden');

                // Đổi loại input của đáp án (radio <-> checkbox)
                const container = block.querySelector('.answers-container');
                const inputs = container.querySelectorAll('.correct-check');
                const newInputType = (newType === 'multiple_choice') ? 'checkbox' : 'radio';
                
                inputs.forEach(input => {
                    input.type = newInputType;
                    input.checked = false; // Reset để tránh lỗi logic
                });
            }

            function updateQuestionNumbers() {
                questionsContainer.querySelectorAll('.question-block').forEach((block, idx) => {
                    block.querySelector('.q-number').textContent = idx + 1;
                    // Trong thực tế nếu muốn hoàn hảo, cần cập nhật lại cả name attribute index
                    // Nhưng ở đây ta chỉ cần UI đẹp, PHP sẽ nhận theo thứ tự mảng gửi lên.
                });
            }
            
            function updateAnswerPlaceholders(container) {
                container.querySelectorAll('.answer-option input[type="text"]').forEach((input, idx) => {
                    input.placeholder = `Đáp án ${String.fromCharCode(65 + idx)}`;
                });
                container.querySelectorAll('.correct-check').forEach((input, idx) => {
                    input.value = idx;
                });
            }

            // --- KHỞI TẠO: TẢI DỮ LIỆU TỪ PHP ---
            const dataHolder = document.getElementById('initial-data-holder');
            if (dataHolder) {
                try {
                    const jsonData = dataHolder.getAttribute('data-json');
                    const initialData = JSON.parse(jsonData);
                    
                    if (initialData) {
                        // Fill thông tin chung
                        titleInput.value = initialData.title || '';
                        durationInput.value = initialData.duration || 45;
                        
                        // Render danh sách câu hỏi cũ
                        if (initialData.questions && initialData.questions.length > 0) {
                            initialData.questions.forEach(q => addQuestion(q));
                        } else {
                            addQuestion();
                        }
                    }
                } catch (e) {
                    console.error("Lỗi parse dữ liệu bài thi cũ:", e);
                    addQuestion(); 
                }
            } else {
                addQuestion();
            }
        });
    </script>
</body>
</html>