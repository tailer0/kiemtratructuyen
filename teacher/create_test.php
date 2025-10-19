<?php
require_once '../config.php';

// Bảo vệ trang
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $duration = $_POST['duration'] ?? 0; // Thêm biến thời gian
    $questions = $_POST['questions'] ?? [];
    $teacher_id = $_SESSION['user_id'];

    // Thêm kiểm tra cho duration
    if (empty($title) || empty($questions) || !is_numeric($duration) || $duration <= 0) {
        $error_message = "Vui lòng nhập tiêu đề, thời gian làm bài hợp lệ (> 0) và ít nhất một câu hỏi.";
    } else {
        $invite_code = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6);
        $pdo->beginTransaction();
        try {
            // Cập nhật câu lệnh INSERT để thêm duration_minutes
            $stmt = $pdo->prepare("INSERT INTO tests (teacher_id, title, invite_code, status, duration_minutes) VALUES (?, ?, ?, 'draft', ?)");
            $stmt->execute([$teacher_id, $title, $invite_code, $duration]);
            $test_id = $pdo->lastInsertId();

            // Insert questions and answers
            foreach ($questions as $q_key => $question) {
                if (empty($question['text'])) continue;

                $question_text = $question['text'];
                $stmt = $pdo->prepare("INSERT INTO questions (test_id, question_text) VALUES (?, ?)");
                $stmt->execute([$test_id, $question_text]);
                $question_id = $pdo->lastInsertId();
                
                $correct_answer_key = $question['correct'] ?? null;

                foreach ($question['answers'] as $a_key => $answer_text) {
                    if (empty($answer_text)) continue;
                    
                    $is_correct = ($a_key == $correct_answer_key) ? 1 : 0;

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
        .question-block { background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; }
        .answer-option { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .answer-option input[type="text"] { flex-grow: 1; }
        .remove-btn { background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <?php include '../_partials/header.php'; ?>
    <div class="container">
        <h1>Tạo bài kiểm tra mới</h1>
        <?php if ($error_message): ?>
            <p style="color: red;"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="title">Tiêu đề bài kiểm tra:</label>
            <input type="text" id="title" name="title" required>

            <!-- Thêm ô nhập thời gian làm bài -->
            <label for="duration">Thời gian làm bài (phút):</label>
            <input type="number" id="duration" name="duration" min="1" required>

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
                questionBlock.innerHTML = `
                    <label><b>Câu hỏi ${qIndex + 1}:</b></label>
                    <input type="text" name="questions[${qIndex}][text]" placeholder="Nhập nội dung câu hỏi" required>
                    <button type="button" class="remove-btn remove-question">Xóa câu hỏi</button>
                    <p>Các lựa chọn trả lời (chọn đáp án đúng):</p>
                    <div class="answers-container"></div>
                    <button type="button" class="button add-answer">Thêm lựa chọn</button>
                `;
                questionsContainer.appendChild(questionBlock);
                
                // Tự động thêm 2 lựa chọn trả lời ban đầu
                addAnswer(questionBlock.querySelector('.answers-container'), qIndex);
                addAnswer(questionBlock.querySelector('.answers-container'), qIndex);
            }

            function addAnswer(container, qIdx) {
                const answerIndex = container.children.length;
                const answerOption = document.createElement('div');
                answerOption.className = 'answer-option';
                answerOption.innerHTML = `
                    <input type="radio" name="questions[${qIdx}][correct]" value="${answerIndex}" required>
                    <input type="text" name="questions[${qIdx}][answers][${answerIndex}]" placeholder="Nội dung lựa chọn ${answerIndex + 1}" required>
                    <button type="button" class="remove-btn remove-answer">Xóa</button>
                `;
                container.appendChild(answerOption);
            }

            addQuestionBtn.addEventListener('click', addQuestion);

            questionsContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('add-answer')) {
                    const answersContainer = e.target.previousElementSibling;
                    const qIndex = parseInt(answersContainer.closest('.question-block').querySelector('input[type=radio]').name.match(/\[(\d+)\]/)[1]);
                    addAnswer(answersContainer, qIndex);
                }
                if (e.target.classList.contains('remove-question')) {
                    e.target.closest('.question-block').remove();
                }
                if (e.target.classList.contains('remove-answer')) {
                    e.target.closest('.answer-option').remove();
                }
            });

            // Thêm 1 câu hỏi khi tải trang
            addQuestion();
        });
    </script>
</body>
</html>

