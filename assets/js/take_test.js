// Chờ cho toàn bộ trang được tải xong
document.addEventListener('DOMContentLoaded', () => {
    // Lấy các phần tử HTML cần thiết
    const timerElement = document.getElementById('timer');
    const webcamElement = document.getElementById('webcam');
    const statusBox = document.getElementById('status-box');
    const captureCanvas = document.getElementById('captureCanvas');
    const testForm = document.getElementById('test-form');

    let model, videoInterval;

    // --- CÁC HẰNG SỐ CÓ THỂ ĐIỀU CHỈNH ĐỘ NHẠY ---
    const YAW_THRESHOLD = 20;       // Ngưỡng độ lệch trái/phải (càng nhỏ càng nhạy)
    const PITCH_THRESHOLD = 15;     // Ngưỡng độ lệch lên/xuống (càng nhỏ càng nhạy)
    const SUSPICIOUS_THRESHOLD_MS = 2000; // Thời gian duy trì hành vi đáng ngờ để ghi nhận (ms)
    const LOG_COOLDOWN_MS = 5000;   // Thời gian chờ giữa các lần ghi nhận vi phạm (ms)

    // --- PHẦN 1: ĐỒNG HỒ ĐẾM NGƯỢC VÀ GIÁM SÁT CHUYỂN TAB ---

    // Thiết lập đồng hồ đếm ngược
    let timeLeft = DURATION;
    const timerInterval = setInterval(() => {
        timeLeft--;
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerElement.textContent = `Thời gian: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            clearInterval(videoInterval);
            alert('Hết giờ làm bài!');
            testForm.submit();
        }
    }, 1000);

    // Giám sát việc chuyển tab
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            logCheating('switched_tab', 'Người dùng đã chuyển tab khác', null);
        }
    });

    // --- PHẦN 2: KHỞI TẠO WEBCAM VÀ MÔ HÌNH AI (ĐÃ NÂNG CẤP) ---

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
                runtime: 'mediapipe',
                solutionPath: 'https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh',
                maxFaces: 5 // Nâng cấp để phát hiện nhiều khuôn mặt
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

    // --- PHẦN 3: LOGIC PHÂN TÍCH VÀ PHÁT HIỆN GIAN LẬN (ĐÃ NÂNG CẤP CHUYÊN SÂU) ---
    
    let suspiciousStates = {};
    let isLogging = false;

    async function detectFaces() {
        if (!model) return;
        const predictions = await model.estimateFaces(webcamElement, { flipHorizontal: false });

        // Kịch bản 1: Không có ai trong khung hình
        if (predictions.length === 0) {
            statusBox.textContent = 'Cảnh báo: Không tìm thấy khuôn mặt!';
            logSuspiciousBehavior('no_face_detected', 'Không tìm thấy khuôn mặt trong khung hình.');
            return;
        }

        // Kịch bản 2: Có nhiều hơn 1 người trong khung hình
        if (predictions.length > 1) {
            statusBox.textContent = `Cảnh báo: Phát hiện ${predictions.length} người!`;
            logSuspiciousBehavior('multiple_faces', `Phát hiện ${predictions.length} người trong khung hình.`);
            return;
        }

        // Kịch bản 3: Phân tích hướng nhìn của 1 người
        const face = predictions[0];
        const keypoints = face.keypoints;

        const leftEye = keypoints.find(p => p.name === 'leftEye');
        const rightEye = keypoints.find(p => p.name === 'rightEye');
        const nose = keypoints.find(p => p.name === 'noseTip');
        const leftCheek = keypoints[234]; // Điểm mốc bên má trái
        const rightCheek = keypoints[454]; // Điểm mốc bên má phải

        if (!leftEye || !rightEye || !nose || !leftCheek || !rightCheek) return;
        
        // Tính toán Yaw (xoay trái/phải)
        const noseToLeftDist = Math.abs(nose.x - leftCheek.x);
        const noseToRightDist = Math.abs(nose.x - rightCheek.x);
        const yawRatio = noseToLeftDist / noseToRightDist;
        
        // Tính toán Pitch (ngẩng/cúi)
        const eyeMidY = (leftEye.y + rightEye.y) / 2;
        const pitchRatio = Math.abs(nose.y - eyeMidY);

        let isSuspicious = false;
        let violationType = '';
        let violationDetails = '';

        if (yawRatio > 1.8 || yawRatio < 0.5) {
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
            // Nếu không có hành vi đáng ngờ, reset tất cả các trạng thái
            suspiciousStates = {};
            statusBox.textContent = 'Hệ thống giám sát đã sẵn sàng.';
        }
    }
    
    function logSuspiciousBehavior(type, details) {
        if (!suspiciousStates[type]) {
            suspiciousStates[type] = Date.now();
        }
        
        const suspiciousDuration = Date.now() - suspiciousStates[type];
        
        if (suspiciousDuration > SUSPICIOUS_THRESHOLD_MS && !isLogging) {
            isLogging = true; // Bắt đầu quá trình ghi log
            const imageData = captureFrame();
            logCheating(type, details, imageData);
            
            // Reset tất cả trạng thái và bắt đầu cooldown
            suspiciousStates = {}; 
            setTimeout(() => { isLogging = false; }, LOG_COOLDOWN_MS);
        }
    }

    function captureFrame() {
        const context = captureCanvas.getContext('2d');
        captureCanvas.width = webcamElement.videoWidth;
        captureCanvas.height = webcamElement.videoHeight;
        context.drawImage(webcamElement, 0, 0, captureCanvas.width, captureCanvas.height);
        return captureCanvas.toDataURL('image/jpeg');
    }

    async function logCheating(type, details, imageData) {
        console.log(`Phát hiện gian lận: ${type} - ${details}`);
        const formData = new FormData();
        formData.append('attempt_id', ATTEMPT_ID);
        formData.append('violation_type', type);
        formData.append('details', details);
        if (imageData) {
            formData.append('screenshot', imageData);
        }
        
        try {
            await fetch('log_cheating.php', { method: 'POST', body: formData });
        } catch (error) {
            console.error('Lỗi khi gửi log gian lận:', error);
        }
    }

    // --- PHẦN 4: HÀM KHỞI CHẠY CHÍNH ---

    async function main() {
        const cameraReady = await setupCamera();
        if (!cameraReady) return;

        const modelReady = await loadModel();
        if (!modelReady) return;
        
        videoInterval = setInterval(detectFaces, 500); // Tần suất quét: 2 lần/giây
    }

    main();
});

