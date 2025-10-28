<?php
require_once 'config.php';
<<<<<<< HEAD

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
=======

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'student';
    if ($role === 'admin') {
        header('Location: /admin/index.php');
        exit();
    } elseif ($role === 'teacher') {
        header('Location: /teacher/index.php');
        exit();
    }
}

$login_url = isset($gClient) ? $gClient->createAuthUrl() : '#';
>>>>>>> e4761229ec738ebebd5d4d282506740a0acf0e2a
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<<<<<<< HEAD
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

=======
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OnlineTest - Hệ thống Kiểm tra Trực tuyến</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{
            --accent:#6a5acd; /* tím */
            --blue:#007bff;
            --muted:#6b7280;
            --bg:#f9fafc;
            --card:#fff;
            --radius:12px;
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--bg);color:#0f172a;overflow-x:hidden;}

        /* Hiệu ứng fade-in tổng thể */
        body.loaded main,
        body.loaded header,
        body.loaded footer {
            opacity: 1;
            transform: translateY(0);
        }

        header, main, footer {
            opacity: 0;
            transform: translateY(15px);
            transition: all 0.6s ease;
        }

        .container{max-width:1200px;margin:50px auto;padding:0 20px;display:grid;grid-template-columns:1fr 420px;gap:40px;align-items:center;}
        @media (max-width:880px){.container{grid-template-columns:1fr;}}

        /* Header */
        header{position:fixed;top:0;left:0;width:100%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.05);padding:14px 50px;display:flex;justify-content:space-between;align-items:center;z-index:10;}
        header h1{font-size:22px;font-weight:700;color:var(--accent);}
        header nav a{margin-left:18px;text-decoration:none;color:#333;font-weight:500;}
        .login-btn-top{background:var(--accent);color:#fff;padding:8px 16px;border-radius:10px;text-decoration:none;font-weight:600;transition:0.2s;}
        .login-btn-top:hover{background:#7c6df2;}

        /* Card & content */
        .card{background:var(--card);border-radius:var(--radius);box-shadow:0 6px 18px rgba(12,20,40,0.06);padding:30px;}
        h1{font-size:28px;margin-bottom:10px;}
        p.lead{font-size:18px;color:var(--blue);font-weight:500;margin-bottom:20px;}

        .features{display:flex;gap:15px;margin-top:25px;flex-wrap:wrap;}
        .feature{
            flex:1 1 30%;
            min-width:220px;
            padding:14px;
            border-radius:12px;
            background:linear-gradient(180deg,#ffffff,#f6f8ff);
            border:1px solid rgba(2,6,23,0.06);
            transition:all 0.25s ease;
            cursor:pointer;
        }
        .feature i{color:var(--accent);font-size:22px;margin-bottom:6px;transition:0.25s;}
        .feature strong{display:block;margin-bottom:4px;}
        .feature small{color:var(--muted);}

        /* Hiệu ứng hover */
        .feature:hover{
            transform:translateY(-4px) scale(1.03);
            border-color:var(--accent);
            box-shadow:0 6px 16px rgba(106,90,205,0.15);
        }
        .feature:hover i{
            color:var(--blue);
            transform:scale(1.2);
        }

        /* Right side */
        aside.card h3{margin-bottom:10px;font-size:20px;}
        .muted{color:var(--muted);font-size:14px;margin-bottom:8px;}
        .join-box{display:flex;flex-direction:column;gap:12px;}
        .join-box input{padding:12px;border-radius:10px;border:1px solid #e6e9ef;font-size:15px;}
        .btn-primary{background:var(--accent);color:#fff;padding:12px;border-radius:10px;border:none;font-weight:700;cursor:pointer;transition:0.2s;}
        .btn-primary:hover{background:#7c6df2;}
        .btn-ghost{background:transparent;border:1px solid rgba(2,6,23,0.06);padding:10px;border-radius:10px;cursor:pointer;transition:0.2s;}
        .btn-ghost:hover{border-color:var(--accent);color:var(--accent);}

        footer{text-align:center;color:var(--muted);font-size:13px;margin:30px 0;}
    </style>
</head>
<!-- Hiệu ứng nền canvas -->
<canvas id="backgroundCanvas"></canvas>

<script>
const canvas = document.getElementById("backgroundCanvas");
const ctx = canvas.getContext("2d");

function resizeCanvas() {
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
}
window.addEventListener("resize", resizeCanvas);
resizeCanvas();

const particles = [];
const numParticles = 40; // ít hạt, nhẹ và tinh tế

for (let i = 0; i < numParticles; i++) {
  particles.push({
    x: Math.random() * canvas.width,
    y: Math.random() * canvas.height,
    r: Math.random() * 2 + 1,
    dx: (Math.random() - 0.5) * 0.3,
    dy: (Math.random() - 0.5) * 0.3,
  });
}

function drawParticles() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.fillStyle = "rgba(255, 255, 255, 0.8)";

  for (let p of particles) {
    ctx.beginPath();
    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
    ctx.fill();
    p.x += p.dx;
    p.y += p.dy;

    if (p.x < 0 || p.x > canvas.width) p.dx *= -1;
    if (p.y < 0 || p.y > canvas.height) p.dy *= -1;
  }

  requestAnimationFrame(drawParticles);
}

drawParticles();

// Đặt canvas làm nền phía sau
canvas.style.position = "fixed";
canvas.style.top = "0";
canvas.style.left = "0";
canvas.style.zIndex = "-1"; // nằm sau các nội dung
canvas.style.pointerEvents = "none"; // không chặn click
canvas.style.background = "linear-gradient(120deg, #6fa3ef, #9b59b6)";
</script>

<body>
    <header>
        <h1>OnlineTest</h1>
        <nav>
            <a href="#">Về chúng tôi</a>
            <a href="#">Tính năng</a>
            <a href="#">Liên hệ</a>
            <a href="<?php echo htmlspecialchars($login_url); ?>" class="login-btn-top">Đăng nhập</a>
        </nav>
    </header>

    <main class="container" style="margin-top:100px">
        <section class="card">
            <h1>Hệ thống Kiểm tra Trực tuyến</h1>
            <p class="lead">Làm bài, giám sát và phát hiện gian lận đơn giản — an toàn, nhanh chóng và thân thiện với người dùng.</p>

            <div class="features">
                <div class="feature">
                    <i class="fa-solid fa-eye"></i>
                    <strong>Giám sát thời gian thực</strong>
                    <small>Kiểm tra chuyển tab, thoát trang và hành vi bất thường.</small>
                </div>
                <div class="feature">
                    <i class="fa-solid fa-video"></i>
                    <strong>Ghi hình & phân tích</strong>
                    <small>Kết nối camera để phân tích và lưu bằng chứng.</small>
                </div>
                <div class="feature">
                    <i class="fa-solid fa-file-lines"></i>
                    <strong>Báo cáo chi tiết</strong>
                    <small>Biên bản, nhật ký thao tác và ảnh chụp màn hình.</small>
                </div>
            </div>
        </section>

        <aside class="card">
            <h3>Vào phòng thi</h3>
            <p class="muted">Nhập mã mời do giảng viên cung cấp.</p>
            <form action="/student/join.php" method="GET" class="join-box">
                <input id="code" name="code" type="text" placeholder="VD: ABCD-1234" pattern="[A-Za-z0-9-]{4,20}" required>
                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn-primary">Vào thi</button>
                    <a class="btn-ghost" href="#" onclick="document.getElementById('code').value='';return false;">Xóa</a>
                </div>
            </form>

            <div style="margin-top:12px;font-size:13px;color:var(--muted)">
                <strong>Lưu ý:</strong>
                <div>1) Vui lòng sử dụng trình duyệt Chrome/Edge trên máy tính để có trải nghiệm tốt nhất.</div>
                <div>2) Cho phép quyền camera nếu giáo viên yêu cầu quay kiểm tra.</div>
            </div>
        </aside>
    </main>

    <footer>
        © <?php echo date('Y'); ?> OnlineTest — Phát triển bởi nhóm đồ án.
    </footer>

    <script>
        // Khi trang load xong, thêm class "loaded" để kích hoạt animation
        window.addEventListener("load", () => {
            document.body.classList.add("loaded");
        });
    </script>
</body>
</html>
>>>>>>> e4761229ec738ebebd5d4d282506740a0acf0e2a
