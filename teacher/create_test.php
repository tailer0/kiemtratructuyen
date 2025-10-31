<?php
// === PHẢI GỌI session_start() NGAY ĐẦU ===
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';
require_once ROOT_PATH . '/vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\AbstractContainer;

// Bảo vệ trang
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

$error_message = '';
$success_message = '';
$imported_data_json_for_js = 'null';
$debug_log = []; // Mảng lưu log debug

// --- KIỂM TRA DỮ LIỆU/LỖI IMPORT TỪ SESSION ---
if (isset($_SESSION['imported_test_json'])) {
    $imported_data_json_for_js = $_SESSION['imported_test_json'];
    unset($_SESSION['imported_test_json']);

    if (isset($_SESSION['import_success_message'])) {
        $success_message = $_SESSION['import_success_message'];
        unset($_SESSION['import_success_message']);
    }
    
    if (isset($_SESSION['debug_log'])) {
        $debug_log = $_SESSION['debug_log'];
        unset($_SESSION['debug_log']);
    }

} elseif (isset($_SESSION['import_error_message'])) {
    $error_message = $_SESSION['import_error_message'];
    unset($_SESSION['import_error_message']);
    
    if (isset($_SESSION['debug_log'])) {
        $debug_log = $_SESSION['debug_log'];
        unset($_SESSION['debug_log']);
    }
}

// --- PHÂN BIỆT LOẠI POST REQUEST ---
$is_word_upload_request = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_word_file']));
$is_manual_save_request = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_manual_test']));

// --- HÀM PHỤ TRỢ ĐỌC VĂN BẢN TỪ ELEMENT ---
function extractTextFromElement($element) {
    $text = '';
    
    if ($element instanceof Text) {
        return $element->getText();
    }
    
    if ($element instanceof TextRun) {
        // Sử dụng hàm getText() có sẵn của TextRun, nó đã ghép các Text bên trong
        return $element->getText();
    }
    
    if ($element instanceof AbstractContainer) {
        try {
            $reflection = new ReflectionClass($element);
            if ($reflection->hasMethod('getElements')) {
                foreach ($element->getElements() as $innerElement) {
                    $text .= extractTextFromElement($innerElement);
                }
            }
        } catch (ReflectionException $e) {
            error_log("ReflectionException: " . $e->getMessage());
        }
    }
    
    return $text;
}

