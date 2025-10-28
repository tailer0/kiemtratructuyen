<?php
require_once 'config.php';
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'];
    
    if ($role == 'admin') {
        header('Location: /admin/index.php');
        exit();
    } elseif ($role == 'teacher') {
        header('Location: /teacher/index.php');
        exit();
    } elseif ($role == 'user') { // 'user' là vai trò của học sinh
        // Chuyển học sinh đã đăng nhập đến thẳng trang nhập mã
        header('Location: student/join_test.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlineTest - Hệ thống Kiểm tra Trực tuyến Chuyên nghiệp</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    
</head>
<body>
    
    <!-- Header (Đã có sẵn logic Đăng nhập/Đăng ký) -->
    <?php include ROOT_PATH . '/_partials/header.php';?>

    <main class="main-content">

        <!-- Phần Hero Section -->
        <section class="hero-section container">
            <h1>
                Hệ thống Kiểm tra <span>Trực tuyến</span> Chuyên nghiệp
            </h1>
            <p class="subtitle">
                Giải pháp toàn diện ứng dụng <span>AI</span> để đảm bảo tính minh bạch,
                uy tín và chất lượng cho mọi kỳ thi.
            </p>
            <a href="/join_test.php" class="button">Tham gia Phòng thi Ngay</a>
        </section>

        <!-- Phần Giới thiệu Công nghệ -->
        <section class="features-section container">
            <div class="features-grid">
                
                <!-- Card 1: Giám sát AI (Thêm class "animate-on-scroll") -->
                <div class="feature-card animate-on-scroll">
                    <div class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </div>
                    <h3>Giám sát AI Nâng cao</h3>
                    <p>
                        Sử dụng TensorFlow.js và MediaPipe, hệ thống phân tích thời gian thực
                        hướng nhìn, góc quay đầu và phát hiện nhiều khuôn mặt trong khung hình
                        để đảm bảo người thi luôn tập trung.
                    </p>
                </div>

                <!-- Card 2: Chống Gian lận (Thêm class "animate-on-scroll") -->
                <div class="feature-card animate-on-scroll">
                    <div class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><shield d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></shield></svg>
                    </div>
                    <h3>Chống Gian lận Đa lớp</h3>
                    <p>
                        Ngoài giám sát AI, hệ thống tự động ghi nhận các hành vi đáng ngờ như
                        chuyển tab, rời khỏi màn hình thi. Mọi vi phạm đều được chụp ảnh
                        và báo cáo chi tiết cho giáo viên.
                    </p>
                </div>

                <!-- Card 3: Quản lý Toàn diện (Thêm class "animate-on-scroll") -->
                <div class="feature-card animate-on-scroll">
                    <div class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                    <h3>Quản lý Linh hoạt</h3>
                    <p>
                        Phân quyền rõ ràng cho Admin, Giáo viên và Học sinh. Giáo viên
                        dễ dàng tạo đề thi, quản lý phòng thi, công bố bài và
                        xem lại kết quả kèm bằng chứng gian lận.
                    </p>
                </div>

            </div>
        </section>

        <!-- === PHẦN MỚI: LUỒNG HOẠT ĐỘNG === -->
        <section class="how-it-works-section container">
            <h2>Luồng hoạt động <span>đơn giản</span></h2>
            <p class="subtitle">Chỉ với 3 bước để có một kỳ thi an toàn và minh bạch.</p>
            <div class="how-it-works-grid">
                
                <!-- Bước 1 -->
                <div class="step-card animate-on-scroll">
                    <div class="step-number">1</div>
                    <div class="step-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                    </div>
                    <h3>Giáo viên Tạo đề</h3>
                    <p>Giáo viên đăng nhập, tạo bài kiểm tra, thêm câu hỏi và thiết lập thời gian.</p>
                </div>

                <!-- Bước 2 -->
                <div class="step-card animate-on-scroll">
                    <div class="step-number">2</div>
                    <div class="step-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1.67l-2.83 8.16L1 12l8.17 2.17L12 22.33l2.83-8.16L23 12l-8.17-2.17z"></path></svg>
                    </div>
                    <h3>Sinh viên Làm bài</h3>
                    <p>Sinh viên dùng mã mời để vào phòng thi và làm bài dưới sự giám sát của AI.</p>
                </div>

                <!-- Bước 3 -->
                <div class="step-card animate-on-scroll">
                    <div class="step-number">3</div>
                    <div class="step-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>
                    </div>
                    <h3>Xem Kết quả & Bằng chứng</h3>
                    <p>Giáo viên nhận kết quả, xem lại tỷ lệ gian lận và các hình ảnh bằng chứng chi tiết.</p>
                </div>

            </div>
        </section>
        <!-- === HẾT PHẦN MỚI === -->

    </main>


    <!-- Bao gồm Footer chung -->
    <?php include ROOT_PATH . '/_partials/footer.php';?>

    <script src="/assets/js/main.js"></script>
</body>
</html>
  