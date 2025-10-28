<?php
// === BẮT ĐẦU OUTPUT BUFFERING ===
// Phải là dòng đầu tiên tuyệt đối trong file
ob_start(); 
// =============================

// === DI CHUYỂN VÀO SAU ob_start() ===
session_start();
require_once '../config.php'; 
// Kiểm tra xem $pdo có tồn tại không sau khi require config
if (!isset($pdo)) {
    ob_end_clean(); // Xóa buffer nếu config lỗi
    die("Lỗi nghiêm trọng: Không thể khởi tạo kết nối cơ sở dữ liệu từ config.php.");
}
require_once ROOT_PATH . '/vendor/autoload.php';
// ===================================

// Sử dụng các lớp từ PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Bảo vệ trang: Chỉ teacher mới được truy cập
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    http_response_code(403);
    ob_end_clean(); 
    die("Bạn không có quyền truy cập chức năng này.");
}

// Kiểm tra xem test_id có được cung cấp không
if (!isset($_GET['test_id']) || !is_numeric($_GET['test_id'])) {
    http_response_code(400);
    ob_end_clean();
    die("Thiếu hoặc ID bài kiểm tra không hợp lệ.");
}

$test_id = intval($_GET['test_id']);
$teacher_id = $_SESSION['user_id'];

// Lấy thông tin bài test
try {
    $stmt = $pdo->prepare("SELECT title FROM tests WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$test_id, $teacher_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$test) {
        http_response_code(404);
        ob_end_clean();
        die("Bài kiểm tra không tồn tại hoặc bạn không có quyền truy cập.");
    }
    $test_title_slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($test['title']));

    // Lấy danh sách kết quả
    $stmt = $pdo->prepare("
        SELECT student_name, student_id, student_dob, score
        FROM test_attempts
        WHERE test_id = ?
        ORDER BY student_name
    ");
    $stmt->execute([$test_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    ob_end_clean();
    error_log("Lỗi PDO khi lấy dữ liệu: " . $e->getMessage());
    die("Lỗi cơ sở dữ liệu khi truy vấn thông tin.");
}


// --- Tạo file Excel (.xlsx) ---
try {
    // 1. Tạo một đối tượng Spreadsheet mới
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // 2. Đặt tiêu đề cho các cột
    $sheet->setCellValue('A1', 'Họ và Tên');
    $sheet->setCellValue('B1', 'Mã số sinh viên');
    $sheet->setCellValue('C1', 'Ngày sinh');
    $sheet->setCellValue('D1', 'Điểm');

    // 3. Ghi dữ liệu kết quả vào các dòng tiếp theo
    $rowIndex = 2; // Bắt đầu ghi từ dòng 2
    if (!empty($results)) {
        foreach ($results as $row) {
            $sheet->setCellValue('A' . $rowIndex, $row['student_name']);
            $sheet->setCellValueExplicit('B' . $rowIndex, $row['student_id'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            // Xử lý ngày sinh an toàn hơn
            $dob_timestamp = $row['student_dob'] ? strtotime($row['student_dob']) : false;
            $dob_excel_value = ($dob_timestamp !== false) ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($dob_timestamp) : '';
            $sheet->setCellValue('C' . $rowIndex, $dob_excel_value);
            if ($dob_excel_value !== '') { // Chỉ định dạng nếu có ngày
                 $sheet->getStyle('C' . $rowIndex)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
            }
            $sheet->setCellValue('D' . $rowIndex, $row['score']);
            $sheet->getStyle('D' . $rowIndex)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
            $rowIndex++;
        }
    } else {
        $sheet->setCellValue('A' . $rowIndex, 'Chưa có sinh viên nào nộp bài.');
        $sheet->mergeCells('A' . $rowIndex . ':D' . $rowIndex);
    }

    // 4. Tự động điều chỉnh độ rộng cột
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // === XÓA SẠCH OUTPUT BUFFER TRƯỚC KHI GỬI HEADER ===
    ob_clean(); 
    // Không cần flush() ở đây, nó có thể gây lỗi header nếu output đã bắt đầu
    // ===============================================

    // 5. Thiết lập headers để trình duyệt tải file .xlsx
    $filename = "ket_qua_" . $test_title_slug . "_" . date('YmdHis') . ".xlsx";
    // Đảm bảo không có output nào trước các header này
    if (headers_sent()) {
        ob_end_clean();
        die("Lỗi: Headers đã được gửi đi trước khi xuất file Excel. Vui lòng kiểm tra code hoặc file config.");
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    // Bỏ các header Cache-Control và Expires dư thừa, Pragma là đủ
    // header('Cache-Control: max-age=1'); 
    // header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    // header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); 
    // header('Cache-Control: cache, must-revalidate');
    header('Pragma: public'); 

    // 6. Tạo đối tượng Writer và xuất file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    // === KẾT THÚC OUTPUT BUFFERING ===
    // ob_end_flush(); // Không cần thiết sau khi save('php://output') và exit()

    // Dừng kịch bản
    exit();

} catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
    ob_end_clean();
    error_log("Lỗi PhpSpreadsheet: " . $e->getMessage());
    // Hiển thị lỗi thân thiện hơn cho người dùng
    die("Đã xảy ra lỗi khi tạo file Excel (Thư viện Spreadsheet). Chi tiết lỗi đã được ghi lại."); 
} catch (Exception $e) {
    ob_end_clean();
    error_log("Lỗi không xác định khi xuất Excel: " . $e->getMessage());
     // Hiển thị lỗi thân thiện hơn cho người dùng
    die("Đã xảy ra lỗi không xác định khi tạo file Excel. Chi tiết lỗi đã được ghi lại.");
}

// Nếu kịch bản chạy đến đây mà chưa exit(), xóa buffer cuối cùng
ob_end_clean(); 
?>

