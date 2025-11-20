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

// 1. BẮT BUỘC PHẢI CÓ CLASS_ID (CHẶN BÀI THI TỰ DO)
// Kiểm tra cả GET (khi mới vào) và POST (khi submit form)
$url_class_id = null;
if (isset($_GET['class_id'])) {
    $url_class_id = intval($_GET['class_id']);
} elseif (isset($_POST['class_id'])) {
    $url_class_id = intval($_POST['class_id']);
}

// Nếu không có class_id, đuổi về trang chủ ngay lập tức
if (!$url_class_id) {
    $_SESSION['error_msg'] = "Bạn phải chọn một lớp học để tạo bài kiểm tra!";
    header('Location: index.php'); // Hoặc dashboard.php
    exit();
}

$error_message = '';
$success_message = '';
$imported_data_json_for_js = 'null';
$debug_log = []; 

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

// --- HÀM PHÂN TÍCH FILE WORD (GIỮ NGUYÊN LOGIC CŨ CỦA BẠN) ---
function parseWordFile($filePath, &$debug_log) {
    $debug_log[] = "========== BẮT ĐẦU PHÂN TÍCH FILE ==========";
    $phpWord = IOFactory::load($filePath);
    $originalFileName = pathinfo($filePath, PATHINFO_FILENAME);
    
    $test_data = [
        'title' => 'Bài kiểm tra - ' . $originalFileName,
        'duration' => 45,
        'questions' => []
    ];
    
    $current_question = null;
    $line_number = 0;
    $pending_tag = ''; 
    
    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            $line_number++;
            $raw_text = trim(extractTextFromElement($element));
            
            if (empty($raw_text)) continue;
            
            $raw_text = preg_replace('/\\\\+/', ' ', $raw_text); 
            $raw_text = preg_replace('/\s+/', ' ', $raw_text);
            $raw_text = trim($raw_text);
            
            $split_texts = preg_split('/(?=(?:\[[^\]]+\]\s*)?\d+\.\s+)/', $raw_text, -1, PREG_SPLIT_NO_EMPTY);
            
            $chunk_index = 0;
            foreach ($split_texts as $text_chunk) {
                $text = trim($text_chunk);
                if (empty($text)) continue;
                $chunk_index++;
                
                if (preg_match('/^\[([^\]]+)\]$/s', $text, $tag_match)) {
                    $pending_tag = strtoupper(trim($tag_match[1]));
                    continue;
                }
                
                if (preg_match('/^(?:\[([^\]]+)\]\s*)?(\d+)\.\s*(?:\[([^\]]+)\]\s*)?(.+?)(?=\s+[A-Z]\.\s+|$)/s', $text, $q_matches)) {
                    if ($current_question !== null && !empty($current_question['answers'])) {
                        if (validateQuestion($current_question)) {
                            $test_data['questions'][] = $current_question;
                        }
                    }
                    
                    $tag = '';
                    if (!empty($pending_tag)) {
                        $tag = $pending_tag;
                        $pending_tag = '';
                    } elseif (!empty(trim($q_matches[1] ?? ''))) {
                        $tag = strtoupper(trim($q_matches[1]));
                    } elseif (!empty(trim($q_matches[3] ?? ''))) {
                        $tag = strtoupper(trim($q_matches[3]));
                    }
                    
                    $question_text_raw = trim($q_matches[4]);
                    $question_text = $question_text_raw;
                    if (preg_match('/^(.+?)\s+[A-Z]\.\s+/s', $question_text_raw, $split_match)) {
                        $question_text = trim($split_match[1]);
                    }
                    
                    $question_type = 'single_choice';
                    $audio_required = false;
                    if ($tag === 'AUDIO' || $tag === 'LISTENING') {
                        $question_type = 'listening';
                        $audio_required = true;
                    } elseif ($tag === 'MULTIPLE' || $tag === 'MULTI') {
                        $question_type = 'multiple_choice';
                    }
                    
                    $current_question = [
                        'text' => $question_text,
                        'type' => $question_type,
                        'audio_required' => $audio_required,
                        'answers' => [],
                        'correct' => []
                    ];
                    
                    if (preg_match_all('/([A-Z])\.\s*(.+?)(?=\s+[A-Z]\.\s+|$)/s', $text, $a_matches, PREG_SET_ORDER)) {
                        foreach ($a_matches as $a_match) {
                            $raw_answer_text = trim($a_match[2]);
                            $raw_answer_text = preg_replace('/\s*\\\\\s*/', ' ', $raw_answer_text);
                            $raw_answer_text = preg_replace('/\s+/', ' ', $raw_answer_text);
                            
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
                            
                            if ($is_correct) {
                                if ($current_question['type'] === 'single_choice') {
                                    $current_question['correct'] = [(string)$answer_key];
                                } else {
                                    $current_question['correct'][] = (string)$answer_key;
                                }
                            }
                        }
                    }
                } elseif ($current_question !== null && preg_match('/^([A-Z])\.\s*(.+)/is', $text, $matches)) {
                    // Xử lý đáp án dòng riêng lẻ
                     $raw_answer_text = trim($matches[2]);
                     $is_correct = false;
                     if (strpos($raw_answer_text, '*') !== false) {
                         $is_correct = true;
                         $answer_text = trim(str_replace('*', '', $raw_answer_text), " \t\n\r\0\x0B.,?!;");
                     } else {
                         $answer_text = rtrim($raw_answer_text, " \t\n\r\0\x0B.,?!;");
                     }
                     
                     if (!empty($answer_text)) {
                         $answer_key = count($current_question['answers']);
                         $current_question['answers'][$answer_key] = $answer_text;
                         if ($is_correct) {
                             if ($current_question['type'] === 'single_choice') {
                                 $current_question['correct'] = [(string)$answer_key];
                             } else {
                                 $current_question['correct'][] = (string)$answer_key;
                             }
                         }
                     }
                } elseif ($current_question !== null) {
                    // Nối text vào câu hỏi hoặc đáp án cuối
                    if (empty($current_question['answers'])) {
                        $current_question['text'] .= "\n" . $text;
                    } else {
                        $last_answer_key = count($current_question['answers']) - 1;
                        if (isset($current_question['answers'][$last_answer_key])) {
                            $current_question['answers'][$last_answer_key] .= "\n" . $text;
                        }
                    }
                }
            } 
        } 
    } 
    
    if ($current_question !== null && !empty($current_question['answers'])) {
        if (validateQuestion($current_question)) {
            $test_data['questions'][] = $current_question;
        }
    }
    
    return $test_data;
}