// --- HÀM PHÂN TÍCH FILE WORD (NÂNG CẤP HOÀN TOÀN) ---
function parseWordFile($filePath, &$debug_log) {
    $debug_log[] = "========== BẮT ĐẦU PHÂN TÍCH FILE ==========";
    $phpWord = IOFactory::load($filePath);
    $originalFileName = pathinfo($filePath, PATHINFO_FILENAME);
    
    $test_data = [
        'title' => 'Nhập từ Word - ' . $originalFileName,
        'duration' => 60,
        'questions' => []
    ];
    
    $current_question = null;
    $line_number = 0;
    $pending_tag = ''; // Lưu tag tạm thời khi gặp tag rời
    
    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            $line_number++;
            $raw_text = trim(extractTextFromElement($element));
            
            if (empty($raw_text)) continue;
            
            // CHUẨN HÓA: Xóa ký tự \ (backslash) - XỬ LÝ CẢ \\\ (3 backslash)
            $raw_text = preg_replace('/\\\\+/', ' ', $raw_text); // Xóa 1 hoặc nhiều backslash
            $raw_text = preg_replace('/\s+/', ' ', $raw_text); // Xóa khoảng trắng thừa
            $raw_text = trim($raw_text);
            
            // TÁCH DÒNG NẾU NÓ CHỨA NHIỀU CÂU HỎI
            // Pattern: Tách tại "[TAG] 1. " hoặc "1. " (có khoảng trắng sau dấu chấm)
            $split_texts = preg_split('/(?=(?:\[[^\]]+\]\s*)?\d+\.\s+)/', $raw_text, -1, PREG_SPLIT_NO_EMPTY);
            
            $chunk_index = 0;
            foreach ($split_texts as $text_chunk) {
                $text = trim($text_chunk);
                if (empty($text)) continue;
                
                $chunk_index++;
                
                // PHÁT HIỆN TAG RỜI (chỉ chứa [TAG]) - Lưu lại để áp dụng cho câu hỏi tiếp theo
                if (preg_match('/^\[([^\]]+)\]$/s', $text, $tag_match)) {
                    $pending_tag = strtoupper(trim($tag_match[1]));
                    $debug_log[] = "Dòng {$line_number} (chunk {$chunk_index}): [LƯU TAG] '{$pending_tag}' cho câu hỏi tiếp theo";
                    continue;
                }
                
                $debug_log[] = "Dòng {$line_number} (chunk {$chunk_index}): " . substr($text, 0, 80) . (strlen($text) > 80 ? '...' : '');
                
                // ===== PHÁT HIỆN CÂU HỎI =====
                // Pattern 1: [TAG] 1. Text hoặc 1. [TAG] Text hoặc 1. Text
                if (preg_match('/^(?:\[([^\]]+)\]\s*)?(\d+)\.\s*(?:\[([^\]]+)\]\s*)?(.+?)(?=\s+[A-Z]\.\s+|$)/s', $text, $q_matches)) {
                    // Lưu câu hỏi trước đó
                    if ($current_question !== null && !empty($current_question['answers'])) {
                        if (validateQuestion($current_question)) {
                            $test_data['questions'][] = $current_question;
                        }
                    }
                    
                    // XÁC ĐỊNH TAG (ưu tiên: tag từ pending > tag đầu tiên > tag thứ hai)
                    $tag = '';
                    if (!empty($pending_tag)) {
                        $tag = $pending_tag;
                        $pending_tag = ''; // Reset sau khi dùng
                    } elseif (!empty(trim($q_matches[1] ?? ''))) {
                        $tag = strtoupper(trim($q_matches[1]));
                    } elseif (!empty(trim($q_matches[3] ?? ''))) {
                        $tag = strtoupper(trim($q_matches[3]));
                    }
                    
                    $question_number = $q_matches[2];
                    $question_text_raw = trim($q_matches[4]);
                    
                    // TÁCH câu hỏi ra khỏi các đáp án (nếu có)
                    // Nếu text có dạng "Text A. Answer B. Answer" thì chỉ lấy phần trước "A."
                    $question_text = $question_text_raw;
                    if (preg_match('/^(.+?)\s+[A-Z]\.\s+/s', $question_text_raw, $split_match)) {
                        $question_text = trim($split_match[1]);
                    }
                    
                    // XÁC ĐỊNH LOẠI CÂU HỎI
                    $question_type = 'single_choice';
                    $audio_required = false;
                    
                    if ($tag === 'AUDIO' || $tag === 'LISTENING') {
                        $question_type = 'listening';
                        $audio_required = true;
                    } elseif ($tag === 'MULTIPLE' || $tag === 'MULTI') {
                        $question_type = 'multiple_choice';
                    }
                    
                    $debug_log[] = "  ✅ PHÁT HIỆN CÂU HỎI #{$question_number}: Loại={$question_type}, Tag='{$tag}'";
                    $debug_log[] = "     Nội dung: " . substr($question_text, 0, 60) . (strlen($question_text) > 60 ? '...' : '');
                    
                    $current_question = [
                        'text' => $question_text,
                        'type' => $question_type,
                        'audio_required' => $audio_required,
                        'answers' => [],
                        'correct' => []
                    ];
                    
                    // TÁCH CÁC ĐÁP ÁN (nếu có trên cùng dòng)
                    if (preg_match_all('/([A-Z])\.\s*(.+?)(?=\s+[A-Z]\.\s+|$)/s', $text, $a_matches, PREG_SET_ORDER)) {
                        foreach ($a_matches as $a_match) {
                            $answer_letter = strtoupper($a_match[1]);
                            $raw_answer_text = trim($a_match[2]);
                            
                            // Chuẩn hóa: xóa backslash và khoảng trắng thừa
                            $raw_answer_text = preg_replace('/\s*\\\\\s*/', ' ', $raw_answer_text);
                            $raw_answer_text = preg_replace('/\s+/', ' ', $raw_answer_text);
                            $raw_answer_text = trim($raw_answer_text);
                            
                            $is_correct = false;
                            // Kiểm tra dấu * (đánh dấu đáp án đúng)
                            if (strpos($raw_answer_text, '*') !== false) {
                                $is_correct = true;
                                $answer_text = trim(str_replace('*', '', $raw_answer_text), " \t\n\r\0\x0B.,?!;");
                            } else {
                                $answer_text = rtrim($raw_answer_text, " \t\n\r\0\x0B.,?!;");
                            }
                            
                            if (empty($answer_text)) continue;
                            
                            $answer_key = count($current_question['answers']);
                            $current_question['answers'][$answer_key] = $answer_text;
                            
                            $debug_log[] = "  ➤ Đáp án {$answer_letter}: " . substr($answer_text, 0, 40) . ($is_correct ? ' ★ ĐÚNG' : '');
                            
                            if ($is_correct) {
                                $current_question['correct'][] = (string)$answer_key;
                                
                                if ($current_question['type'] === 'single_choice') {
                                    $current_question['correct'] = [(string)$answer_key];
                                }
                            }
                        }
                    }
                }
                // Pattern 2: Đáp án riêng lẻ (A. Text)
                elseif ($current_question !== null && preg_match('/^([A-Z])\.\s*(.+)/is', $text, $matches)) {
                    $answer_letter = strtoupper($matches[1]);
                    $raw_answer_text = trim($matches[2]);
                    
                    $is_correct = false;
                    if (strpos($raw_answer_text, '*') !== false) {
                        $is_correct = true;
                        $answer_text = trim(str_replace('*', '', $raw_answer_text), " \t\n\r\0\x0B.,?!;");
                    } else {
                        $answer_text = rtrim($raw_answer_text, " \t\n\r\0\x0B.,?!;");
                    }
                    
                    if (empty($answer_text)) continue;
                    
                    $answer_key = count($current_question['answers']);
                    $current_question['answers'][$answer_key] = $answer_text;
                    
                    $debug_log[] = "  ➤ Đáp án {$answer_letter}: " . substr($answer_text, 0, 40) . ($is_correct ? ' ★ ĐÚNG' : '');
                    
                    if ($is_correct) {
                        $current_question['correct'][] = (string)$answer_key;
                        
                        if ($current_question['type'] === 'single_choice') {
                            $current_question['correct'] = [(string)$answer_key];
                        }
                    }
                }
                // Pattern 3: Nối tiếp văn bản (cho câu hỏi hoặc đáp án)
                elseif ($current_question !== null) {
                    if (empty($current_question['answers'])) {
                        $current_question['text'] .= "\n" . $text;
                        $debug_log[] = "     ... (nối vào câu hỏi): " . substr($text, 0, 60);
                    } else {
                        $last_answer_key = count($current_question['answers']) - 1;
                        if (isset($current_question['answers'][$last_answer_key])) {
                            $current_question['answers'][$last_answer_key] .= "\n" . $text;
                            $debug_log[] = "     ... (nối vào đáp án cuối): " . substr($text, 0, 60);
                        }
                    }
                }
            } // end foreach $split_texts
        } // end foreach $element
    } // end foreach $section
    
    // Lưu câu hỏi cuối cùng
    if ($current_question !== null && !empty($current_question['answers'])) {
        if (validateQuestion($current_question)) {
            $test_data['questions'][] = $current_question;
        }
    }
    
    $debug_log[] = "========== HOÀN TẤT: Tìm thấy " . count($test_data['questions']) . " câu hỏi hợp lệ ==========";
    
    return $test_data;
}

