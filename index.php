<?php
session_start();

// 1. Điều hướng nếu đã đăng nhập
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'];
    switch ($role) {
        case 'admin': header('Location: /admin/index.php'); exit();
        case 'teacher': header('Location: /teacher/index.php'); exit();
        case 'user': header('Location: /student/index.php'); exit();
        default: header('Location: /auth/logout.php'); exit();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlineTest AI - Nền Tảng Thi Cử Tương Lai</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Space Grotesk (Tech feel) & Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Space Grotesk', 'sans-serif'],
                    },
                    colors: {
                        dark: '#0B0C10',
                        light: '#66FCF1',
                        gray: '#C5C6C7',
                        navy: '#1F2833',
                        cyan: '#45A29E'
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        /* 1. Background Gradient Mesh */
        body {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            color: white;
            overflow-x: hidden;
        }

        /* 2. 3D TILT CARD EFFECT */
        .tilt-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.1s ease; /* Nhanh để mượt với chuột */
            transform-style: preserve-3d;
        }
        
        /* 3. SPOTLIGHT EFFECT */
        .spotlight-group:hover .spotlight-card::before {
            opacity: 1;
        }
        .spotlight-card {
            position: relative;
            overflow: hidden;
        }
        .spotlight-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(800px circle at var(--mouse-x) var(--mouse-y), rgba(255,255,255,0.1), transparent 40%);
            opacity: 0;
            transition: opacity 0.5s;
            pointer-events: none;
            z-index: 2;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #3b82f6; border-radius: 4px; }
    </style>
