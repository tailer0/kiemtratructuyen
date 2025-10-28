// Chờ cho toàn bộ trang được tải xong
document.addEventListener('DOMContentLoaded', () => {
    // Lấy các phần tử HTML cần thiết
    const timerElement = document.getElementById('timer');
    const webcamElement = document.getElementById('webcam');
    const statusBox = document.getElementById('status-box');
    const captureCanvas = document.getElementById('captureCanvas');
    const testForm = document.getElementById('test-form');
    // Các phần tử mới cho luồng bắt đầu
    const startButton = document.getElementById('start-test-button');
    const testContent = document.getElementById('test-content');
    const proctoringContainer = document.getElementById('proctoring-container');
    const startOverlay = document.getElementById('start-test-overlay'); // Thêm ID cho overlay

    let model, videoInterval;
    let isSubmitting = false; // <<< KHAI BÁO BIẾN isSubmitting Ở ĐÂY

    // --- CÁC HẰNG SỐ CÓ THỂ ĐIỀU CHỈNH ĐỘ NHẠY ---
    const YAW_THRESHOLD = 20;      // Ngưỡng độ lệch trái/phải (càng nhỏ càng nhạy)
    const PITCH_THRESHOLD = 15;    // Ngưỡng độ lệch lên/xuống (càng nhỏ càng nhạy)
    const SUSPICIOUS_THRESHOLD_MS = 2000; // Thời gian duy trì hành vi đáng ngờ để ghi nhận (ms)
    const LOG_COOLDOWN_MS = 5000;  // Thời gian chờ giữa các lần ghi nhận vi phạm (ms)

    // --- PHẦN 1: ĐỒNG HỒ ĐẾM NGƯỢC VÀ GIÁM SÁT NÂNG CAO ---
    let timerInterval = null; // Khai báo timerInterval ở phạm vi rộng hơn

    // 1.1. Giám sát việc chuyển tab
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && timerInterval) {
            logCheating('switched_tab', 'Người dùng đã chuyển tab khác', null);
        }
    });

    // 1.2. Giám sát việc mất focus (nhấp ra ngoài cửa sổ, màn hình 2)
    window.addEventListener('blur', () => {
        if (timerInterval) {
            logCheating('window_blur', 'Người dùng đã nhấp ra ngoài cửa sổ bài thi.', null);
        }
    });

    // 1.3. Vô hiệu hóa Copy, Paste, Cut
    ['copy', 'paste', 'cut'].forEach(event => {
        document.addEventListener(event, (e) => {
            if (timerInterval) {
                e.preventDefault();
                logCheating('clipboard_attempt', `Cố gắng ${event} nội dung.`, null);
            }
        });
    });

    // 1.4. Vô hiệu hóa Chuột phải (Context Menu)
    document.addEventListener('contextmenu', (e) => {
        if (timerInterval) {
            e.preventDefault();
            logCheating('context_menu_attempt', 'Cố gắng mở menu chuột phải.', null);
        }
    });

    // 1.5. Giám sát các phím tắt
    document.addEventListener('keydown', (e) => {
        if (!timerInterval) return;

        if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i'))) {
            e.preventDefault();
            logCheating('devtools_key_attempt', 'Cố gắng mở Developer Tools bằng phím tắt.', null);
        }
        if (e.ctrlKey && (e.key === 'p' || e.key === 'P')) {
            e.preventDefault();
            logCheating('print_attempt', 'Cố gắng in trang bằng phím tắt.', null);
        }
    });

    // 1.6. Giám sát thoát Fullscreen
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && timerInterval) {
            logCheating('fullscreen_exit', 'Người dùng đã thoát khỏi chế độ toàn màn hình.', null);
            statusBox.textContent = "Cảnh báo: Bạn vừa thoát toàn màn hình!";
        }
    });

    // 1.7. Giám sát DevTools bằng cách check kích thước
    let devToolsCheckInterval = null;
    function checkDevTools() {
        // Chỉ kiểm tra nếu bài thi đang diễn ra
        if (!timerInterval) return;
        const widthThreshold = window.outerWidth - window.innerWidth > 160;
        const heightThreshold = window.outerHeight - window.innerHeight > 160;
        if (widthThreshold || heightThreshold) {
            logCheating('devtools_resize', 'Phát hiện DevTools có thể đang mở (thay đổi kích thước).', null);
        }
    }


    // --- PHẦN 2: KHỞI TẠO WEBCAM VÀ MÔ HÌNH AI ---

    async function setupCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240 }, audio: false });
            webcamElement.srcObject = stream;
            return new Promise((resolve) => {
                webcamElement.onloadedmetadata = () => resolve(webcamElement);
            });
        } catch (error) {
            statusBox.textContent = "Lỗi: Không thể truy cập camera.";
            console.error("Lỗi truy cập camera:", error);
            return null;
        }
    }

    async function loadModel() {
        statusBox.textContent = 'Đang tải mô hình AI...';
        try {
            const modelType = faceLandmarksDetection.SupportedModels.MediaPipeFaceMesh;
            const detectorConfig = {
                runtime: 'mediapipe', // Sửa lỗi chính tả 'mediapip' -> 'mediapipe'
                solutionPath: 'https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh',
                maxFaces: 5
            };
            model = await faceLandmarksDetection.createDetector(modelType, detectorConfig);
            statusBox.textContent = 'Hệ thống giám sát đã sẵn sàng.';
            return true;
        } catch (error) {
            statusBox.textContent = "Lỗi: Không thể tải mô hình AI.";
            console.error("Lỗi tải mô hình:", error);
            return false;
        }
    }

    // --- PHẦN 3: LOGIC PHÂN TÍCH VÀ PHÁT HIỆN GIAN LẬN ---

    let suspiciousStates = {};
    let isLogging = false;

    async function detectFaces() {
        if (!model || !webcamElement || webcamElement.readyState < 2) return; // Thêm kiểm tra webcam sẵn sàng
        try {
            const predictions = await model.estimateFaces(webcamElement, { flipHorizontal: false });

            if (predictions.length === 0) {
                statusBox.textContent = 'Cảnh báo: Không tìm thấy khuôn mặt!';
                logSuspiciousBehavior('no_face_detected', 'Không tìm thấy khuôn mặt trong khung hình.');
                return;
            }

            if (predictions.length > 1) {
                statusBox.textContent = `Cảnh báo: Phát hiện ${predictions.length} người!`;
                logSuspiciousBehavior('multiple_faces', `Phát hiện ${predictions.length} người trong khung hình.`);
                return;
            }

            const face = predictions[0];
            const keypoints = face.keypoints;

            const leftEye = keypoints.find(p => p.name === 'leftEye');
            const rightEye = keypoints.find(p => p.name === 'rightEye');
            const nose = keypoints.find(p => p.name === 'noseTip');
            // Cập nhật lấy điểm leftCheek và rightCheek chính xác hơn từ keypoints map
            const leftCheek = keypoints.find(p => p.index === 234); // Index chuẩn của MediaPipe cho má trái
            const rightCheek = keypoints.find(p => p.index === 454); // Index chuẩn của MediaPipe cho má phải

            if (!leftEye || !rightEye || !nose || !leftCheek || !rightCheek) {
                console.warn("Thiếu keypoints quan trọng.");
                return; // Bỏ qua nếu thiếu điểm mốc
            }

            const noseToLeftDist = Math.abs(nose.x - leftCheek.x);
            const noseToRightDist = Math.abs(nose.x - rightCheek.x);
            const yawRatio = (noseToLeftDist + 1) / (noseToRightDist + 1);

            const eyeMidY = (leftEye.y + rightEye.y) / 2;
            const pitchRatio = Math.abs(nose.y - eyeMidY);

            let isSuspicious = false;
            let violationType = '';
            let violationDetails = '';

            const YAW_RIGHT_THRESHOLD = 1.8;
            const YAW_LEFT_THRESHOLD = 0.5;

            if (yawRatio > YAW_RIGHT_THRESHOLD || yawRatio < YAW_LEFT_THRESHOLD) {
                violationType = 'looking_away';
                violationDetails = `Nghiêng đầu quá mức (Tỷ lệ: ${yawRatio.toFixed(2)})`;
                isSuspicious = true;
            } else if (pitchRatio > PITCH_THRESHOLD) {
                violationType = 'head_down';
                violationDetails = `Cúi đầu hoặc nhìn lên bất thường (Độ lệch: ${pitchRatio.toFixed(2)})`;
                isSuspicious = true;
            }

            if (isSuspicious) {
                statusBox.textContent = `Cảnh báo: ${violationDetails}`;
                logSuspiciousBehavior(violationType, violationDetails);
            } else {
                suspiciousStates = {};
                if (statusBox.textContent.startsWith('Cảnh báo:')) { // Chỉ cập nhật nếu đang hiển thị cảnh báo
                    statusBox.textContent = 'Hệ thống giám sát đã sẵn sàng.';
                }
            }
        } catch (error) {
            console.error("Lỗi trong quá trình detectFaces:", error);
            // Có thể tạm dừng giám sát nếu lỗi liên tục
        }
    }

    function logSuspiciousBehavior(type, details) {
        if (!suspiciousStates[type]) {
            suspiciousStates[type] = Date.now();
        }

        const suspiciousDuration = Date.now() - suspiciousStates[type];

        if (suspiciousDuration > SUSPICIOUS_THRESHOLD_MS && !isLogging) {
            isLogging = true;
            const imageData = captureFrame();
            logCheating(type, details, imageData);

            suspiciousStates = {};
            setTimeout(() => { isLogging = false; }, LOG_COOLDOWN_MS);
        }
    }

    function captureFrame() {
        try {
            const context = captureCanvas.getContext('2d');
            // Đảm bảo video đã có kích thước trước khi vẽ
            if (webcamElement.videoWidth > 0 && webcamElement.videoHeight > 0) {
                 captureCanvas.width = webcamElement.videoWidth;
                 captureCanvas.height = webcamElement.videoHeight;
                 context.drawImage(webcamElement, 0, 0, captureCanvas.width, captureCanvas.height);
                 return captureCanvas.toDataURL('image/jpeg');
            }
        } catch (e) {
            console.error("Lỗi khi chụp ảnh:", e);
            return null; // Trả về null nếu có lỗi
        }
         return null; // Trả về null nếu video chưa sẵn sàng
    }

    async function logCheating(type, details, imageData) {
        // Kiểm tra biến isSubmitting NGAY ĐẦU HÀM
        if (typeof isSubmitting !== 'undefined' && isSubmitting) {
             console.log("Đang nộp bài, bỏ qua ghi log.");
             return;
        }

        console.log(`Phát hiện gian lận: ${type} - ${details}`);
        const formData = new FormData();
        formData.append('attempt_id', ATTEMPT_ID);
        formData.append('violation_type', type);
        formData.append('details', details);
        if (imageData) {
            formData.append('screenshot', imageData);
        }

        try {
            const response = await fetch('log_cheating.php', { method: 'POST', body: formData });
             if (!response.ok) {
                 console.error(`Lỗi HTTP khi gửi log: ${response.status} ${response.statusText}`);
                 const errorText = await response.text();
                 console.error("Phản hồi lỗi từ server:", errorText);
             }
        } catch (error) {
            console.error('Lỗi mạng khi gửi log gian lận:', error);
        }
    }

    // --- PHẦN 4: HÀM KHỞI CHẠY CHÍNH ---

    async function main() {
        const cameraReady = await setupCamera();
        if (!cameraReady) {
            alert("Không thể truy cập camera. Vui lòng cấp quyền và tải lại trang để làm bài.");
            return;
        }

        const modelReady = await loadModel();
        if (!modelReady) {
             alert("Không thể tải mô hình AI. Vui lòng kiểm tra kết nối mạng và thử lại.");
             return;
        }

        startTimer();
        videoInterval = setInterval(detectFaces, 500); // Tần suất quét: 2 lần/giây
        devToolsCheckInterval = setInterval(checkDevTools, 2000);
    }

    // --- PHẦN 5: BẮT ĐẦU BÀI THI ---

    function startTimer() {
        let timeLeft = DURATION;
        timerInterval = setInterval(() => {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `Thời gian: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            if (timeLeft <= 0) {
                isSubmitting = true;
                clearInterval(timerInterval);
                if (videoInterval) clearInterval(videoInterval);
                if (devToolsCheckInterval) clearInterval(devToolsCheckInterval);
                alert('Hết giờ làm bài!');
                testForm.submit();
            }
        }, 1000);
    }

    startButton.addEventListener('click', () => {
        document.documentElement.requestFullscreen().then(() => {
            startOverlay.style.display = 'none';
            testContent.style.display = 'block';
            timerElement.style.display = 'block';
            proctoringContainer.style.display = 'block';
            main();
        }).catch(err => {
            alert(`Không thể vào chế độ toàn màn hình. Vui lòng cho phép để tiếp tục.\nLỗi: ${err.message}`);
        });
    });

    testForm.addEventListener('submit', () => {
        isSubmitting = true;
    });

});

