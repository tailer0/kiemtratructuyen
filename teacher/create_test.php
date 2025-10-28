<?php
require_once '../config.php';
require_once ROOT_PATH . '/vendor/autoload.php'; // Include Composer Autoload

use PhpOffice\PhpWord\IOFactory; // Sử dụng thư viện PhpWord

// Bảo vệ trang
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

$error_message = '';
$success_message = '';

// --- PHẦN XỬ LÝ UPLOAD FILE WORD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['word_file']) && $_FILES['word_file']['error'] === UPLOAD_ERR_OK) {
    $uploadedFile = $_FILES['word_file'];
    $allowedExtensions = ['docx'];
    $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExtension, $allowedExtensions)) {
        $error_message = "Chỉ chấp nhận file .docx.";
    } else {
        try {
            // === LOGIC PHÂN TÍCH FILE WORD (CẦN TÙY CHỈNH) ===
            // Đây là phần phức tạp nhất và phụ thuộc vào định dạng file Word của bạn
            $phpWord = IOFactory::load($uploadedFile['tmp_name']);
            $test_data = ['title' => 'Nhập từ Word', 'duration' => 60, 'questions' => []]; // Dữ liệu mặc định

            // Ví dụ: Lặp qua các section/element trong file Word để trích xuất
            // Giả sử:
            // - Câu hỏi bắt đầu bằng số (1., 2., ...)
            // - Lựa chọn bắt đầu bằng chữ cái (A., B., C., ...)
            // - Đáp án đúng có dấu * ở cuối (A. Đáp án 1 *)
            // - Câu hỏi nghe có tag [AUDIO] ở đầu
            // - Câu hỏi nhiều đáp án có tag [MULTIPLE] ở đầu

            $current_question = null;
            $question_index = 0;

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text = trim($element->getText());
                        if (empty($text)) continue;

                        // Phát hiện câu hỏi mới (ví dụ: bắt đầu bằng số và dấu chấm)
                        if (preg_match('/^(\d+)\.\s*(.*)/', $text, $matches)) {
                            if ($current_question) { // Lưu câu hỏi trước đó (nếu có)
                                $test_data['questions'][] = $current_question;
                            }
                            $question_text_raw = trim($matches[2]);
                            $question_type = 'single_choice'; // Mặc định
                            $audio_required = false;

                            // Kiểm tra loại câu hỏi đặc biệt
                            if (strpos(strtoupper($question_text_raw), '[AUDIO]') === 0) {
                                $question_type = 'listening';
                                $audio_required = true;
                                $question_text_raw = trim(substr($question_text_raw, 7)); // Bỏ tag [AUDIO]
                            } elseif (strpos(strtoupper($question_text_raw), '[MULTIPLE]') === 0) {
                                $question_type = 'multiple_choice';
                                $question_text_raw = trim(substr($question_text_raw, 10)); // Bỏ tag [MULTIPLE]
                            }

                            $current_question = [
                                'text' => $question_text_raw,
                                'type' => $question_type, // Lưu loại câu hỏi
                                'audio_required' => $audio_required, // Cần file audio không?
                                'answers' => [],
                                'correct' => [] // Có thể là mảng cho multiple_choice
                            ];
                            $question_index++;

                        }
                        // Phát hiện lựa chọn (ví dụ: bắt đầu bằng chữ cái và dấu chấm)
                        elseif ($current_question && preg_match('/^([A-Z])\.\s*(.*)/i', $text, $matches)) {
                             $answer_text_raw = trim($matches[2]);
                             $is_correct = false;
                             if (substr($answer_text_raw, -1) === '*') {
                                 $answer_text_raw = trim(substr($answer_text_raw, 0, -1));
                                 $is_correct = true;
                             }
                             $answer_key = count($current_question['answers']); // Index của câu trả lời
                             $current_question['answers'][$answer_key] = $answer_text_raw;
                             if ($is_correct) {
                                 if ($current_question['type'] === 'multiple_choice') {
                                     $current_question['correct'][] = $answer_key; // Thêm vào mảng correct
                                 } else {
                                     $current_question['correct'] = $answer_key; // Ghi đè cho single_choice
                                 }
                             }
                        }
                    }
                }
            }
             if ($current_question) { // Lưu câu hỏi cuối cùng
                $test_data['questions'][] = $current_question;
            }

            // TODO: Hiển thị $test_data này lên form để giáo viên xác nhận và tải audio (nếu cần)
            // Hoặc có thể lưu trực tiếp nếu định dạng Word đủ chuẩn
             $success_message = "Đã đọc thành công file Word. Tìm thấy " . count($test_data['questions']) . " câu hỏi. (Chức năng lưu chưa hoàn thiện)";
            // Lưu ý: Phần này cần được phát triển thêm để xử lý $test_data và lưu vào DB


        } catch (Exception $e) {
            $error_message = "Lỗi khi đọc file Word: " . $e->getMessage();
        }
    }
}
// --- PHẦN XỬ LÝ TẠO BÀI THI THỦ CÔNG ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $duration = $_POST['duration'] ?? 0;
    $questions = $_POST['questions'] ?? [];
    $teacher_id = $_SESSION['user_id'];

    if (empty($title) || empty($questions) || !is_numeric($duration) || $duration <= 0) {
        $error_message = "Vui lòng nhập tiêu đề, thời gian làm bài hợp lệ (> 0) và ít nhất một câu hỏi.";
    } else {
        $invite_code = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO tests (teacher_id, title, invite_code, status, duration_minutes) VALUES (?, ?, ?, 'draft', ?)");
            $stmt->execute([$teacher_id, $title, $invite_code, $duration]);
            $test_id = $pdo->lastInsertId();

            // Insert questions and answers
            foreach ($questions as $q_key => $question) {
                if (empty($question['text'])) continue;

                $question_text = $question['text'];
                $question_type = $question['type'] ?? 'single_choice';
                $audio_path = null;

                // Xử lý upload file audio nếu là câu hỏi nghe
                if ($question_type === 'listening' && isset($_FILES['questions']['name'][$q_key]['audio']) && $_FILES['questions']['error'][$q_key]['audio'] === UPLOAD_ERR_OK) {
                    $audioFile = $_FILES['questions']['tmp_name'][$q_key]['audio'];
                    $audioFileName = 'audio_' . $test_id . '_' . $q_key . '_' . uniqid() . '.' . strtolower(pathinfo($_FILES['questions']['name'][$q_key]['audio'], PATHINFO_EXTENSION));
                    $audioUploadDir = ROOT_PATH . '/uploads/audio/'; // Tạo thư mục uploads/audio/ nếu chưa có
                    if (!is_dir($audioUploadDir)) {
                        mkdir($audioUploadDir, 0777, true);
                    }
                    if (move_uploaded_file($audioFile, $audioUploadDir . $audioFileName)) {
                        $audio_path = '/uploads/audio/' . $audioFileName; // Lưu đường dẫn tương đối
                    } else {
                        throw new Exception("Không thể lưu file audio cho câu hỏi " . ($q_key + 1));
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO questions (test_id, question_text, question_type, audio_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$test_id, $question_text, $question_type, $audio_path]);
                $question_id = $pdo->lastInsertId();

                $correct_answers = isset($question['correct']) ? (array)$question['correct'] : []; // Luôn chuyển thành mảng

                foreach ($question['answers'] as $a_key => $answer_text) {
                    if (empty($answer_text)) continue;

                    // Kiểm tra xem index $a_key có nằm trong mảng $correct_answers không
                    $is_correct = in_array((string)$a_key, $correct_answers) ? 1 : 0;

                    $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->execute([$question_id, $answer_text, $is_correct]);
                }
            }
            $pdo->commit();
            header("Location: index.php"); // Chuyển về dashboard của giáo viên
            exit();

        } catch(Exception $e) {
            $pdo->rollBack();
            $error_message = "Lỗi khi tạo bài kiểm tra: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tạo bài kiểm tra mới</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .question-block { background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; position: relative; }
        .answer-option { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .answer-option input[type="text"] { flex-grow: 1; }
        .remove-btn { background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; font-size: 0.8em; }
        .remove-question { position: absolute; top: 10px; right: 10px; }
        .question-type-selector { margin-bottom: 10px; }
        .audio-upload { display: none; margin-top: 10px; } /* Ẩn ô upload audio mặc định */
        .upload-section { margin-bottom: 2rem; padding: 1rem; border: 1px dashed #ccc; border-radius: 5px; background-color: #f0f7ff; }
    </style>
</head>
<body>
    <?php include '../_partials/header.php'; ?>
    <div class="container">
        <h1>Tạo bài kiểm tra mới</h1>
        <?php if ($error_message): ?>
            <p class="alert error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
         <?php if ($success_message): ?>
            <p class="alert success"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>

        <!-- Phần Upload File Word -->
        <div class="upload-section">
            <h2>Hoặc Nhập từ File Word (.docx)</h2>
            <form method="POST" enctype="multipart/form-data">
                <p>Tải lên file .docx chứa đề thi theo định dạng quy ước.</p>
                <div class="form-group">
                    <label for="word_file">Chọn file Word:</label>
                    <input type="file" id="word_file" name="word_file" accept=".docx" required>
                </div>
                <button type="submit" class="button">Đọc File Word</button>
                 <small>(Lưu ý: Chức năng này đang trong quá trình phát triển, cần định dạng file chuẩn.)</small>
            </form>
        </div>
        <hr>

        <!-- Phần Tạo thủ công -->
        <h2>Tạo thủ công</h2>
        <form method="POST" enctype="multipart/form-data"> <!-- Thêm enctype cho upload audio -->
            <div class="form-group">
                <label for="title">Tiêu đề bài kiểm tra:</label>
                <input type="text" id="title" name="title" required>
            </div>

            <div class="form-group">
                <label for="duration">Thời gian làm bài (phút):</label>
                <input type="number" id="duration" name="duration" min="1" required>
            </div>

            <div id="questions-container">
                <!-- Các câu hỏi sẽ được thêm vào đây bằng JavaScript -->
            </div>

            <button type="button" id="add-question" class="button">Thêm câu hỏi</button>
            <hr>
            <button type="submit" class="button">Lưu bài kiểm tra</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const questionsContainer = document.getElementById('questions-container');
            const addQuestionBtn = document.getElementById('add-question');
            let questionIndex = 0;

            function addQuestion() {
                const qIndex = questionIndex++;
                const questionBlock = document.createElement('div');
                questionBlock.className = 'question-block';
                questionBlock.dataset.index = qIndex; // Lưu index để dễ tham chiếu
                questionBlock.innerHTML = `
                    <button type="button" class="remove-btn remove-question">Xóa câu hỏi</button>
                    <div class="form-group">
                        <label><b>Câu hỏi ${qIndex + 1}:</b></label>
                        <textarea name="questions[${qIndex}][text]" placeholder="Nhập nội dung câu hỏi" required rows="3"></textarea>
                    </div>
                    <div class="form-group question-type-selector">
                        <label>Loại câu hỏi:</label>
                        <select name="questions[${qIndex}][type]" class="question-type-select">
                            <option value="single_choice" selected>Trắc nghiệm (1 đáp án đúng)</option>
                            <option value="multiple_choice">Trắc nghiệm (Nhiều đáp án đúng)</option>
                            <option value="listening">Câu hỏi nghe</option>
                        </select>
                    </div>
                    <div class="form-group audio-upload">
                        <label>Tải file audio (mp3, wav):</label>
                        <input type="file" name="questions[${qIndex}][audio]" accept=".mp3,.wav">
                    </div>
                    <p>Các lựa chọn trả lời (chọn đáp án đúng):</p>
                    <div class="answers-container"></div>
                    <button type="button" class="button add-answer" style="font-size: 0.9em; padding: 8px 15px;">Thêm lựa chọn</button>
                `;
                questionsContainer.appendChild(questionBlock);

                // Tự động thêm 2 lựa chọn trả lời ban đầu
                addAnswer(questionBlock.querySelector('.answers-container'), qIndex, 'single_choice');
                addAnswer(questionBlock.querySelector('.answers-container'), qIndex, 'single_choice');

                // Gắn listener cho select loại câu hỏi
                const typeSelect = questionBlock.querySelector('.question-type-select');
                typeSelect.addEventListener('change', handleQuestionTypeChange);
                 handleQuestionTypeChange({ target: typeSelect }); // Gọi lần đầu để ẩn/hiện audio
            }

            function addAnswer(container, qIdx, questionType) {
                const answerIndex = container.children.length;
                const answerOption = document.createElement('div');
                answerOption.className = 'answer-option';

                // Thay đổi input type dựa vào loại câu hỏi
                const inputType = (questionType === 'multiple_choice') ? 'checkbox' : 'radio';
                // Name cho checkbox cần có [] để PHP nhận mảng
                const inputName = (questionType === 'multiple_choice') ? `questions[${qIdx}][correct][]` : `questions[${qIdx}][correct]`;

                answerOption.innerHTML = `
                    <input type="${inputType}" name="${inputName}" value="${answerIndex}" ${inputType === 'radio' && answerIndex === 0 ? 'required' : ''}>
                    <input type="text" name="questions[${qIdx}][answers][${answerIndex}]" placeholder="Nội dung lựa chọn ${answerIndex + 1}" required>
                    <button type="button" class="remove-btn remove-answer">Xóa</button>
                `;
                container.appendChild(answerOption);

                // Cập nhật thuộc tính required cho radio buttons
                if (inputType === 'radio') {
                     updateRadioRequired(container);
                }
            }

            // Hàm cập nhật thuộc tính required cho radio buttons của một câu hỏi
            function updateRadioRequired(answersContainer) {
                 const radios = answersContainer.querySelectorAll('input[type="radio"]');
                 radios.forEach((radio, index) => {
                     radio.required = (index === 0); // Chỉ radio đầu tiên là required ban đầu
                 });
                 // Nếu chỉ còn 1 radio, nó phải là required
                 if(radios.length === 1) radios[0].required = true;
            }


            // Hàm xử lý khi thay đổi loại câu hỏi
            function handleQuestionTypeChange(event) {
                const selectElement = event.target;
                const questionBlock = selectElement.closest('.question-block');
                const answersContainer = questionBlock.querySelector('.answers-container');
                const audioUploadDiv = questionBlock.querySelector('.audio-upload');
                const qIndex = parseInt(questionBlock.dataset.index);
                const newType = selectElement.value;

                // Hiện/ẩn ô upload audio
                audioUploadDiv.style.display = (newType === 'listening') ? 'block' : 'none';

                // Cập nhật loại input (radio/checkbox) cho các câu trả lời hiện có
                const currentAnswers = answersContainer.querySelectorAll('.answer-option');
                const answerTexts = []; // Lưu lại text đang có
                 currentAnswers.forEach(ans => {
                     const input = ans.querySelector('input[type=text]');
                     if (input) answerTexts.push(input.value);
                 });

                 // Xóa các lựa chọn cũ
                 answersContainer.innerHTML = '';

                 // Tạo lại các lựa chọn với input type mới
                 answerTexts.forEach((text, index) => {
                      addAnswer(answersContainer, qIndex, newType);
                      // Điền lại text cũ
                      const newTextInput = answersContainer.lastChild.querySelector('input[type=text]');
                      if (newTextInput) newTextInput.value = text;
                 });
                 // Đảm bảo có ít nhất 2 lựa chọn
                 if(answerTexts.length < 2) {
                     addAnswer(answersContainer, qIndex, newType);
                     if(answerTexts.length < 1) addAnswer(answersContainer, qIndex, newType);
                 }
            }

            addQuestionBtn.addEventListener('click', addQuestion);

            questionsContainer.addEventListener('click', function(e) {
                const target = e.target;

                if (target.classList.contains('add-answer')) {
                    const questionBlock = target.closest('.question-block');
                    const answersContainer = questionBlock.querySelector('.answers-container');
                    const qIndex = parseInt(questionBlock.dataset.index);
                    const questionType = questionBlock.querySelector('.question-type-select').value;
                    addAnswer(answersContainer, qIndex, questionType);
                }
                else if (target.classList.contains('remove-question')) {
                    target.closest('.question-block').remove();
                    // Cập nhật lại chỉ số câu hỏi nếu cần (phức tạp hơn, tạm bỏ qua)
                }
                else if (target.classList.contains('remove-answer')) {
                     const answerOption = target.closest('.answer-option');
                     const answersContainer = answerOption.parentElement;
                     answerOption.remove();
                     // Cập nhật lại thuộc tính required cho radio buttons sau khi xóa
                     if (answersContainer.querySelector('input[type="radio"]')) {
                         updateRadioRequired(answersContainer);
                     }
                }
                // Không cần listener riêng cho select vì đã gắn ở hàm addQuestion
            });

            // Thêm 1 câu hỏi khi tải trang
            addQuestion();
        });
    </script>
</body>
</html>