</head>
<body class="antialiased selection:bg-indigo-500 selection:text-white">

    <!-- NAVIGATION -->
    <nav class="fixed w-full z-50 top-0 transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-brain text-3xl text-indigo-500 animate-pulse-slow"></i>
                    <span class="font-display font-bold text-2xl tracking-tighter">OnlineTest<span class="text-indigo-500">.AI</span></span>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#features" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Tính năng</a>
                    <a href="#technology" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">Công nghệ</a>
                    <a href="/auth/login.php" class="text-sm font-medium text-white hover:text-indigo-400 transition-colors">Đăng nhập</a>
                    <a href="/auth/register.php" class="px-5 py-2.5 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition-all hover:shadow-[0_0_20px_rgba(79,70,229,0.5)] transform hover:-translate-y-0.5">
                        Đăng ký ngay
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="relative min-h-screen flex items-center justify-center pt-20 overflow-hidden">
        <!-- Animated Background Elements -->
        <div class="absolute top-20 left-10 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 animate-float"></div>
        <div class="absolute bottom-20 right-10 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 animate-float" style="animation-delay: 2s;"></div>

        <div class="container mx-auto px-6 relative z-10 text-center">
            <div data-aos="fade-up" data-aos-duration="1000">
                <span class="inline-block py-1 px-3 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-bold tracking-widest uppercase mb-4">
                    Next Gen Proctoring
                </span>
                <h1 class="text-5xl md:text-7xl font-display font-bold mb-6 leading-tight">
                    Khảo thí Thông minh <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-cyan-400">Được hỗ trợ bởi AI</span>
                </h1>
                <p class="text-lg md:text-xl text-gray-400 mb-10 max-w-2xl mx-auto">
                    Hệ thống kiểm tra trực tuyến tích hợp trí tuệ nhân tạo để giám sát hành vi, chống gian lận và đảm bảo tính công bằng tuyệt đối.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="#student-join" class="px-8 py-4 rounded-lg bg-white text-slate-900 font-bold text-lg hover:bg-gray-100 transition-all flex items-center justify-center gap-2 group">
                        <i class="fa-solid fa-play text-indigo-600 group-hover:scale-110 transition-transform"></i>
                        Vào thi ngay
                    </a>
                    <a href="#teacher-register" class="px-8 py-4 rounded-lg bg-white/5 border border-white/10 text-white font-bold text-lg hover:bg-white/10 backdrop-blur-sm transition-all">
                        Dành cho Giáo viên
                    </a>
                </div>
            </div>
            
            <!-- Dashboard Preview (3D Tilt Effect) -->
            <div class="mt-16 relative max-w-5xl mx-auto" data-aos="fade-up" data-aos-delay="200">
                <div class="tilt-card rounded-xl p-2 bg-gradient-to-b from-white/10 to-white/0">
                    <img src="https://shots.codepen.io/username/pen/abLOrwV-1280.jpg?version=1625671469" alt="Dashboard Preview" class="rounded-lg shadow-2xl w-full opacity-80 hover:opacity-100 transition-opacity duration-500">
                    <!-- Thay ảnh trên bằng ảnh screenshot ứng dụng của bạn -->
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0f172a] via-transparent to-transparent"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURES SECTION (Spotlight Effect) -->
    <section id="features" class="py-24 relative spotlight-group" onmousemove="handleMouseMove(event)">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Tính năng vượt trội</h2>
                <p class="text-gray-400">Giải quyết mọi vấn đề gian lận trong thi cử trực tuyến</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="spotlight-card tilt-card p-8 rounded-2xl group hover:bg-white/5 transition-colors" data-aos="fade-up" data-aos-delay="0">
                    <div class="w-14 h-14 bg-indigo-500/20 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fa-solid fa-eye text-2xl text-indigo-400"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-white">AI Gaze Tracking</h3>
                    <p class="text-gray-400 leading-relaxed">
                        Sử dụng MediaPipe Face Mesh để theo dõi hướng mắt và chuyển động đầu. Tự động cảnh báo khi thí sinh nhìn ra ngoài màn hình.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="spotlight-card tilt-card p-8 rounded-2xl group hover:bg-white/5 transition-colors" data-aos="fade-up" data-aos-delay="100">
                    <div class="w-14 h-14 bg-pink-500/20 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fa-solid fa-window-restore text-2xl text-pink-400"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-white">Chống Switch Tab</h3>
                    <p class="text-gray-400 leading-relaxed">
                        Hệ thống phát hiện ngay lập tức khi thí sinh chuyển tab hoặc rời khỏi chế độ toàn màn hình. Giáo viên nhận thông báo Realtime.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="spotlight-card tilt-card p-8 rounded-2xl group hover:bg-white/5 transition-colors" data-aos="fade-up" data-aos-delay="200">
                    <div class="w-14 h-14 bg-cyan-500/20 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fa-solid fa-bolt text-2xl text-cyan-400"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-white">Realtime Action</h3>
                    <p class="text-gray-400 leading-relaxed">
                        Giáo viên có thể chat cảnh báo trực tiếp hoặc đình chỉ bài thi của thí sinh vi phạm ngay lập tức thông qua Dashboard.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- TECH STACK SECTION -->
    <section id="technology" class="py-24 bg-slate-900/50 border-y border-white/5">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row items-center gap-12">
                <div class="md:w-1/2" data-aos="fade-right">
                    <h2 class="text-3xl md:text-4xl font-display font-bold mb-6">
                        Sức mạnh từ <br>
                        <span class="text-indigo-400">Công nghệ Lõi</span>
                    </h2>
                    <p class="text-gray-400 mb-6 leading-relaxed">
                        Chúng tôi không chỉ xây dựng một website, chúng tôi tích hợp những công nghệ Deep Learning tiên tiến nhất trực tiếp vào trình duyệt.
                    </p>
                    
                    <div class="space-y-4">
                        <div class="flex items-center gap-4 p-4 rounded-lg bg-white/5 border border-white/5 hover:border-indigo-500/50 transition-colors">
                            <i class="fa-brands fa-google text-3xl text-orange-400"></i>
                            <div>
                                <h4 class="font-bold">TensorFlow.js & MediaPipe</h4>
                                <p class="text-xs text-gray-500">Xử lý AI trực tiếp trên trình duyệt Client, độ trễ thấp.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 p-4 rounded-lg bg-white/5 border border-white/5 hover:border-indigo-500/50 transition-colors">
                            <i class="fa-brands fa-php text-3xl text-indigo-400"></i>
                            <div>
                                <h4 class="font-bold">PHP 8 & MySQL (PDO)</h4>
                                <p class="text-xs text-gray-500">Backend bảo mật, xử lý dữ liệu Realtime với AJAX Polling.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 p-4 rounded-lg bg-white/5 border border-white/5 hover:border-indigo-500/50 transition-colors">
                            <i class="fa-solid fa-wind text-3xl text-cyan-400"></i>
                            <div>
                                <h4 class="font-bold">Tailwind CSS</h4>
                                <p class="text-xs text-gray-500">Giao diện Responsive, hiện đại và tối ưu trải nghiệm.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Visual Tech Representation -->
                <div class="md:w-1/2 relative" data-aos="fade-left">
                    <div class="relative z-10 bg-gradient-to-tr from-slate-800 to-slate-900 rounded-2xl p-8 border border-white/10 shadow-2xl">
                        <div class="flex items-center justify-between mb-6 border-b border-white/10 pb-4">
                            <span class="text-xs font-mono text-green-400">● System Online</span>
                            <i class="fa-solid fa-code text-gray-500"></i>
                        </div>
                        <div class="font-mono text-sm space-y-2 text-gray-300">
                            <p><span class="text-purple-400">const</span> <span class="text-blue-400">detector</span> = <span class="text-purple-400">await</span> faceLandmarksDetection.<span class="text-yellow-400">createDetector</span>(...);</p>
                            <p><span class="text-purple-400">if</span> (faces.<span class="text-blue-400">length</span> > 1) {</p>
                            <p class="pl-4"><span class="text-red-400">logViolation</span>(<span class="text-green-300">'multiple_faces'</span>);</p>
                            <p class="pl-4"><span class="text-yellow-400">sendAlertToTeacher</span>();</p>
                            <p>}</p>
                        </div>
                    </div>
                    <!-- Decor -->
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-indigo-600/30 rounded-full blur-3xl"></div>
                    <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-cyan-600/30 rounded-full blur-3xl"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- JOIN TEST SECTION (Student) -->
    <section id="student-join" class="py-24">
        <div class="container mx-auto px-6 text-center">
            <div class="max-w-2xl mx-auto bg-gradient-to-b from-slate-800 to-slate-900 p-10 rounded-3xl border border-white/10 shadow-2xl relative overflow-hidden" data-aos="zoom-in">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
                
                <h2 class="text-3xl font-bold mb-2">Bạn là Học viên?</h2>
                <p class="text-gray-400 mb-8">Nhập mã mời để tham gia phòng thi ngay lập tức.</p>
                
                <form action="/student/join_test.php" method="POST" class="flex flex-col sm:flex-row gap-4">
                    <input type="text" name="invite_code" placeholder="CODE: 123456" class="flex-1 bg-white/5 border border-white/10 rounded-lg px-6 py-4 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all text-center sm:text-left font-mono uppercase tracking-widest">
                    <button type="submit" class="px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold transition-all shadow-lg hover:shadow-indigo-500/25 whitespace-nowrap">
                        Vào thi <i class="fa-solid fa-arrow-right ml-2"></i>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- TEACHER REGISTRATION SECTION -->
    <section id="teacher-register" class="py-24 relative overflow-hidden">
        <div class="absolute inset-0 bg-indigo-900/20"></div>
        <div class="container mx-auto px-6 relative z-10">
            <div class="flex flex-col md:flex-row items-center justify-between gap-12">
                <div class="md:w-1/2" data-aos="fade-up">
                    <span class="text-indigo-400 font-bold tracking-wider uppercase text-sm mb-2 block">Dành cho Giáo viên</span>
                    <h2 class="text-4xl md:text-5xl font-display font-bold mb-6">Kiến tạo kỳ thi <br>Chuyên nghiệp</h2>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-center gap-3 text-gray-300"><i class="fa-solid fa-check-circle text-green-400"></i> Tạo đề thi nhanh chóng từ file Word</li>
                        <li class="flex items-center gap-3 text-gray-300"><i class="fa-solid fa-check-circle text-green-400"></i> Quản lý lớp học và học sinh dễ dàng</li>
                        <li class="flex items-center gap-3 text-gray-300"><i class="fa-solid fa-check-circle text-green-400"></i> Xuất báo cáo kết quả chi tiết</li>
                    </ul>
                    <a href="/auth/register.php?role=teacher" class="inline-block px-8 py-4 bg-white text-indigo-900 rounded-lg font-bold hover:bg-gray-100 transition-all shadow-[0_0_30px_rgba(255,255,255,0.3)]">
                        Đăng ký Tài khoản Giáo viên
                    </a>
                </div>
                <div class="md:w-1/2" data-aos="zoom-in-left">
                    <!-- Decorative Element -->
                    <div class="relative">
                        <div class="absolute -inset-1 bg-gradient-to-r from-pink-600 to-purple-600 rounded-2xl blur opacity-75 animate-pulse"></div>
                        <div class="relative bg-slate-900 rounded-2xl p-8 border border-white/10">
                            <div class="flex items-center gap-4 mb-6">
                                <div class="w-12 h-12 rounded-full bg-indigo-500 flex items-center justify-center font-bold text-xl">GV</div>
                                <div>
                                    <h4 class="font-bold">Cô Nguyễn Thị A</h4>
                                    <p class="text-xs text-green-400">● Đang giám sát 45 học sinh</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="h-2 bg-white/10 rounded w-3/4"></div>
                                <div class="h-2 bg-white/10 rounded w-1/2"></div>
                                <div class="mt-4 p-3 bg-red-500/10 border border-red-500/20 rounded flex items-center gap-3">
                                    <i class="fa-solid fa-triangle-exclamation text-red-500"></i>
                                    <span class="text-sm text-red-200">Phát hiện 1 học sinh chuyển tab!</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-slate-950 border-t border-white/5 pt-16 pb-8">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="fa-solid fa-brain text-2xl text-indigo-500"></i>
                        <span class="font-display font-bold text-xl">OnlineTest.AI</span>
                    </div>
                    <p class="text-gray-500 max-w-xs">
                        Nền tảng thi cử trực tuyến hàng đầu, ứng dụng công nghệ AI để mang lại sự công bằng cho giáo dục.
                    </p>
                </div>
                <div>
                    <h4 class="font-bold mb-4">Liên kết</h4>
                    <ul class="space-y-2 text-gray-500 text-sm">
                        <li><a href="#" class="hover:text-white transition-colors">Trang chủ</a></li>
                        <li><a href="#features" class="hover:text-white transition-colors">Tính năng</a></li>
                        <li><a href="/auth/login.php" class="hover:text-white transition-colors">Đăng nhập</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold mb-4">Liên hệ</h4>
                    <ul class="space-y-2 text-gray-500 text-sm">
                        <li><i class="fa-solid fa-envelope mr-2"></i> contact@onlinetest.ai</li>
                        <li><i class="fa-solid fa-phone mr-2"></i> (+84) 123 456 789</li>
                    </ul>
                </div>
            </div>
            <div class="text-center text-gray-600 text-sm border-t border-white/5 pt-8">
                &copy; 2025 OnlineTest AI. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- SCRIPTS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- Vanilla Tilt JS for 3D effect -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.2/vanilla-tilt.min.js"></script>

    <script>
        // 1. Initialize AOS Animation
        AOS.init({
            once: true,
            offset: 100,
            duration: 800,
        });

        // 2. Initialize Vanilla Tilt for 3D Cards
        VanillaTilt.init(document.querySelectorAll(".tilt-card"), {
            max: 15, // Độ nghiêng tối đa
            speed: 400,
            glare: true,
            "max-glare": 0.2,
            scale: 1.02
        });

        // 3. Mouse Spotlight Effect Logic
        function handleMouseMove(e) {
            const cards = document.querySelectorAll(".spotlight-card");
            for(const card of cards) {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                card.style.setProperty("--mouse-x", `${x}px`);
                card.style.setProperty("--mouse-y", `${y}px`);
            }
        }

        // 4. Navbar Scroll Effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('bg-slate-900/80', 'backdrop-blur-md', 'shadow-lg');
            } else {
                navbar.classList.remove('bg-slate-900/80', 'backdrop-blur-md', 'shadow-lg');
            }
        });
    </script>
</body>
</html>