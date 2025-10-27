<?php
require_once 'config.php';

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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
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
