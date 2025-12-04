/* FILE: assets/js/exam_security.js */

// Khai báo biến toàn cục để lưu ID bài thi
let currentExamAttemptId = null;

// --- 1. HÀM KHỞI ĐỘNG GIÁM SÁT (Được gọi từ take_test.php) ---
function startExamMonitor(attemptId) {
    console.log("✅ Hệ thống giám sát đã kích hoạt. Attempt ID:", attemptId);
    currentExamAttemptId = attemptId;

    // Kích hoạt các sự kiện lắng nghe
    setupEventListeners();
}

// --- 2. THIẾT LẬP CÁC SỰ KIỆN VI PHẠM ---
function setupEventListeners() {
    // A. Rời khỏi tab (Chuyển tab / Minimize)
    document.addEventListener("visibilitychange", function() {
        if (document.hidden) {
            logViolation('switched_tab', 'Rời khỏi màn hình thi');
        }
    });

    // B. Click ra ngoài (Mất focus khỏi trình duyệt)
    window.addEventListener("blur", function() {
        // Chỉ bắt lỗi nếu không phải đang click vào các thành phần hợp lệ (như input file)
        if (document.activeElement === document.body) {
            logViolation('window_blur', 'Click ra ngoài khu vực thi');
        }
    });

    // C. Chuột phải
    document.addEventListener('contextmenu', event => {
        event.preventDefault();
        logViolation('right_click', 'Cố tình bấm chuột phải');
    });

    // D. Copy/Paste/Cut
    document.addEventListener('copy', () => logViolation('copy', 'Copy nội dung'));
    document.addEventListener('paste', () => logViolation('paste', 'Dán nội dung'));
    document.addEventListener('cut', () => logViolation('cut', 'Cut nội dung'));

    // E. Phím cấm (F12, Alt+Tab giả lập...)
    document.addEventListener('keydown', function(e) {
        // Chặn F12, Ctrl+Shift+I (DevTools), Ctrl+P (In ấn)
        if (
            e.key === "F12" || 
            (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "i" || e.key === "C" || e.key === "c")) ||
            (e.ctrlKey && (e.key === "p" || e.key === "P"))
        ) {
            e.preventDefault();
            logViolation('devtools_key_attempt', 'Cố tình sử dụng phím cấm');
        }
    });
}

// --- 3. HÀM GHI LOG (Cầu nối sang take_test.js) ---
function logViolation(type, message) {
    // Kiểm tra xem hàm logCheating bên take_test.js có tồn tại không
    // Đây là hàm chính để gửi dữ liệu về PHP và hiện Toast dưới đồng hồ
    if (typeof logCheating === 'function') {
        logCheating(type, message, null);
    } else {
        // Fallback: Nếu take_test.js lỗi hoặc chưa tải xong, dùng Popup nội bộ
        console.warn("⚠️ Không tìm thấy hàm logCheating. Dùng cảnh báo cục bộ.");
        showStudentWarning(message, -1, -1);
    }
}

// --- 4. HÀM HIỂN THỊ MÀN HÌNH ĐÌNH CHỈ (GAME OVER) ---
// Hàm này được gọi khi Server trả về status 'suspended'
function showSuspendedScreen(reason, count) {
    // Xóa toàn bộ nội dung trang web để học sinh không làm bài được nữa
    document.body.innerHTML = `
        <div style="
            position: fixed; inset: 0; background: #450a0a; color: white;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-family: sans-serif; text-align: center; z-index: 99999;
        ">
            <div style="font-size: 80px; margin-bottom: 20px;">🚫</div>
            <h1 style="font-size: 36px; font-weight: bold; color: #fca5a5; margin-bottom: 10px;">BẠN ĐÃ BỊ ĐÌNH CHỈ THI</h1>
            
            <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; max-width: 600px; margin: 20px;">
                <p style="font-size: 18px; line-height: 1.6;">
                    Hệ thống đã ghi nhận vi phạm vượt quá giới hạn cho phép.
                </p>
                <div style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 15px;">
                    <p style="color: #fca5a5; font-weight: bold; font-size: 20px;">${reason}</p>
                    <p style="font-size: 14px; color: #d1d5db; margin-top: 5px;">(Tổng số lần vi phạm: ${count})</p>
                </div>
            </div>

            <button onclick="window.location.href='/student/index.php'" style="
                margin-top: 30px; background: white; color: #7f1d1d; border: none;
                padding: 15px 40px; border-radius: 8px; font-weight: bold; font-size: 16px;
                cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s;
            " onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                QUAY VỀ TRANG CHỦ
            </button>
        </div>
    `;
}

// --- 5. HÀM HIỂN THỊ POPUP CẢNH BÁO CỤC BỘ (FALLBACK) ---
// Dùng khi take_test.js chưa load kịp hoặc chạy độc lập
function showStudentWarning(message, remaining, limit) {
    const oldPopup = document.getElementById('student-warning-modal');
    if (oldPopup) oldPopup.remove();

    let subText = "Hệ thống đã ghi nhận hành vi này.";
    let colorClass = "#f59e0b"; // Cam

    const modalHTML = `
        <div id="student-warning-modal" style="
            position: fixed; inset: 0; background-color: rgba(0,0,0,0.8); 
            z-index: 99999; display: flex; align-items: center; justify-content: center;
            font-family: Arial, sans-serif; backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease-out;
        ">
            <div style="
                background: white; width: 90%; max-width: 400px; 
                border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px rgba(0,0,0,0.2);
            ">
                <div style="background: ${colorClass}; padding: 15px; text-align: center;">
                    <div style="font-size: 40px;">⚠️</div>
                    <h2 style="color: white; margin: 10px 0 0; text-transform: uppercase;">Cảnh báo vi phạm</h2>
                </div>
                <div style="padding: 20px; text-align: center;">
                    <h3 style="color: #333; margin-bottom: 10px;">${message}</h3>
                    <p style="color: #666; font-size: 14px;">${subText}</p>
                    <button onclick="document.getElementById('student-warning-modal').remove()" style="
                        margin-top: 20px; background: ${colorClass}; color: white; border: none;
                        padding: 10px 30px; border-radius: 5px; cursor: pointer; font-weight: bold; width: 100%;
                    ">Đã hiểu</button>
                </div>
            </div>
        </div>
        <style>@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }</style>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}