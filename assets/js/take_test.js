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
            alert('Hết giờ làm bài!');
            testForm.submit();
        }
    }, 1000);

    // Giám sát việc chuyển tab
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            logCheating('switched_tab', null); // Gửi log gian lận khi chuyển tab
        }
    });

    // --- PHẦN 2: KHỞI TẠO WEBCAM VÀ MÔ HÌNH AI ---

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
            // Tải mô hình nhận diện điểm mốc khuôn mặt
            model = await faceLandmarksDetection.load(
                faceLandmarksDetection.SupportedPackages.mediapipeFacemesh,
                { maxFaces: 1 }
            );
            statusBox.textContent = 'Hệ thống giám sát đã sẵn sàng.';
            return true;
        } catch (error) {
            statusBox.textContent = "Lỗi: Không thể tải mô hình AI.";
            console.error("Lỗi tải mô hình:", error);
            return false;
        }
    }

    // --- PHẦN 3: LOGIC PHÂN TÍCH VÀ PHÁT HIỆN GIAN LẬN ---
    
    let suspiciousStartTime = null; // Thời điểm bắt đầu hành vi đáng ngờ
    const SUSPICIOUS_THRESHOLD_MS = 3000; // Ngưỡng thời gian cho hành vi đáng ngờ (3 giây)
    let isLogging = false; // Cờ để tránh gửi log liên tục

    async function detectFaces() {
        const predictions = await model.estimateFaces({ input: webcamElement });

        if (predictions.length === 0) {
            // Không tìm thấy khuôn mặt
            statusBox.textContent = 'Cảnh báo: Không tìm thấy khuôn mặt!';
            logSuspiciousBehavior('no_face_detected');
            return;
        }

        const face = predictions[0];
        const keypoints = face.scaledMesh;
        
        // Các điểm mốc quan trọng trên khuôn mặt
        const nose = keypoints[4];      // Chóp mũi
        const leftEye = keypoints[130]; // Góc mắt trái
        const rightEye = keypoints[359];// Góc mắt phải

        // Tính toán độ nghiêng của đầu
        const eyeMidpoint = [(leftEye[0] + rightEye[0]) / 2, (leftEye[1] + rightEye[1]) / 2];
        const angle = Math.atan2(nose[1] - eyeMidpoint[1], nose[0] - eyeMidpoint[0]) * 180 / Math.PI;

        // Phát hiện hành vi gian lận
        let isSuspicious = false;
        let violationType = '';

        if (Math.abs(angle) > 110 || Math.abs(angle) < 70) {
            // Đầu quay sang trái hoặc phải quá nhiều
            statusBox.textContent = 'Cảnh báo: Nhìn ra ngoài!';
            violationType = 'looking_away';
            isSuspicious = true;
        } else if (angle > 95) {
             // Đầu cúi xuống quá thấp
            statusBox.textContent = 'Cảnh báo: Cúi đầu quá thấp!';
            violationType = 'head_down';
            isSuspicious = true;
        }

        if (isSuspicious) {
            logSuspiciousBehavior(violationType);
        } else {
            // Nếu không còn đáng ngờ, reset bộ đếm
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
            
            // Reset bộ đếm và cờ sau khi gửi log
            suspiciousStartTime = null; 
            setTimeout(() => { isLogging = false; }, 5000); // Chờ 5 giây trước khi cho phép log tiếp
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
        
        // Bắt đầu vòng lặp giám sát, chạy 2 lần mỗi giây
        videoInterval = setInterval(detectFaces, 500);
    }

    // Bắt đầu chạy
    main();
});

