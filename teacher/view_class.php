<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'teacher') {
    header('Location: /index.php');
    exit();
}

$class_id = $_GET['id'] ?? 0;
$teacher_id = $_SESSION['user_id'];

// 1. Lấy thông tin lớp
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    die("Lớp học không tồn tại hoặc bạn không có quyền truy cập.");
}

// 2. Lấy danh sách thành viên (Đã thêm u.student_code)
$stmt = $pdo->prepare("
    SELECT u.name, u.email, u.student_code, u.id as user_id, cm.joined_at 
    FROM class_members cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.class_id = ?
    ORDER BY u.name ASC
");
$stmt->execute([$class_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Lấy danh sách bài kiểm tra
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
                <!-- Nút Xóa Lớp -->
                <a href="delete_class.php?id=<?php echo $class['id']; ?>" onclick="return confirm('CẢNH BÁO: Bạn có chắc chắn muốn xóa LỚP HỌC này không?\n\nHành động này sẽ xóa:\n- Toàn bộ bài thi trong lớp\n- Toàn bộ kết quả của học sinh\n- Danh sách thành viên')" class="text-red-600 bg-red-50 hover:bg-red-100 px-4 py-2 rounded-lg font-medium transition-colors border border-red-200 flex items-center">
                    <i class="fa-solid fa-trash mr-2"></i> Xóa Lớp
                </a>
                <!-- Nút Tạo Bài Thi -->
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
                <!-- Copy Code Box -->
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
                    <p class="text-sm text-gray-500 mt-1">Hãy tạo bài kiểm tra đầu tiên cho lớp này.</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tiêu đề</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã mời</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày tạo</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($tests as $test): 
                                // Màu sắc cho Select Box dựa trên trạng thái
                                $statusClasses = [
                                    'draft' => 'bg-gray-100 text-gray-700 border-gray-300',
                                    'published' => 'bg-green-100 text-green-800 border-green-300 font-bold',
                                    'closed' => 'bg-red-100 text-red-800 border-red-300'
                                ];
                                $currentClass = $statusClasses[$test['status']] ?? 'bg-white border-gray-300';
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                    <?php echo htmlspecialchars($test['title']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-mono bg-indigo-50 text-indigo-700 px-2 py-1 rounded text-xs border border-indigo-100"><?php echo $test['invite_code']; ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <!-- FORM CẬP NHẬT TRẠNG THÁI -->
                                    <form action="update_test_status.php" method="POST" class="m-0">
                                        <input type="hidden" name="test_id" value="<?php echo $test['id']; ?>">
                                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                        <select name="status" onchange="this.form.submit()" class="text-xs rounded-full px-3 py-1 border focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer <?php echo $currentClass; ?>">
                                            <option value="draft" <?php echo $test['status'] == 'draft' ? 'selected' : ''; ?>>Nháp (Ẩn)</option>
                                            <option value="published" <?php echo $test['status'] == 'published' ? 'selected' : ''; ?>>Đang mở (Công khai)</option>
                                            <option value="closed" <?php echo $test['status'] == 'closed' ? 'selected' : ''; ?>>Đã đóng</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d/m/Y', strtotime($test['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <a href="view_results.php?test_id=<?php echo $test['id']; ?>" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded transition-colors inline-flex items-center" title="Xem kết quả">
                                        <i class="fa-solid fa-square-poll-vertical mr-1"></i> KQ
                                    </a>
                                    <a href="edit_test.php?test_id=<?php echo $test['id']; ?>" class="text-amber-600 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded transition-colors inline-flex items-center" title="Sửa bài thi">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="delete_test.php?test_id=<?php echo $test['id']; ?>&class_id=<?php echo $class['id']; ?>" onclick="return confirm('Bạn có chắc muốn xóa bài kiểm tra này?\nHành động này sẽ xóa toàn bộ kết quả của học sinh!')" class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded transition-colors inline-flex items-center" title="Xóa bài thi">
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã số sinh viên</th>
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono font-bold text-indigo-600">
                                    <?php echo htmlspecialchars($mem['student_code'] ?? '---'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($mem['email']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d/m/Y H:i', strtotime($mem['joined_at'])); ?>
                                </td>
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
        <span id="toast-msg">Đã sao chép thành công!</span>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all
            document.getElementById('content-tests').classList.add('hidden');
            document.getElementById('content-members').classList.add('hidden');
            document.getElementById('tab-tests').classList.remove('active', 'border-indigo-600', 'text-indigo-600');
            document.getElementById('tab-members').classList.remove('active', 'border-indigo-600', 'text-indigo-600');
            
            // Reset to default style
            document.getElementById('tab-tests').classList.add('text-slate-500', 'border-transparent');
            document.getElementById('tab-members').classList.add('text-slate-500', 'border-transparent');

            // Show selected
            document.getElementById('content-' + tabName).classList.remove('hidden');
            const activeBtn = document.getElementById('tab-' + tabName);
            activeBtn.classList.add('active', 'border-indigo-600', 'text-indigo-600');
            activeBtn.classList.remove('text-slate-500', 'border-transparent');
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast(`Đã sao chép mã: ${text}`);
            }).catch(() => {
                showToast('Không thể sao chép!', true);
            });
        }

        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            const msg = document.getElementById('toast-msg');
            
            msg.innerText = message;
            toast.classList.remove('translate-y-20', 'opacity-0');
            
            if(window.toastTimeout) clearTimeout(window.toastTimeout);
            
            window.toastTimeout = setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }
    </script>
</body>
</html>