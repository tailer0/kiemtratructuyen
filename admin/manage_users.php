<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: /index.php');
    exit();
}

// Xử lý Xóa
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    // Không cho xóa chính mình
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: manage_users.php');
    exit();
}

// Xử lý Tìm kiếm & Lọc
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$sql = "SELECT * FROM users WHERE (name LIKE ? OR email LIKE ?)";
$params = ["%$search%", "%$search%"];

if ($role_filter) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Người dùng - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-900 text-slate-100 h-screen flex overflow-hidden">

    <!-- SIDEBAR (Giống file index.php) -->
    <aside class="w-64 bg-slate-800 border-r border-slate-700 hidden md:flex flex-col z-10">
        <div class="h-16 flex items-center px-6 border-b border-slate-700">
            <i class="fa-solid fa-shield-cat text-indigo-500 text-2xl mr-2"></i>
            <span class="text-xl font-bold text-white">Admin<span class="text-indigo-500">Panel</span></span>
        </div>
        <nav class="flex-1 py-6 px-3 space-y-1">
            <a href="index.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-gauge-high w-6 text-center mr-2"></i> Dashboard
            </a>
            <a href="manage_users.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg bg-indigo-600 text-white shadow-lg shadow-indigo-500/20">
                <i class="fa-solid fa-users-gear w-6 text-center mr-2"></i> Quản lý Users
            </a>
            <a href="monitor_cheating.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-triangle-exclamation w-6 text-center mr-2"></i> Giám sát Vi phạm
            </a>
            <a href="test_quality.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-chart-line w-6 text-center mr-2"></i> Phân tích Đề thi
            </a>
            <a href="settings.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fa-solid fa-cog w-6 text-center mr-2"></i> Cài đặt Hệ thống
            </a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden relative bg-slate-900">
        <header class="h-16 bg-slate-800 border-b border-slate-700 flex items-center justify-between px-6 shadow-md">
            <h1 class="text-lg font-bold text-white">Quản lý Người dùng</h1>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            
            <!-- Search & Filter Toolbar -->
            <div class="bg-slate-800 p-4 rounded-xl border border-slate-700 mb-6 flex flex-col md:flex-row gap-4 justify-between items-center">
                <form class="flex gap-2 w-full md:w-auto" method="GET">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-500"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm tên, email..." class="bg-slate-900 border border-slate-600 text-white pl-10 pr-4 py-2 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none w-full md:w-64">
                    </div>
                    <select name="role" class="bg-slate-900 border border-slate-600 text-white px-4 py-2 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer" onchange="this.form.submit()">
                        <option value="">Tất cả vai trò</option>
                        <option value="user" <?php if($role_filter == 'user') echo 'selected'; ?>>Học sinh</option>
                        <option value="teacher" <?php if($role_filter == 'teacher') echo 'selected'; ?>>Giáo viên</option>
                        <option value="admin" <?php if($role_filter == 'admin') echo 'selected'; ?>>Admin</option>
                    </select>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors">Lọc</button>
                </form>
                
                <div class="text-sm text-slate-400">
                    Tìm thấy <strong class="text-white"><?php echo count($users); ?></strong> kết quả
                </div>
            </div>

            <!-- User Table -->
            <div class="bg-slate-800 rounded-2xl border border-slate-700 shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-slate-400">
                        <thead class="bg-slate-700/50 text-slate-200 uppercase text-xs font-bold">
                            <tr>
                                <th class="px-6 py-4">Người dùng</th>
                                <th class="px-6 py-4">Email</th>
                                <th class="px-6 py-4">Vai trò</th>
                                <th class="px-6 py-4">Thông tin thêm</th>
                                <th class="px-6 py-4 text-right">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700">
                            <?php foreach ($users as $u): 
                                $role_bg = $u['role'] == 'admin' ? 'bg-red-500/10 text-red-400' : ($u['role'] == 'teacher' ? 'bg-indigo-500/10 text-indigo-400' : 'bg-emerald-500/10 text-emerald-400');
                            ?>
                            <tr class="hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-slate-600 flex items-center justify-center text-white font-bold mr-3 overflow-hidden">
                                            <?php if($u['avatar']): ?>
                                                <img src="<?php echo htmlspecialchars($u['avatar']); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <?php echo substr($u['name'], 0, 1); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="font-bold text-white"><?php echo htmlspecialchars($u['name']); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?php echo $role_bg; ?>">
                                        <?php echo $u['role']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <?php if($u['student_code']): ?>
                                        <div class="mb-1"><span class="text-slate-500">MSSV:</span> <?php echo htmlspecialchars($u['student_code']); ?></div>
                                    <?php endif; ?>
                                    <div><span class="text-slate-500">Ngày tạo:</span> <?php echo date('d/m/Y', strtotime($u['created_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if($u['role'] != 'admin'): ?>
                                    <a href="manage_users.php?delete_id=<?php echo $u['id']; ?>" onclick="return confirm('Bạn có chắc muốn xóa tài khoản này? Dữ liệu liên quan sẽ bị xóa vĩnh viễn!')" class="text-slate-400 hover:text-red-500 transition-colors p-2" title="Xóa">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>