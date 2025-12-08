<?php
session_start();
require_once '../config.php';

// --- KIỂM TRA QUYỀN GIÁO VIÊN ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

// 1. Lấy ID lớp trước
$class_id = $_GET['id'] ?? 0;
$teacher_id = $_SESSION['user_id'];

// 2. Lấy thông tin lớp
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    die("Lớp học không tồn tại hoặc bạn không có quyền truy cập.");
}

// 3. LOGIC TỰ ĐỘNG ĐÓNG BÀI THI (Sửa lỗi: Đặt sau khi đã có $class_id)
// Quét tất cả bài thi đang mở của lớp này mà có end_date < hiện tại
$autoCloseStmt = $pdo->prepare("
    UPDATE tests 
    SET status = 'closed' 
    WHERE class_id = ? 
    AND status != 'closed' 
    AND end_date IS NOT NULL 
    AND end_date <= NOW()
");
$autoCloseStmt->execute([$class_id]);

// 4. Lấy danh sách thành viên
$stmt = $pdo->prepare("
    SELECT u.name, u.email, u.student_code, u.id as user_id, cm.joined_at 
    FROM class_members cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.class_id = ?
    ORDER BY u.name ASC
");
$stmt->execute([$class_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Lấy danh sách bài kiểm tra
$stmt = $pdo->prepare("SELECT * FROM tests WHERE class_id = ? ORDER BY created_at DESC");
$stmt->execute([$class_id]);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lớp: <?php echo htmlspecialchars($class['class_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .tab-btn.active { @apply border-indigo-600 text-indigo-600; }
        /* Ẩn calendar icon mặc định của input date để custom đẹp hơn */
        input[type="datetime-local"]::-webkit-calendar-picker-indicator { cursor: pointer; }
    </style>
</head>
<body class="bg-gray-50 text-slate-800 min-h-screen">
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Navigation -->
        <div class="flex items-center justify-between mb-6">
            <a href="index.php" class="flex items-center text-slate-500 hover:text-indigo-600 transition-colors">
                <i class="fa-solid fa-arrow-left mr-2"></i> Quay lại Dashboard
            </a>
            <div class="flex gap-3">
                <a href="delete_class.php?id=<?php echo $class['id']; ?>" onclick="return confirm('CẢNH BÁO: Xóa lớp sẽ mất toàn bộ dữ liệu bài thi và kết quả!')" class="text-red-600 bg-red-50 hover:bg-red-100 px-4 py-2 rounded-lg font-medium transition-colors border border-red-200 flex items-center">
                    <i class="fa-solid fa-trash mr-2"></i> Xóa Lớp
                </a>
                <a href="create_test.php?class_id=<?php echo $class['id']; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-medium shadow-lg shadow-indigo-200 transition-all flex items-center">
                    <i class="fa-solid fa-file-circle-plus mr-2"></i> Tạo Bài Thi
                </a>
            </div>
        </div>

        <!-- Class Info Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800 mb-2"><?php echo htmlspecialchars($class['class_name']); ?></h1>
                    <p class="text-slate-500"><?php echo htmlspecialchars($class['class_description']); ?></p>
                </div>
                <div class="flex items-center bg-indigo-50 border border-indigo-100 rounded-lg p-3 cursor-pointer hover:bg-indigo-100 transition-colors group" onclick="copyToClipboard('<?php echo $class['class_code']; ?>')">
                    <div class="mr-4">
                        <p class="text-xs text-indigo-400 uppercase font-bold">Mã tham gia</p>
                        <p class="text-xl font-mono font-bold text-indigo-700"><?php echo $class['class_code']; ?></p>
                    </div>
                    <i class="fa-regular fa-copy text-indigo-400 text-xl group-hover:text-indigo-600"></i>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8">
                <button onclick="switchTab('tests')" id="tab-tests" class="tab-btn active border-b-2 border-transparent py-4 px-1 font-medium text-sm flex items-center transition-colors">
                    <i class="fa-regular fa-file-lines mr-2"></i> Bài kiểm tra (<?php echo count($tests); ?>)
                </button>
                <button onclick="switchTab('members')" id="tab-members" class="tab-btn text-slate-500 hover:text-slate-700 border-b-2 border-transparent py-4 px-1 font-medium text-sm flex items-center transition-colors">
                    <i class="fa-solid fa-users mr-2"></i> Thành viên (<?php echo count($members); ?>)
                </button>
            </nav>
        </div>

        <!-- TAB CONTENT: TESTS -->
        <div id="content-tests" class="space-y-4">
            <?php if (empty($tests)): ?>
                <div class="text-center py-12 bg-white rounded-xl border border-dashed border-gray-300">
                    <div class="mx-auto h-12 w-12 text-gray-400 flex items-center justify-center bg-gray-50 rounded-full mb-3"><i class="fa-regular fa-folder-open text-xl"></i></div>
                    <h3 class="text-sm font-medium text-gray-900">Chưa có bài kiểm tra nào</h3>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-visible">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tiêu đề</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã mời</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái & Lịch</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày tạo</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($tests as $test): 
                                $statusClasses = [
                                    'draft' => 'bg-gray-100 text-gray-700 border-gray-300',
                                    'published' => 'bg-green-100 text-green-800 border-green-300 font-bold',
                                    'closed' => 'bg-red-100 text-red-800 border-red-300'
                                ];
                                $currentClass = $statusClasses[$test['status']] ?? 'bg-white border-gray-300';
                                
                                // Xử lý hiển thị thời gian
                                $hasSchedule = !empty($test['end_date']);
                                $endDate = $hasSchedule ? strtotime($test['end_date']) : null;
                                $isExpired = $endDate && $endDate <= time();
                                
                                $timeDisplay = '';
                                if ($hasSchedule) {
                                    if ($isExpired) {
                                        $timeDisplay = '<span class="text-xs text-red-500 flex items-center mt-1"><i class="fa-solid fa-clock mr-1"></i> Đã hết hạn</span>';
                                    } else {
                                        $timeDisplay = '<span class="text-xs text-blue-600 flex items-center mt-1" title="'.date('d/m/Y H:i', $endDate).'"><i class="fa-solid fa-hourglass-half mr-1"></i> Đóng: '.date('H:i d/m', $endDate).'</span>';
                                    }
                                }
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                    <?php echo htmlspecialchars($test['title']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-mono bg-indigo-50 text-indigo-700 px-2 py-1 rounded text-xs border border-indigo-100"><?php echo $test['invite_code']; ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col items-start">
                                        <div class="flex items-center space-x-2">
                                            <form action="update_test_status.php" method="POST" class="m-0">
                                                <input type="hidden" name="test_id" value="<?php echo $test['id']; ?>">
                                                <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                <input type="hidden" name="action_type" value="update_status">
                                                <select name="status" onchange="this.form.submit()" class="text-xs rounded-full px-3 py-1 border focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer <?php echo $currentClass; ?>">
                                                    <option value="draft" <?php echo $test['status'] == 'draft' ? 'selected' : ''; ?>>Nháp</option>
                                                    <option value="published" <?php echo $test['status'] == 'published' ? 'selected' : ''; ?>>Đang mở</option>
                                                    <option value="closed" <?php echo $test['status'] == 'closed' ? 'selected' : ''; ?>>Đã đóng</option>
                                                </select>
                                            </form>
                                            
                                            <button type="button" 
                                                    onclick="openScheduleModal('<?php echo $test['id']; ?>', '<?php echo $class['id']; ?>', '<?php echo $hasSchedule ? date('Y-m-d\TH:i', $endDate) : ''; ?>')" 
                                                    class="<?php echo $hasSchedule && !$isExpired ? 'text-blue-600 bg-blue-50' : 'text-gray-400 hover:text-gray-600'; ?> p-1.5 rounded-full hover:bg-gray-100 transition-colors"
                                                    title="Hẹn giờ đóng">
                                                <i class="fa-solid fa-clock"></i>
                                            </button>
                                        </div>
                                        <?php echo $timeDisplay; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d/m/Y', strtotime($test['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <a href="view_results.php?test_id=<?php echo $test['id']; ?>" class="text-teal-600 hover:text-teal-900 bg-teal-50 hover:bg-teal-100 px-3 py-1.5 rounded transition-colors inline-flex items-center">
                                        <i class="fa-solid fa-square-poll-vertical mr-1"></i> Quản lý
                                    </a>
                                    <a href="edit_test.php?test_id=<?php echo $test['id']; ?>" class="text-amber-600 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded transition-colors inline-flex items-center">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="delete_test.php?test_id=<?php echo $test['id']; ?>&class_id=<?php echo $class['id']; ?>" onclick="return confirm('Bạn có chắc muốn xóa bài kiểm tra này?')" class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded transition-colors inline-flex items-center">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB CONTENT: MEMBERS -->
        <div id="content-members" class="hidden space-y-4">
             <?php if (empty($members)): ?>
                <div class="text-center py-12 bg-white rounded-xl border border-dashed border-gray-300">
                    <h3 class="text-sm font-medium text-gray-900">Chưa có thành viên nào</h3>
                    <p class="text-sm text-gray-500 mt-1">Chia sẻ mã lớp để học sinh tham gia.</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Họ tên</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã SV</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày tham gia</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($members as $mem): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold text-xs mr-3">
                                            <?php echo substr($mem['name'] ?? 'U', 0, 1); ?>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($mem['name']); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono text-indigo-600 font-bold"><?php echo htmlspecialchars($mem['student_code'] ?? '---'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($mem['email']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($mem['joined_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOAST NOTIFICATION -->
    <div id="toast" class="fixed bottom-5 right-5 bg-slate-800 text-white px-4 py-3 rounded-lg shadow-lg transform translate-y-20 opacity-0 transition-all duration-300 z-50 flex items-center">
        <i class="fa-solid fa-check-circle text-green-400 mr-2"></i>
        <span id="toast-msg">Thông báo</span>
    </div>

    <!-- MODAL HẸN GIỜ -->
    <div id="scheduleModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 hidden overflow-y-auto h-full w-full z-50 flex items-center justify-center backdrop-blur-sm">
        <div class="relative mx-auto p-0 border-0 w-full max-w-md shadow-2xl rounded-xl bg-white animate-fade-in-down">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-5 border-b border-gray-100 rounded-t-xl bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fa-solid fa-hourglass-start text-blue-600 mr-2"></i>Hẹn giờ đóng bài
                </h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            
            <form action="update_test_status.php" method="POST" id="scheduleForm">
                <div class="p-6 space-y-6">
                    <input type="hidden" name="action_type" value="update_schedule">
                    <input type="hidden" id="modal_test_id" name="test_id" value="">
                    <input type="hidden" id="modal_class_id" name="class_id" value="">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Thời gian đóng tự động</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-regular fa-calendar text-gray-400"></i>
                            </div>
                            <input type="datetime-local" id="modal_end_date" name="end_date" 
                                class="pl-10 block w-full rounded-lg border-gray-300 border bg-gray-50 focus:bg-white focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2.5 transition-colors">
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Bài thi sẽ tự động khóa và không cho phép làm bài sau thời gian này.</p>
                    </div>

                    <!-- Nút chọn nhanh -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Chọn nhanh</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" onclick="addMinutes(15)" class="px-3 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500 transition-all">+15 Phút</button>
                            <button type="button" onclick="addMinutes(45)" class="px-3 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500 transition-all">+45 Phút</button>
                            <button type="button" onclick="addMinutes(60)" class="px-3 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500 transition-all">+1 Giờ</button>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="bg-gray-50 px-6 py-4 flex flex-row-reverse gap-2 rounded-b-xl border-t border-gray-100">
                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm transition-colors">
                        Lưu & Mở bài thi
                    </button>
                    <button type="button" onclick="clearSchedule()" class="w-full sm:w-auto inline-flex justify-center rounded-lg border border-red-200 shadow-sm px-4 py-2 bg-white text-base font-medium text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:text-sm transition-colors">
                        Xóa hẹn giờ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.getElementById('content-tests').classList.add('hidden');
            document.getElementById('content-members').classList.add('hidden');
            document.getElementById('tab-tests').classList.remove('active', 'border-indigo-600', 'text-indigo-600');
            document.getElementById('tab-members').classList.remove('active', 'border-indigo-600', 'text-indigo-600');
            document.getElementById('tab-tests').classList.add('text-slate-500', 'border-transparent');
            document.getElementById('tab-members').classList.add('text-slate-500', 'border-transparent');
            document.getElementById('content-' + tabName).classList.remove('hidden');
            const activeBtn = document.getElementById('tab-' + tabName);
            activeBtn.classList.add('active', 'border-indigo-600', 'text-indigo-600');
            activeBtn.classList.remove('text-slate-500', 'border-transparent');
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => showToast(`Đã sao chép: ${text}`)).catch(() => showToast('Lỗi sao chép!', true));
        }

        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-msg').innerText = message;
            toast.classList.remove('translate-y-20', 'opacity-0');
            if(window.toastTimeout) clearTimeout(window.toastTimeout);
            window.toastTimeout = setTimeout(() => toast.classList.add('translate-y-20', 'opacity-0'), 3000);
        }

        // --- SCRIPTS CHO MODAL HẸN GIỜ ---
        function openScheduleModal(testId, classId, currentEndDate) {
            document.getElementById('modal_test_id').value = testId;
            document.getElementById('modal_class_id').value = classId;
            document.getElementById('modal_end_date').value = currentEndDate;
            document.getElementById('scheduleModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('scheduleModal').classList.add('hidden');
        }

        function clearSchedule() {
            if(confirm('Bạn muốn hủy hẹn giờ và tắt chế độ đóng tự động?')) {
                document.getElementById('modal_end_date').value = '';
                document.getElementById('scheduleForm').submit();
            }
        }

        function addMinutes(minutes) {
            let date = new Date();
            // Điều chỉnh múi giờ địa phương (nếu cần thiết, tuỳ server)
            // Lấy thời gian hiện tại + phút
            date.setMinutes(date.getMinutes() + minutes);
            // Bù giờ timezone offset để toISOString ra đúng giờ địa phương
            date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
            
            // Cắt lấy format YYYY-MM-DDTHH:MM
            document.getElementById('modal_end_date').value = date.toISOString().slice(0,16);
        }

        // Đóng modal khi click ra ngoài
        window.onclick = function(event) {
            if (event.target == document.getElementById('scheduleModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>