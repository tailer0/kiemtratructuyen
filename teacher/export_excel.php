<?php
// === BẮT ĐẦU OUTPUT BUFFERING ===
ob_start(); 

session_start();
require_once '../config.php'; 

if (!isset($pdo)) {
    ob_end_clean();
    die("Lỗi nghiêm trọng: Không thể khởi tạo kết nối cơ sở dữ liệu.");
}

require_once ROOT_PATH . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    http_response_code(403);
    ob_end_clean(); 
    die("Bạn không có quyền truy cập chức năng này.");
}

if (!isset($_GET['test_id']) || !is_numeric($_GET['test_id'])) {
    http_response_code(400);
    ob_end_clean();
    die("Thiếu hoặc ID bài kiểm tra không hợp lệ.");
}

$test_id = intval($_GET['test_id']);
$teacher_id = $_SESSION['user_id'];

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

    // --- [SỬA ĐOẠN NÀY] ---
    // Lấy u.student_code làm MSSV chính thức. Nếu ko có mới lấy ta.student_id (dữ liệu cũ)
    $sql = "
        SELECT 
            ta.id as attempt_id,
            u.name as real_name,          -- Lấy tên từ bảng user (mới nhất)
            u.student_code as real_mssv,  -- Lấy MSSV chuẩn từ bảng user
            u.dob as real_dob,            -- Lấy ngày sinh chuẩn
            ta.student_name,              -- Tên lúc làm bài (backup)
            ta.student_id,                -- MSSV lúc làm bài (backup)
            ta.student_dob, 
            ta.score,
            ta.start_time,
            ta.end_time,
            u.email,
            (
                SELECT GROUP_CONCAT(CONCAT(
                    CASE 
                        WHEN cl.log_type = 'tab_switch' THEN 'Chuyển tab'
                        WHEN cl.log_type = 'multiple_faces' THEN 'Nhiều người'
                        WHEN cl.log_type = 'no_face' THEN 'Không thấy mặt'
                        ELSE cl.log_type 
                    END,
                    ' (', DATE_FORMAT(cl.timestamp, '%H:%i:%s'), ')'
                ) SEPARATOR '\n') 
                FROM cheating_logs cl 
                WHERE cl.attempt_id = ta.id
            ) as violation_details,
            (
                SELECT COUNT(*) 
                FROM exam_messages em 
                WHERE em.attempt_id = ta.id 
                AND em.sender_type = 'system' 
                AND em.message LIKE '%đình chỉ%'
            ) as is_suspended
        FROM test_attempts ta
        LEFT JOIN users u ON ta.user_id = u.id
        WHERE ta.test_id = ?
        ORDER BY ta.student_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$test_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    ob_end_clean();
    die("Lỗi cơ sở dữ liệu: " . $e->getMessage());
}

// --- TẠO FILE EXCEL ---
try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('KetQua_ChiTiet');

    $headers = [
        'A' => 'STT',
        'B' => 'Họ và Tên',
        'C' => 'MSSV',
        'D' => 'Email',
        'E' => 'Ngày sinh',
        'F' => 'Điểm số',
        'G' => 'Trạng thái',
        'H' => 'Chi tiết vi phạm'
    ];

    foreach ($headers as $col => $text) {
        $sheet->setCellValue($col . '1', $text);
    }

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
    $sheet->getRowDimension('1')->setRowHeight(30);

    $rowIndex = 2;
    if (!empty($results)) {
        $stt = 1;
        foreach ($results as $row) {
            // Ưu tiên lấy thông tin từ bảng Users (chuẩn xác nhất hiện tại)
            // Nếu không có (ví dụ user bị xóa), mới lấy từ lịch sử thi
            $final_name = !empty($row['real_name']) ? $row['real_name'] : $row['student_name'];
            $final_mssv = !empty($row['real_mssv']) ? $row['real_mssv'] : $row['student_id'];
            $final_dob  = !empty($row['real_dob']) ? $row['real_dob'] : $row['student_dob'];

            $sheet->setCellValue('A' . $rowIndex, $stt++);
            $sheet->setCellValue('B' . $rowIndex, $final_name);
            $sheet->setCellValueExplicit('C' . $rowIndex, $final_mssv, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('D' . $rowIndex, $row['email'] ?? '');
            
            $dobVal = '';
            if ($final_dob) {
                $timestamp = strtotime($final_dob);
                if ($timestamp) {
                    $dobVal = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($timestamp);
                    $sheet->setCellValue('E' . $rowIndex, $dobVal);
                    $sheet->getStyle('E' . $rowIndex)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                }
            }
            
            $sheet->setCellValue('F' . $rowIndex, $row['score']);
            if ($row['score'] !== null) {
                $sheet->getStyle('F' . $rowIndex)->getNumberFormat()->setFormatCode('0.00');
            }

            $statusText = '';
            $statusColor = '000000';

            if ($row['is_suspended'] > 0) {
                $statusText = 'ĐÃ ĐÌNH CHỈ';
                $statusColor = 'DC3545';
            } elseif (!empty($row['end_time'])) {
                $statusText = 'Đã nộp bài';
                $statusColor = '28A745';
            } else {
                $statusText = 'Đang làm bài';
                $statusColor = 'FFC107';
            }

            $sheet->setCellValue('G' . $rowIndex, $statusText);
            $sheet->getStyle('G' . $rowIndex)->getFont()->getColor()->setARGB($statusColor);
            $sheet->getStyle('G' . $rowIndex)->getFont()->setBold(true);

            $sheet->setCellValue('H' . $rowIndex, $row['violation_details']);
            $sheet->getStyle('H' . $rowIndex)->getAlignment()->setWrapText(true);

            $rowStyle = [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP]
            ];
            $sheet->getStyle('A' . $rowIndex . ':H' . $rowIndex)->applyFromArray($rowStyle);

            $rowIndex++;
        }
    } else {
        $sheet->setCellValue('A2', 'Chưa có dữ liệu.');
        $sheet->mergeCells('A2:H2');
    }

    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $sheet->getColumnDimension('H')->setWidth(50);

    ob_clean();
    $filename = "KetQua_" . $test_title_slug . "_" . date('d-m-Y_H-i') . ".xlsx";

    if (headers_sent()) {
        ob_end_clean();
        die("Lỗi: Headers đã được gửi.");
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public'); 

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();

} catch (Exception $e) {
    ob_end_clean();
    die("Lỗi khi tạo Excel: " . $e->getMessage());
}
ob_end_clean();
?>