<?php
// Tệp này được include ở đầu mỗi trang cần xác thực
// Nó sẽ hiển thị thông tin người dùng và nút đăng xuất
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<header class="app-header">
    <div class="logo">OnlineTest</div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="user-info">
            <img src="<?php echo htmlspecialchars($_SESSION['user_avatar']); ?>" alt="Avatar">
            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="/auth/logout.php">Đăng xuất</a>
        </div>
    <?php endif; ?>
</header>