function validateQuestion($question) {
    if (empty(trim($question['text']))) return false;
    if (empty($question['answers'])) return false;
    if (in_array($question['type'], ['single_choice', 'multiple_choice', 'listening'])) {
        if (empty($question['correct'])) return false;
    }
    return true;
}

// --- XỬ LÝ UPLOAD FILE WORD ---
if ($is_word_upload_request) {
    unset($_SESSION['imported_test_json']);
    unset($_SESSION['import_success_message']);
    unset($_SESSION['import_error_message']);
    unset($_SESSION['debug_log']);

    $current_error = '';
    $current_success = '';

    // TẠO URL REDIRECT ĐỂ GIỮ LẠI CLASS ID
    $redirect_url = 'create_test.php';
    if ($url_class_id) {
        $redirect_url .= '?class_id=' . $url_class_id;
    }

    if (isset($_FILES['word_file']) && $_FILES['word_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['word_file'];
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

        if ($fileExtension !== 'docx') {
            $current_error = "Chỉ chấp nhận file .docx";
        } else {
            try {
                $test_data = parseWordFile($uploadedFile['tmp_name'], $debug_log);
                
                if (empty($test_data['questions'])) {
                    $current_error = "Không tìm thấy câu hỏi hợp lệ. Vui lòng kiểm tra định dạng Word.";
                } else {
                    $current_success = "Đọc thành công " . count($test_data['questions']) . " câu hỏi.";
                    $json_encoded = json_encode($test_data, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
                    
                    if ($json_encoded === false) {
                        $current_error = "Lỗi mã hóa JSON.";
                    } else {
                        $_SESSION['imported_test_json'] = $json_encoded;
                        $_SESSION['import_success_message'] = $current_success;
                        $_SESSION['debug_log'] = $debug_log;
                    }
                }
            } catch (Exception $e) {
                $current_error = "Lỗi đọc file: " . $e->getMessage();
            }
        }
    } else {
        $current_error = "Chưa chọn file hoặc lỗi upload.";
    }

    if (!empty($current_error)) {
        $_SESSION['import_error_message'] = $current_error;
        $_SESSION['debug_log'] = $debug_log;
    }

    // *** QUAN TRỌNG: Redirect về URL có kèm class_id ***
    header('Location: ' . $redirect_url);
    exit();
}

// --- XỬ LÝ LƯU THỦ CÔNG ---
elseif ($is_manual_save_request) {
    $title = $_POST['title'] ?? '';
    $duration = $_POST['duration'] ?? 0;
    $questions = $_POST['questions'] ?? [];
    $teacher_id = $_SESSION['user_id'];
    
    // Lấy class_id từ POST
    $form_class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;

    if (empty($form_class_id)) {
        $error_message = "Lỗi: Không xác định được lớp học.";
    } elseif (empty($title) || empty($questions) || !is_numeric($duration) || $duration <= 0) {
        $error_message = "Vui lòng nhập đầy đủ thông tin tiêu đề, thời gian và câu hỏi.";
    } else {
        $invite_code = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6);
        $pdo->beginTransaction();
        
        try {
            // Insert bài thi
            $stmt = $pdo->prepare("INSERT INTO tests (teacher_id, class_id, title, invite_code, status, duration_minutes) VALUES (?, ?, ?, ?, 'draft', ?)");
            $stmt->execute([$teacher_id, $form_class_id, $title, $invite_code, $duration]);
            $test_id = $pdo->lastInsertId();

            foreach ($questions as $q_key => $question) {
                if (!isset($question['text']) || trim($question['text']) === '') continue;

                $question_text = trim($question['text']);
                $question_type = $question['type'] ?? 'single_choice';
                $audio_path = null;

                // Xử lý Audio
                if ($question_type === 'listening') {
                    if (isset($_FILES['questions']['name'][$q_key]['audio']) && $_FILES['questions']['error'][$q_key]['audio'] === UPLOAD_ERR_OK) {
                         $audioTmpName = $_FILES['questions']['tmp_name'][$q_key]['audio'];
                         $audioOriginalName = $_FILES['questions']['name'][$q_key]['audio'];
                         $fileExtension = strtolower(pathinfo($audioOriginalName, PATHINFO_EXTENSION));
                         $audioFileName = 'audio_' . $test_id . '_' . $q_key . '_' . uniqid() . '.' . $fileExtension;
                         $audioUploadDir = ROOT_PATH . '/uploads/audio/';
                         if (!is_dir($audioUploadDir)) mkdir($audioUploadDir, 0777, true);
                         if (move_uploaded_file($audioTmpName, $audioUploadDir . $audioFileName)) {
                             $audio_path = '/uploads/audio/' . $audioFileName;
                         }
                    } elseif (!empty($question['existing_audio'])) {
                        $audio_path = $question['existing_audio'];
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO questions (test_id, question_text, question_type, audio_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$test_id, $question_text, $question_type, $audio_path]);
                $question_id = $pdo->lastInsertId();

                $correct_answers = $question['correct'] ?? [];
                if (!is_array($correct_answers)) $correct_answers = [$correct_answers];
                $correct_answers = array_map('strval', $correct_answers);

                foreach ($question['answers'] as $a_key => $answer_text) {
                    $trimmed_answer = trim((string)$answer_text);
                    if (empty($trimmed_answer)) continue;
                    
                    $is_correct = in_array((string)$a_key, $correct_answers, true) ? 1 : 0;
                    $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->execute([$question_id, $trimmed_answer, $is_correct]);
                }
            }
            
            $pdo->commit();
            // Thành công -> Quay về trang xem lớp
            header("Location: view_class.php?id=" . $form_class_id);
            exit();

        } catch(Exception $e) {
            $pdo->rollBack();
            $error_message = "Lỗi hệ thống: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tạo bài kiểm tra - Lớp #<?php echo $url_class_id; ?></title>
    <!-- Tailwind & Fonts -->
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
                <a href="view_class.php?id=<?php echo $url_class_id; ?>" class="text-slate-500 hover:text-indigo-600 font-medium flex items-center gap-2 mb-1 transition-colors">
                    <i class="fa-solid fa-arrow-left"></i> Quay lại Lớp học
                </a>
                <h1 class="text-2xl font-bold text-slate-800">Tạo bài kiểm tra mới</h1>
                <p class="text-sm text-slate-500">Đang tạo cho lớp ID: <span class="font-mono font-bold text-indigo-600"><?php echo $url_class_id; ?></span></p>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <!-- Thông báo lỗi/thành công -->
            <?php if ($error_message): ?>
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-lg p-4 mb-6 flex items-center">
                    <i class="fa-solid fa-circle-exclamation mr-3 text-xl"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="bg-green-50 text-green-700 border border-green-200 rounded-lg p-4 mb-6 flex items-center">
                    <i class="fa-solid fa-circle-check mr-3 text-xl"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <!-- Debug Log -->
            <?php if (!empty($debug_log)): ?>
            <div class="bg-slate-900 text-green-400 rounded-lg p-4 mb-6 text-xs font-mono max-h-60 overflow-y-auto">
                <div class="flex justify-between items-center mb-2 border-b border-slate-700 pb-2">
                    <span class="font-bold">SYSTEM LOG</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-red-400 hover:text-red-300">Close [x]</button>
                </div>
                <?php foreach ($debug_log as $log): ?>
                    <div class="mb-1">> <?php echo htmlspecialchars($log); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Upload Section -->
            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-6 mb-8">
                <h2 class="text-lg font-bold text-indigo-800 mb-2"><i class="fa-solid fa-file-word mr-2"></i>Nhập từ Word (.docx)</h2>
                <p class="text-sm text-indigo-600 mb-4">Hỗ trợ tự động nhận diện câu hỏi, đáp án đúng (đánh dấu *) và file nghe.</p>
                
                <form method="POST" enctype="multipart/form-data" action="create_test.php?class_id=<?php echo $url_class_id; ?>" class="flex gap-3 items-end">
                    <div class="flex-1">
                        <input type="file" name="word_file" accept=".docx" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-100 file:text-indigo-700 hover:file:bg-indigo-200 transition-all cursor-pointer">
                    </div>
                    <button type="submit" name="submit_word_file" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors font-medium shadow-sm shadow-indigo-200">
                        <i class="fa-solid fa-upload mr-1"></i> Tải lên & Phân tích
                    </button>
                </form>
            </div>

            <div class="flex items-center gap-4 mb-8">
                <div class="h-px bg-gray-200 flex-1"></div>
                <span class="text-gray-400 text-sm font-medium uppercase">Hoặc nhập thủ công</span>
                <div class="h-px bg-gray-200 flex-1"></div>
            </div>

            <!-- Manual Form -->
            <form id="create-test-form" method="POST" enctype="multipart/form-data" action="create_test.php?class_id=<?php echo $url_class_id; ?>">
                <!-- QUAN TRỌNG: Input ẩn chứa Class ID -->
                <input type="hidden" name="class_id" value="<?php echo $url_class_id; ?>">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tên bài kiểm tra <span class="text-red-500">*</span></label>
                        <input type="text" id="title" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all" placeholder="Ví dụ: Kiểm tra 15 phút chương 1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Thời gian (phút) <span class="text-red-500">*</span></label>
                        <input type="number" id="duration" name="duration" min="1" value="45" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>

                <div id="questions-container" class="space-y-6"></div>

                <div class="mt-6 flex justify-between items-center pt-6 border-t border-gray-200">
                    <button type="button" id="add-question" class="text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-5 py-2.5 rounded-lg font-medium transition-colors border border-indigo-200">
                        <i class="fa-solid fa-plus-circle mr-2"></i>Thêm câu hỏi
                    </button>
                    <button type="submit" name="submit_manual_test" class="bg-gradient-to-r from-indigo-600 to-blue-600 text-white px-8 py-3 rounded-lg font-bold shadow-lg shadow-indigo-200 hover:shadow-xl hover:-translate-y-0.5 transition-all">
                        <i class="fa-solid fa-save mr-2"></i>Lưu bài kiểm tra
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden Data Holder for Import -->
    <div id="imported-data-holder" class="hidden" data-json="<?php echo ($imported_data_json_for_js !== 'null') ? htmlspecialchars($imported_data_json_for_js, ENT_QUOTES, 'UTF-8') : 'null'; ?>"></div>

    <!-- Javascript xử lý thêm câu hỏi (Đã được làm đẹp code) -->
    <script>
        // ... (Phần JS giữ nguyên logic nhưng cập nhật class CSS cho đẹp)
        let importedData = null;
        try {
            const dataHolder = document.getElementById('imported-data-holder');
            if (dataHolder) {
                const jsonData = dataHolder.getAttribute('data-json');
                if (jsonData && jsonData !== 'null') importedData = JSON.parse(jsonData);
            }
        } catch (e) { console.error("JSON parse error:", e); }

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
                            <input type="file" name="questions[${qIndex}][audio]" accept=".mp3,.wav,.ogg,.m4a" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700">
                            ${audioRequired ? '<input type="hidden" name="questions['+qIndex+'][existing_audio]" value="true"><p class="text-xs text-orange-600 mt-1"><i class="fa-solid fa-triangle-exclamation"></i> Cần tải file audio!</p>' : ''}
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

                // Add answers logic (same as before)
                if (questionData?.answers && Object.keys(questionData.answers).length > 0) {
                    const correctAnswers = (Array.isArray(questionData.correct) ? questionData.correct : [questionData.correct]).filter(c => c != null).map(String);
                    Object.entries(questionData.answers).forEach(([ansKey, ansText]) => {
                        addAnswer(answersContainer, qIndex, questionType, ansText, correctAnswers.includes(String(ansKey)));
                    });
                    while (answersContainer.children.length < 2) addAnswer(answersContainer, qIndex, questionType);
                } else {
                    addAnswer(answersContainer, qIndex, questionType);
                    addAnswer(answersContainer, qIndex, questionType);
                    addAnswer(answersContainer, qIndex, questionType);
                    addAnswer(answersContainer, qIndex, questionType);
                }

                if (typeSelect) {
                    typeSelect.addEventListener('change', handleQuestionTypeChange);
                }
            }

            function addAnswer(container, qIdx, questionType, answerText = '', isChecked = false) {
                if (!container) return;
                const answerIndex = container.children.length;
                const answerOption = document.createElement('div');
                answerOption.className = 'answer-option flex items-center gap-2 group';

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

            // Event delegation for dynamic elements
            questionsContainer.addEventListener('click', function(e) {
                const target = e.target.closest('button');
                if (!target) return;

                if (target.classList.contains('add-answer')) {
                    const block = target.closest('.question-block');
                    const type = block.querySelector('.question-type-select').value;
                    addAnswer(block.querySelector('.answers-container'), block.dataset.index, type);
                } else if (target.classList.contains('remove-question')) {
                    if (confirm('Xóa câu hỏi này?')) {
                        target.closest('.question-block').remove();
                        updateQuestionNumbers();
                    }
                } else if (target.classList.contains('remove-answer')) {
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
                const audioDiv = block.querySelector('.audio-upload');
                
                if (newType === 'listening') audioDiv.classList.remove('hidden');
                else audioDiv.classList.add('hidden');

                const container = block.querySelector('.answers-container');
                const inputs = container.querySelectorAll('.correct-check');
                const newInputType = (newType === 'multiple_choice') ? 'checkbox' : 'radio';
                
                inputs.forEach(input => {
                    input.type = newInputType;
                    // Reset selection when switching types to avoid confusion
                    input.checked = false; 
                });
            }

            function updateQuestionNumbers() {
                questionsContainer.querySelectorAll('.question-block').forEach((block, idx) => {
                    block.querySelector('.q-number').textContent = idx + 1;
                    // Logic update name attributes phức tạp hơn, để đơn giản ta chỉ update số hiển thị
                    // Trong thực tế cần update name="questions[NEW_INDEX]..."
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

            if (importedData) {
                titleInput.value = importedData.title || '';
                durationInput.value = importedData.duration || 45;
                questionsContainer.innerHTML = '';
                if(importedData.questions) importedData.questions.forEach(q => addQuestion(q));
            } else {
                addQuestion(); 
            }
        });
    </script>
</body>
</html>