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

        // 2. Lấy thông tin bài thi (Thang điểm max_score)
        // Mặc định là 10 nếu chưa set
        $stmt = $pdo->prepare("SELECT max_score FROM tests WHERE id = ?");
        $stmt->execute([$test_id]);
        $test_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $test_scale_score = ($test_info && $test_info['max_score'] > 0) ? floatval($test_info['max_score']) : 10;

        // 3. Lấy danh sách câu hỏi kèm ĐIỂM SỐ (points)
        $stmt = $pdo->prepare("SELECT id, question_type, points FROM questions WHERE test_id = ?");
        $stmt->execute([$test_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Chuẩn bị dữ liệu tính điểm
        $question_map = [];
        $total_possible_raw_points = 0; // Tổng điểm thô tối đa (Ví dụ: Câu 1 (2đ) + Câu 2 (3đ) = 5đ)

        foreach ($questions as $q) {
            $q_points = floatval($q['points'] > 0 ? $q['points'] : 1); // Mặc định 1 điểm nếu lỗi
            $question_map[$q['id']] = [
                'type' => $q['question_type'],
                'points' => $q_points
            ];
            $total_possible_raw_points += $q_points;
        }

        $user_earned_raw_points = 0; // Tổng điểm thô học sinh đạt được

        // 4. Duyệt qua từng câu trả lời của học sinh
        foreach ($submitted_answers as $question_id => $user_ans) {
            if (!isset($question_map[$question_id])) continue; // Bỏ qua nếu ID câu hỏi sai

            $q_data = $question_map[$question_id];
            $type = $q_data['type'];
            $points = $q_data['points'];
            $is_answer_correct = false;

            // XỬ LÝ LƯU VÀO DB & KIỂM TRA ĐÚNG SAI
            
            // TRƯỜNG HỢP 1: Câu hỏi nhiều lựa chọn (Checkbox)
            if (is_array($user_ans)) {
                // Lưu đáp án
                foreach ($user_ans as $ans_id) {
                    $stmt = $pdo->prepare("INSERT INTO user_answers (attempt_id, question_id, answer_id) VALUES (?, ?, ?)");
                    $stmt->execute([$attempt_id, $question_id, intval($ans_id)]);
                }

                // Logic chấm điểm: Phải chọn ĐÚNG và ĐỦ tất cả đáp án đúng mới được điểm
                $stmt = $pdo->prepare("SELECT id FROM answers WHERE question_id = ? AND is_correct = 1");
                $stmt->execute([$question_id]);
                $correct_ids = $stmt->fetchAll(PDO::FETCH_COLUMN); // Mảng ID đúng từ DB

                $user_selected_ids = array_map('intval', $user_ans);
                sort($user_selected_ids);
                sort($correct_ids);

                if ($user_selected_ids === $correct_ids) {
                    $is_answer_correct = true;
                }

            } 
            // TRƯỜNG HỢP 2: Câu hỏi 1 lựa chọn (Radio)
            else {
                $ans_id = intval($user_ans);
                
                // Lưu đáp án
                $stmt = $pdo->prepare("INSERT INTO user_answers (attempt_id, question_id, answer_id) VALUES (?, ?, ?)");
                $stmt->execute([$attempt_id, $question_id, $ans_id]);

                // Kiểm tra đúng sai
                $stmt = $pdo->prepare("SELECT is_correct FROM answers WHERE id = ?");
                $stmt->execute([$ans_id]);
                $is_correct_db = $stmt->fetchColumn();

                if ($is_correct_db) {
                    $is_answer_correct = true;
                }
            }

            // Cộng điểm nếu đúng
            if ($is_answer_correct) {
                $user_earned_raw_points += $points;
            }
        }

        // 5. TÍNH ĐIỂM SỐ CUỐI CÙNG (QUY ĐỔI RA THANG ĐIỂM)
        // Công thức: (Điểm đạt được / Tổng điểm tối đa) * Thang điểm bài thi
        if ($total_possible_raw_points > 0) {
            $final_score = ($user_earned_raw_points / $total_possible_raw_points) * $test_scale_score;
        } else {
            $final_score = 0;
        }

        // Làm tròn 2 chữ số thập phân
        $final_score = round($final_score, 2);

        // 6. Cập nhật điểm số vào DB
        $stmt = $pdo->prepare("UPDATE test_attempts SET score = ? WHERE id = ?");
        $stmt->execute([$final_score, $attempt_id]);

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