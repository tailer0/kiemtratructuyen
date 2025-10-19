// Chờ cho toàn bộ trang được tải xong
document.addEventListener('DOMContentLoaded', () => {
    // Lấy các phần tử HTML cần thiết
    const timerElement = document.getElementById('timer');
    const webcamElement = document.getElementById('webcam');
    const statusBox = document.getElementById('status-box');
    const captureCanvas = document.getElementById('captureCanvas');
    const testForm = document.getElementById('test-form');

    let model, videoInterval;

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
            clearInterval(videoInterval); // Dừng giám sát khi hết giờ
            alert('Hết giờ làm bài!');
            testForm.submit();
        }
    }, 1000);

    // Giám sát việc chuyển tab
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            logCheating('switched_tab', null);
        }
    });

    // --- PHẦN 2: KHỞI TẠO WEBCAM VÀ MÔ HÌNH AI (ĐÃ CẬP NHẬT) ---

    async function setupCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { width: 320, height: 240 },
                audio: false
            });
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
            // SỬ DỤNG API MỚI ĐỂ TẢI MODEL
            const modelType = faceLandmarksDetection.SupportedModels.MediaPipeFaceMesh;
            const detectorConfig = {
                runtime: 'mediapipe',
                solutionPath: 'https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh',
                maxFaces: 1
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

    // --- PHẦN 3: LOGIC PHÂN TÍCH VÀ PHÁT HIỆN GIAN LẬN (ĐÃ CẬP NHẬT) ---
    
    let suspiciousStartTime = null;
    const SUSPICIOUS_THRESHOLD_MS = 3000;
    let isLogging = false;

    async function detectFaces() {
        if (!model) return;
        const predictions = await model.estimateFaces(webcamElement);

        if (predictions.length === 0) {
            statusBox.textContent = 'Cảnh báo: Không tìm thấy khuôn mặt!';
            logSuspiciousBehavior('no_face_detected');
            return;
        }

        const face = predictions[0];
        // CẬP NHẬT CÁCH TRUY CẬP KEYPOINTS
        const keypoints = face.keypoints;
        
        const nose = keypoints.find(p => p.name === 'noseTip');
        const leftEye = keypoints.find(p => p.name === 'leftEye');
        const rightEye = keypoints.find(p => p.name === 'rightEye');

        if (!nose || !leftEye || !rightEye) {
             // Nếu không tìm thấy các điểm quan trọng, bỏ qua khung hình này
            return;
        }

        // CẬP NHẬT CÁCH TÍNH TOÁN VỊ TRÍ
        const eyeMidpoint = { x: (leftEye.x + rightEye.x) / 2, y: (leftEye.y + rightEye.y) / 2 };
        const angle = Math.atan2(nose.y - eyeMidpoint.y, nose.x - eyeMidpoint.x) * 180 / Math.PI;

        let isSuspicious = false;
        let violationType = '';

        if (Math.abs(angle) > 110 || Math.abs(angle) < 70) {
            statusBox.textContent = 'Cảnh báo: Nhìn ra ngoài!';
            violationType = 'looking_away';
            isSuspicious = true;
        } else if (angle > 95) {
            statusBox.textContent = 'Cảnh báo: Cúi đầu quá thấp!';
            violationType = 'head_down';
            isSuspicious = true;
        }

        if (isSuspicious) {
            logSuspiciousBehavior(violationType);
        } else {
            suspiciousStartTime = null;
            statusBox.textContent = 'Hệ thống giám sát đã sẵn sàng.';
        }
    }
    
    function logSuspiciousBehavior(violationType) {
        if (suspiciousStartTime === null) {
            suspiciousStartTime = Date.now();
        }
        
        const suspiciousDuration = Date.now() - suspiciousStartTime;
        
        if (suspiciousDuration > SUSPICIOUS_THRESHOLD_MS && !isLogging) {
            isLogging = true;
            const imageData = captureFrame();
            logCheating(violationType, imageData);
            
            suspiciousStartTime = null; 
            setTimeout(() => { isLogging = false; }, 5000);
        }
    }

    function captureFrame() {
        const context = captureCanvas.getContext('2d');
        captureCanvas.width = webcamElement.videoWidth;
        captureCanvas.height = webcamElement.videoHeight;
        context.drawImage(webcamElement, 0, 0, captureCanvas.width, captureCanvas.height);
        return captureCanvas.toDataURL('image/jpeg');
    }

    async function logCheating(type, imageData) {
        console.log(`Phát hiện gian lận: ${type}`);
        const formData = new FormData();
        formData.append('attempt_id', ATTEMPT_ID);
        formData.append('violation_type', type);
        if (imageData) {
            formData.append('screenshot', imageData);
        }
        
        try {
            await fetch('log_cheating.php', {
                method: 'POST',
                body: formData
            });
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
        
        videoInterval = setInterval(detectFaces, 500);
    }

    main();
});