// --- HÀM KIỂM TRA TÍNH HỢP LỆ ---
function validateQuestion($question) {
    if (empty(trim($question['text']))) return false;
    if (empty($question['answers'])) return false;
    
    // Trắc nghiệm phải có đáp án đúng
    if (in_array($question['type'], ['single_choice', 'multiple_choice', 'listening'])) {
        if (empty($question['correct'])) return false;
    }
    
    // Kiểm tra tất cả đáp án, đảm bảo không rỗng
    foreach($question['answers'] as $answer_text) {
        if (empty(trim($answer_text))) return false;
    }
    
    return true;
}

// --- XỬ LÝ UPLOAD FILE WORD ---
if ($is_word_upload_request) {
    unset($_SESSION['imported_test_json']);
    unset($_SESSION['import_success_message']);
    unset($_SESSION['import_error_message']);
    unset($_SESSION['debug_log']); // Xóa log cũ

    $current_error = '';
    $current_success = '';

    if (isset($_FILES['word_file']) && $_FILES['word_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['word_file'];
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

        if ($fileExtension !== 'docx') {
            $current_error = "Chỉ chấp nhận file .docx";
        } else {
            try {
                $test_data = parseWordFile($uploadedFile['tmp_name'], $debug_log);
                
                if (empty($test_data['questions'])) {
                    $current_error = "Không tìm thấy câu hỏi hợp lệ. Vui lòng kiểm tra định dạng:<br>
- Câu hỏi: <strong>1. Nội dung</strong> hoặc <strong>[AUDIO] 1. Nội dung</strong><br>
- Đáp án: <strong>A. Đáp án</strong>, <strong>B. Đáp án đúng *</strong>";
                } else {
                    $current_success = "Đọc thành công " . count($test_data['questions']) . " câu hỏi. Kiểm tra và tải audio (nếu cần).";
                    
                    $json_encoded = json_encode($test_data, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                    
                    if ($json_encoded === false) {
                        $current_error = "Lỗi mã hóa JSON: " . json_last_error_msg();
                    } else {
                        $_SESSION['imported_test_json'] = $json_encoded;
                        $_SESSION['import_success_message'] = $current_success;
                        $_SESSION['debug_log'] = $debug_log; // Lưu log vào session
                    }
                }
            } catch (Exception $e) {
                $current_error = "Lỗi đọc file: " . $e->getMessage();
                $debug_log[] = "❌ LỖI: " . $e->getMessage();
            }
        }
    } else {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => "File quá lớn (server)",
            UPLOAD_ERR_FORM_SIZE => "File quá lớn (form)",
            UPLOAD_ERR_NO_FILE => "Chưa chọn file",
        ];
        $error_code = $_FILES['word_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $current_error = $upload_errors[$error_code] ?? "Lỗi upload";
    }

    if (!empty($current_error)) {
        unset($_SESSION['imported_test_json']);
        unset($_SESSION['import_success_message']);
        $_SESSION['import_error_message'] = $current_error;
        $_SESSION['debug_log'] = $debug_log; // Lưu log kể cả khi lỗi
    }

    header('Location: create_test.php');
    exit();
}

// --- XỬ LÝ LƯU THỦ CÔNG ---
elseif ($is_manual_save_request) {
    $title = $_POST['title'] ?? '';
    $duration = $_POST['duration'] ?? 0;
    $questions = $_POST['questions'] ?? [];
    $teacher_id = $_SESSION['user_id'];

    if (empty($title) || empty($questions) || !is_numeric($duration) || $duration <= 0) {
        $error_message = "Vui lòng nhập đầy đủ thông tin";
    } else {
        $invite_code = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6);
        $pdo->beginTransaction();
        
        try {
            $stmt = $pdo->prepare("INSERT INTO tests (teacher_id, title, invite_code, status, duration_minutes) VALUES (?, ?, ?, 'draft', ?)");
            $stmt->execute([$teacher_id, $title, $invite_code, $duration]);
            $test_id = $pdo->lastInsertId();

            foreach ($questions as $q_key => $question) {
                if (!isset($question['text']) || trim($question['text']) === '') continue;

                $question_text = trim($question['text']);
                $question_type = $question['type'] ?? 'single_choice';
                $audio_path = null;

                // Upload audio
                if ($question_type === 'listening' && isset($_FILES['questions']['name'][$q_key]['audio']) && $_FILES['questions']['error'][$q_key]['audio'] === UPLOAD_ERR_OK) {
                    $audioTmpName = $_FILES['questions']['tmp_name'][$q_key]['audio'];
                    $audioOriginalName = $_FILES['questions']['name'][$q_key]['audio'];

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $audioTmpName);
                    finfo_close($finfo);

                    $fileExtension = strtolower(pathinfo($audioOriginalName, PATHINFO_EXTENSION));
                    $allowedTypes = ['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/mp4', 'audio/x-m4a'];
                    $allowedExts = ['mp3', 'wav', 'ogg', 'm4a'];

                    if (!in_array($mimeType, $allowedTypes) && !in_array($fileExtension, $allowedExts)) {
                        throw new Exception("File audio không hợp lệ cho câu " . ($q_key + 1) . ". Kiểu file: " . $mimeType);
                    }

                    $audioFileName = 'audio_' . $test_id . '_' . $q_key . '_' . uniqid() . '.' . $fileExtension;
                    $audioUploadDir = ROOT_PATH . '/uploads/audio/';
                    
                    if (!is_dir($audioUploadDir)) {
                        mkdir($audioUploadDir, 0777, true);
                    }
                    
                    if (move_uploaded_file($audioTmpName, $audioUploadDir . $audioFileName)) {
                        $audio_path = '/uploads/audio/' . $audioFileName;
                    } else {
                        throw new Exception("Lỗi di chuyển file audio câu " . ($q_key + 1));
                    }
                } elseif ($question_type === 'listening' && !empty($question['existing_audio'])) {
                    $audio_path = $question['existing_audio'];
                } elseif ($question_type === 'listening') {
                    throw new Exception("Câu nghe số " . ($q_key + 1) . " thiếu file audio");
                }

                $stmt = $pdo->prepare("INSERT INTO questions (test_id, question_text, question_type, audio_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$test_id, $question_text, $question_type, $audio_path]);
                $question_id = $pdo->lastInsertId();

                // Xử lý đáp án đúng
                $correct_answers_input = $question['correct'] ?? [];
                if ($question_type === 'single_choice' && !is_array($correct_answers_input)) {
                    $correct_answers_input = ($correct_answers_input !== null && $correct_answers_input !== '') ? [$correct_answers_input] : [];
                } elseif (!is_array($correct_answers_input)) {
                    $correct_answers_input = [];
                }

                $correct_answers = array_map('strval', $correct_answers_input);

                if (!isset($question['answers']) || !is_array($question['answers'])) {
                    throw new Exception("Câu " . ($q_key + 1) . " không có đáp án");
                }

                $saved_answer_count = 0;
                foreach ($question['answers'] as $a_key => $answer_text) {
                    $trimmed_answer = trim((string)$answer_text);
                    if (empty($trimmed_answer)) continue;

                    $answer_key_string = (string)$a_key;
                    $is_correct = in_array($answer_key_string, $correct_answers, true) ? 1 : 0;

                    $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->execute([$question_id, $trimmed_answer, $is_correct]);
                    $saved_answer_count++;
                }

                if ($saved_answer_count == 0) {
                    throw new Exception("Câu " . ($q_key + 1) . " phải có ít nhất 1 đáp án hợp lệ (không rỗng)");
                }

                if (($question_type === 'single_choice' || $question_type === 'multiple_choice' || $question_type === 'listening') && empty($correct_answers)) {
                    throw new Exception("Câu trắc nghiệm/nghe " . ($q_key + 1) . " phải có đáp án đúng");
                }
            }
            
            $pdo->commit();
            header("Location: index.php");
            exit();

        } catch(Exception $e) {
            $pdo->rollBack();
            $error_message = "Lỗi: " . $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tạo bài kiểm tra</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .question-block { background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; position: relative; }
        .answer-option { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .answer-option input[type="text"] { flex-grow: 1; }
        .remove-btn { background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; font-size: 0.8em; }
        .remove-question { position: absolute; top: 10px; right: 10px; }
        .question-type-selector { margin-bottom: 10px; }
        .audio-upload { display: none; margin-top: 10px; }
        .upload-section { margin-bottom: 2rem; padding: 1.5rem; border: 2px dashed #4CAF50; border-radius: 8px; background: #f0f7ff; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: .75rem 1.25rem; margin-bottom: 1rem; border-radius: .25rem; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: .75rem 1.25rem; margin-bottom: 1rem; border-radius: .25rem; }
        .audio-required-indicator { color: red; font-weight: bold; margin-left: 10px; display: none; }
        .format-help { background: #fff3cd; border: 1px solid #ffc107; padding: 1rem; border-radius: 5px; margin-top: 1rem; }
        .format-help h4 { margin-top: 0; color: #856404; }
        .format-help code { background: #fff; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <?php include '../_partials/header.php'; ?>
    <div class="container">
        <h1>Tạo bài kiểm tra mới</h1>
        
        <?php if ($error_message): ?>
            <p class="alert error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <p class="alert success"><?php echo $success_message; ?></p>
        <?php endif; ?>
        
        <?php if (!empty($debug_log)): ?>
        <div class="alert" style="background: #e7f3ff; color: #004085; border: 1px solid #b8daff; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 0.85em; line-height: 1.4;">
            <h4 style="margin-top: 0;">📊 Chi tiết phân tích file Word:</h4>
            <?php foreach ($debug_log as $log): ?>
                <div style="white-space: pre-wrap; word-break: break-all;"><?php echo htmlspecialchars($log); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Upload Word -->
        <div class="upload-section">
            <h2>📄 Nhập từ File Word (.docx)</h2>
            <form method="POST" enctype="multipart/form-data">
                <p>Tải lên file .docx theo định dạng quy ước.</p>
                <div class="form-group">
                    <label for="word_file">Chọn file:</label>
                    <input type="file" id="word_file" name="word_file" accept=".docx" required>
                </div>
                <button type="submit" name="submit_word_file" class="button">📖 Đọc File</button>
                
                <div class="format-help">
                    <h4>📋 Định dạng hỗ trợ:</h4>
                    <ul>
                        <li><strong>Câu đơn:</strong> <code>1. Câu hỏi?</code></li>
                        <li><strong>Nhiều đáp án:</strong> <code>[MULTIPLE] 2. Câu hỏi?</code></li>
                        <li><strong>Câu nghe:</strong> <code>[AUDIO] 3. Câu hỏi?</code></li>
                        <li><strong>Đáp án:</strong> <code>A. Sai</code>, <code>B. Đúng *</code></li>
                    </ul>
                    <p><strong>✅ Ví dụ 1 (Cùng dòng - hỗ trợ ký tự \):</strong></p>
                    <pre>1. Thủ đô Việt Nam? A. TP.HCM B. Hà Nội * C. Đà Nẵng\
2. Câu hỏi tiếp... A. Đáp án...</pre>
                    <p><strong>✅ Ví dụ 2 (Khác dòng - định dạng truyền thống):</strong></p>
                    <pre>1. Thủ đô Việt Nam?
A. TP.HCM
B. Hà Nội *
C. Đà Nẵng</pre>
                    <p><strong>✅ Ví dụ 3 (Câu hỏi nhiều đáp án đúng):</strong></p>
                    <pre>[MULTIPLE] 3. Chọn số chẵn? A. 1 B. 2 * C. 3 D. 4 *</pre>
                    <p><strong>✅ Ví dụ 4 (Câu nghe):</strong></p>
                    <pre>[AUDIO] 5. Đoạn audio nói về gì? A. Thời tiết B. Thể thao * C. Du lịch</pre>
                </div>
            </form>
        </div>
        
        <hr>

        <!-- Tạo thủ công -->
        <h2>✏️ Tạo thủ công</h2>
        <form id="create-test-form" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Tiêu đề:</label>
                <input type="text" id="title" name="title" required>
            </div>

            <div class="form-group">
                <label for="duration">Thời gian (phút):</label>
                <input type="number" id="duration" name="duration" min="1" required>
            </div>

            <div id="questions-container"></div>

            <button type="button" id="add-question" class="button">➕ Thêm câu hỏi</button>
            <hr>
            <button type="submit" name="submit_manual_test" class="button">💾 Lưu</button>
        </form>
    </div>

    <div id="imported-data-holder" style="display:none;" data-json="<?php echo ($imported_data_json_for_js !== 'null') ? htmlspecialchars($imported_data_json_for_js, ENT_QUOTES, 'UTF-8') : 'null'; ?>"></div>

    <script>
        let importedData = null;
        try {
            const dataHolder = document.getElementById('imported-data-holder');
            if (dataHolder) {
                const jsonData = dataHolder.getAttribute('data-json');
                if (jsonData && jsonData !== 'null') {
                    importedData = JSON.parse(jsonData);
                    if (typeof importedData !== 'object' || importedData === null) {
                        importedData = null;
                    }
                }
            }
        } catch (e) {
            console.error("JSON parse error:", e);
            importedData = null;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const questionsContainer = document.getElementById('questions-container');
            const addQuestionBtn = document.getElementById('add-question');
            const titleInput = document.getElementById('title');
            const durationInput = document.getElementById('duration');
            let questionIndex = 0;

            function addQuestion(questionData = null) {
                const qIndex = questionIndex++;
                const questionBlock = document.createElement('div');
                questionBlock.className = 'question-block';
                questionBlock.dataset.index = qIndex;

                const questionText = questionData?.text ?? '';
                const questionType = questionData?.type ?? 'single_choice';
                const audioRequired = questionData?.audio_required ?? false;

                questionBlock.innerHTML = `
                    <button type="button" class="remove-btn remove-question">❌</button>
                    <div class="form-group">
                        <label><b>Câu <span class="q-number">${qIndex + 1}</span>:</b></label>
                        <textarea name="questions[${qIndex}][text]" required rows="3">${questionText}</textarea>
                    </div>
                    <div class="form-group question-type-selector">
                        <label>Loại:</label>
                        <select name="questions[${qIndex}][type]" class="question-type-select">
                            <option value="single_choice" ${questionType === 'single_choice' ? 'selected' : ''}>1 đáp án đúng</option>
                            <option value="multiple_choice" ${questionType === 'multiple_choice' ? 'selected' : ''}>Nhiều đáp án đúng</option>
                            <option value="listening" ${questionType === 'listening' ? 'selected' : ''}>Câu nghe</option>
                        </select>
                        <span class="audio-required-indicator" style="${audioRequired ? 'display:inline;' : 'display:none;'}">⚠️ Cần audio!</span>
                    </div>
                    <div class="form-group audio-upload" style="${questionType === 'listening' ? 'display:block;' : 'display:none;'}">
                        <label>Audio:</label>
                        <input type="file" name="questions[${qIndex}][audio]" accept=".mp3,.wav,.ogg,.m4a">
                        ${audioRequired ? '<input type="hidden" name="questions[${qIndex}][existing_audio]" value="true">' : ''}
                        ${audioRequired ? '<small style="color: #d9534f;">⚠️ Đã phát hiện câu nghe từ Word. Vui lòng tải file audio.</small>' : ''}
                    </div>
                    <p>Đáp án:</p>
                    <div class="answers-container"></div>
                    <button type="button" class="button add-answer" style="font-size:0.9em;padding:8px 15px">➕ Thêm đáp án</button>
                `;
                questionsContainer.appendChild(questionBlock);

                const answersContainer = questionBlock.querySelector('.answers-container');
                const typeSelect = questionBlock.querySelector('.question-type-select');

                if (questionData?.answers && Object.keys(questionData.answers).length > 0) {
                    const correctAnswers = (Array.isArray(questionData.correct) ? questionData.correct : [questionData.correct])
                        .filter(c => c !== null && c !== undefined)
                        .map(String);

                    Object.entries(questionData.answers).forEach(([ansKey, ansText]) => {
                        addAnswer(answersContainer, qIndex, questionType, ansText, correctAnswers.includes(String(ansKey)));
                    });
                    
                    while (answersContainer.children.length < 2) {
                        addAnswer(answersContainer, qIndex, questionType);
                    }
                } else {
                    addAnswer(answersContainer, qIndex, questionType);
                    addAnswer(answersContainer, qIndex, questionType);
                }

                if (typeSelect) {
                    typeSelect.addEventListener('change', handleQuestionTypeChange);
                    handleQuestionTypeChange({ target: typeSelect });
                }
            }

            function addAnswer(container, qIdx, questionType, answerText = '', isChecked = false) {
                if (!container) return;
                const answerIndex = container.children.length;
                const answerOption = document.createElement('div');
                answerOption.className = 'answer-option';

                const inputType = (questionType === 'multiple_choice') ? 'checkbox' : 'radio';
                const inputName = (questionType === 'multiple_choice') ? `questions[${qIdx}][correct][]` : `questions[${qIdx}][correct]`;

                answerOption.innerHTML = `
                    <input type="${inputType}" name="${inputName}" value="${answerIndex}" ${isChecked ? 'checked' : ''} class="correct-check">
                    <input type="text" name="questions[${qIdx}][answers][${answerIndex}]" placeholder="${String.fromCharCode(65 + answerIndex)}" value="${answerText}" required>
                    <button type="button" class="remove-btn remove-answer">❌</button>
                `;
                container.appendChild(answerOption);
            }

            function handleQuestionTypeChange(event) {
                const selectElement = event.target;
                const questionBlock = selectElement.closest('.question-block');
                if (!questionBlock) return;

                const answersContainer = questionBlock.querySelector('.answers-container');
                const audioUploadDiv = questionBlock.querySelector('.audio-upload');
                const audioInput = audioUploadDiv?.querySelector('input[type=file]');
                const audioIndicator = questionBlock.querySelector('.audio-required-indicator');
                const qIndex = parseInt(questionBlock.dataset.index);
                const newType = selectElement.value;

                if (audioUploadDiv && audioIndicator) {
                    if (newType === 'listening') {
                        audioUploadDiv.style.display = 'block';
                        audioIndicator.style.display = 'inline';
                    } else {
                        audioUploadDiv.style.display = 'none';
                        audioIndicator.style.display = 'none';
                        if (audioInput) {
                            audioInput.value = '';
                        }
                    }
                }

                if (answersContainer) {
                    const currentAnswers = answersContainer.querySelectorAll('.answer-option');
                    const answerData = [];
                    
                    currentAnswers.forEach((ans) => {
                        const textInput = ans.querySelector('input[type=text]');
                        const checkInput = ans.querySelector('.correct-check');
                        if (textInput && checkInput) {
                            answerData.push({
                                text: textInput.value,
                                checked: checkInput.checked,
                            });
                        }
                    });

                    answersContainer.innerHTML = '';

                    answerData.forEach((data) => {
                        addAnswer(answersContainer, qIndex, newType, data.text, data.checked);
                    });
                    
                    while (answersContainer.children.length < 2) {
                        addAnswer(answersContainer, qIndex, newType);
                    }
                }
            }

            addQuestionBtn.addEventListener('click', () => {
                addQuestion();
                updateQuestionNumbers();
            });

            questionsContainer.addEventListener('click', function(e) {
                const target = e.target;

                if (target.classList.contains('add-answer')) {
                    const questionBlock = target.closest('.question-block');
                    if (!questionBlock) return;
                    const answersContainer = questionBlock.querySelector('.answers-container');
                    const qIndex = parseInt(questionBlock.dataset.index);
                    const questionType = questionBlock.querySelector('.question-type-select').value;
                    if (answersContainer) {
                        addAnswer(answersContainer, qIndex, questionType);
                    }
                }
                else if (target.classList.contains('remove-question')) {
                    const blockToRemove = target.closest('.question-block');
                    if (confirm('Xóa câu hỏi này?')) {
                        if (blockToRemove) {
                            blockToRemove.remove();
                            updateQuestionNumbers();
                        }
                    }
                }
                else if (target.classList.contains('remove-answer')) {
                    const answerOption = target.closest('.answer-option');
                    if (answerOption) {
                        const answersContainer = answerOption.parentElement;
                        if (answersContainer && answersContainer.children.length <= 2) {
                            alert("Phải có ít nhất 2 đáp án");
                            return;
                        }
                        answerOption.remove();
                        if (answersContainer) {
                            updateAnswerPlaceholders(answersContainer);
                        }
                    }
                }
            });

            function updateQuestionNumbers() {
                const allQuestionBlocks = questionsContainer.querySelectorAll('.question-block');
                allQuestionBlocks.forEach((block, index) => {
                    const numberSpan = block.querySelector('.q-number');
                    if (numberSpan) {
                        numberSpan.textContent = index + 1;
                    }
                    block.dataset.index = index;
                    
                    block.querySelectorAll('[name^="questions["]').forEach(input => {
                        const oldName = input.name;
                        const newName = oldName.replace(/questions\[\d+\]/, `questions[${index}]`);
                        if (oldName !== newName) {
                            input.name = newName;
                        }
                    });
                });
                questionIndex = allQuestionBlocks.length;
            }

            function updateAnswerPlaceholders(answersContainer) {
                if (!answersContainer) return;
                const answerOptions = answersContainer.querySelectorAll('.answer-option');
                answerOptions.forEach((option, index) => {
                    const textInput = option.querySelector('input[type="text"]');
                    if (textInput) {
                        textInput.placeholder = String.fromCharCode(65 + index);
                        const oldName = textInput.name;
                        const newName = oldName.replace(/\[answers\]\[\d+\]/, `[answers][${index}]`);
                        if (oldName !== newName) {
                            textInput.name = newName;
                        }
                    }
                    const checkInput = option.querySelector('.correct-check');
                    if (checkInput) {
                        checkInput.value = index.toString();
                    }
                });
            }

            function populateFormFromImport(data) {
                if (!data || typeof data !== 'object' || !Array.isArray(data.questions) || data.questions.length === 0) {
                    if (questionsContainer.children.length === 0) {
                        addQuestion();
                        updateQuestionNumbers();
                    }
                    return;
                }

                titleInput.value = data.title || '';
                durationInput.value = data.duration || 60;

                questionsContainer.innerHTML = '';
                questionIndex = 0;

                data.questions.forEach((qData) => {
                    addQuestion(qData);
                });

                updateQuestionNumbers();
            }

            if (importedData) {
                populateFormFromImport(importedData);
            } else {
                if (questionsContainer.children.length === 0) {
                    addQuestion();
                    updateQuestionNumbers();
                }
            }
        });
    </script>
</body>
</html>