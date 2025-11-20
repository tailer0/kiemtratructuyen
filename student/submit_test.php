<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
    header('Location: /index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempt_id = $_POST['attempt_id'] ?? 0;
    $test_id = $_POST['test_id'] ?? 0;
    $submitted_answers = $_POST['answers'] ?? [];

    if (!$attempt_id || !$test_id) {
        die("Dữ liệu nộp bài không hợp lệ.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Cập nhật thời gian nộp bài
        $stmt = $pdo->prepare("UPDATE test_attempts SET end_time = NOW() WHERE id = ?");
        $stmt->execute([$attempt_id]);

        // 2. Lưu đáp án và tính điểm
        $total_score = 0;
        $question_count = 0;
        
        // Lấy danh sách tất cả câu hỏi để biết tổng số câu và đáp án đúng
        $stmt = $pdo->prepare("SELECT id, question_type FROM questions WHERE test_id = ?");
        $stmt->execute([$test_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Map question ID -> type
        $q_types = [];
        foreach ($questions as $q) {
            $q_types[$q['id']] = $q['question_type'];
        }
        
        // Điểm mỗi câu (Giả sử thang điểm 10 chia đều)
        $score_per_question = count($questions) > 0 ? (10 / count($questions)) : 0;

        foreach ($submitted_answers as $question_id => $user_ans) {
            // Kiểm tra loại câu hỏi
            $type = $q_types[$question_id] ?? 'single_choice';

            // TRƯỜNG HỢP 1: Câu hỏi nhiều lựa chọn (Checkbox) -> $user_ans là MẢNG
            if (is_array($user_ans)) {
                // Lưu từng đáp án đã chọn vào DB
                foreach ($user_ans as $ans_id) {
                    $stmt = $pdo->prepare("INSERT INTO user_answers (attempt_id, question_id, answer_id) VALUES (?, ?, ?)");
                    $stmt->execute([$attempt_id, $question_id, intval($ans_id)]);
                }

                // Tính điểm cho câu hỏi nhiều lựa chọn (Logic đơn giản: Phải chọn ĐÚNG HẾT mới được điểm)
                // Lấy các đáp án đúng của câu hỏi này từ DB
                $stmt = $pdo->prepare("SELECT id FROM answers WHERE question_id = ? AND is_correct = 1");
                $stmt->execute([$question_id]);
                $correct_ids = $stmt->fetchAll(PDO::FETCH_COLUMN); // Mảng ID đúng [1, 5]

                // So sánh mảng người dùng chọn và mảng đáp án đúng
                // (Cần sort để so sánh chính xác bất kể thứ tự)
                $user_selected_ids = array_map('intval', $user_ans);
                sort($user_selected_ids);
                sort($correct_ids);

                if ($user_selected_ids === $correct_ids) {
                    $total_score += $score_per_question;
                }

            } 
            // TRƯỜNG HỢP 2: Câu hỏi 1 lựa chọn (Radio) -> $user_ans là SỐ
            else {
                $ans_id = intval($user_ans);
                
                // Lưu vào DB
                $stmt = $pdo->prepare("INSERT INTO user_answers (attempt_id, question_id, answer_id) VALUES (?, ?, ?)");
                $stmt->execute([$attempt_id, $question_id, $ans_id]);

                // Kiểm tra đúng sai
                $stmt = $pdo->prepare("SELECT is_correct FROM answers WHERE id = ?");
                $stmt->execute([$ans_id]);
                $is_correct = $stmt->fetchColumn();

                if ($is_correct) {
                    $total_score += $score_per_question;
                }
            }
        }

        // 3. Cập nhật điểm số cuối cùng
        $stmt = $pdo->prepare("UPDATE test_attempts SET score = ? WHERE id = ?");
        $stmt->execute([$total_score, $attempt_id]);

        $pdo->commit();
        
        // Chuyển hướng xem kết quả
        header("Location: result.php?attempt_id=" . $attempt_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Đã có lỗi xảy ra khi nộp bài: " . $e->getMessage());
    }
} else {
    header('Location: index.php');
    exit();
}
?>