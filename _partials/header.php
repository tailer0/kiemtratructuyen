<?php
// Tệp này được include ở đầu mỗi trang
// Nó sẽ hiển thị thông tin người dùng hoặc các nút đăng nhập/đăng ký
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Lấy avatar, nếu không có thì dùng ảnh mặc định
// Bạn nên tạo một file ảnh tại đường dẫn này: /assets/images/default-avatar.png
$user_avatar = '/assets/images/default-avatar.png'; 
if (!empty($_SESSION['user_avatar'])) {
    $user_avatar = htmlspecialchars($_SESSION['user_avatar']);
}
?>
<header class="app-header">
    <div class="logo">
        <a href="/" style="text-decoration: none; color: inherit;">OnlineTest</a>
    </div>
    <div class="user-info">
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Giao diện khi ĐÃ ĐĂNG NHẬP -->
            <img src="<?php echo $user_avatar; ?>" alt="Avatar" class="avatar">
            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="/auth/logout.php" class="button button-outline">Đăng xuất</a>
        <?php else: ?>
            <!-- Giao diện khi CHƯA ĐĂNG NHẬP -->
            <a href="/auth/login.php" class="button">Đăng nhập</a>
            <a href="/auth/register.php" class="button button-primary">Đăng ký</a>
        <?php endif; ?>
    </div>
</header>

