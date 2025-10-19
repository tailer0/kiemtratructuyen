<?php
require_once '../config.php';
require_once ROOT_PATH . '/_partials/Header.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['attempt_id'])) {
    die("Phương thức không hợp lệ.");
}

$attempt_id = $_POST['attempt_id'];
$submitted_answers = $_POST['answers'] ?? [];

$pdo->beginTransaction();

try {
    // 1. Lưu các câu trả lời của người dùng
    foreach ($submitted_answers as $question_id => $answer_id) {
        $stmt = $pdo->prepare("INSERT INTO user_answers (attempt_id, question_id, answer_id) VALUES (?, ?, ?)");
        $stmt->execute([$attempt_id, $question_id, $answer_id]);
    }

    // 2. Tính điểm
    // Lấy test_id từ attempt_id
    $stmt = $pdo->prepare("SELECT test_id FROM test_attempts WHERE id = ?");
    $stmt->execute([$attempt_id]);
    $test_id = $stmt->fetchColumn();

    // Lấy tất cả câu hỏi và đáp án đúng của bài test
    $stmt = $pdo->prepare("
        SELECT q.id as question_id, a.id as correct_answer_id
        FROM questions q
        JOIN answers a ON q.id = a.question_id
        WHERE q.test_id = ? AND a.is_correct = 1
    ");
    $stmt->execute([$test_id]);
    $correct_answers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $score = 0;
    $total_questions = count($correct_answers);

    foreach ($submitted_answers as $question_id => $answer_id) {
        if (isset($correct_answers[$question_id]) && $correct_answers[$question_id] == $answer_id) {
            $score++;
        }
    }

    // Chuyển điểm về thang 10
    $final_score = ($total_questions > 0) ? ($score / $total_questions) * 10 : 0;

    // 3. Cập nhật điểm và thời gian kết thúc vào `test_attempts`
    $stmt = $pdo->prepare("UPDATE test_attempts SET end_time = NOW(), score = ? WHERE id = ?");
    $stmt->execute([$final_score, $attempt_id]);

    $pdo->commit();

    // 4. Chuyển hướng đến trang kết quả
    header("Location: /student/result.php?attempt_id=" . $attempt_id);
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Đã có lỗi xảy ra khi nộp bài: " . $e->getMessage());
}
