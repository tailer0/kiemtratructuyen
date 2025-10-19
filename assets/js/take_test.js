    document.addEventListener("DOMContentLoaded", () => {
        const attemptId = document.body.dataset.attemptId; // Lấy ID lần làm bài từ thẻ body
        if (!attemptId) return;

        // --- 1. Giám sát chuyển tab ---
        let tabSwitchCount = 0;
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === 'hidden') {
                tabSwitchCount++;
                console.log(`Bạn đã chuyển tab ${tabSwitchCount} lần.`);
                // Gửi cảnh báo về server
                sendCheatingLog('tab_switch', `Chuyển tab lần thứ ${tabSwitchCount}`);
            }
        });

        // Hàm gửi log về server
        function sendCheatingLog(logType, details, proofImage = null) {
            const formData = new FormData();
            formData.append('attempt_id', attemptId);
            formData.append('log_type', logType);
            formData.append('details', details);
            if (proofImage) {
                formData.append('proof_image', proofImage);
            }

            fetch('/student/log_cheating.php', {
                method: 'POST',
                body: formData
            }).catch(error => console.error('Lỗi gửi log:', error));
        }


        // --- 2. Giám sát bằng Webcam (Sử dụng TensorFlow.js và MoveNet) ---
        // LƯU Ý: Phần này rất nâng cao và cần cài đặt thư viện TensorFlow.js
        // <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>
        // <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/movenet"></script>

        const video = document.getElementById('webcam');
        const canvas = document.getElementById('output');
        const ctx = canvas.getContext('2d');
        let detector;

        async function setupCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('API Camera không được trình duyệt này hỗ trợ.');
            }
            const stream = await navigator.mediaDevices.getUserMedia({
                'audio': false,
                'video': { width: 640, height: 480 },
            });
            video.srcObject = stream;
            return new Promise((resolve) => {
                video.onloadedmetadata = () => {
                    resolve(video);
                };
            });
        }

        async function loadModel() {
            const detectorConfig = { modelType: 'movenet/singlepose/lightning' };
            detector = await poseDetection.createDetector(poseDetection.SupportedModels.MoveNet, detectorConfig);
            console.log('MoveNet model loaded.');
        }

        let violationStartTime = null;
        const VIOLATION_THRESHOLD_MS = 3000; // Ngưỡng vi phạm: 3 giây

        async function detectPose() {
            if (detector) {
                const poses = await detector.estimatePoses(video);
                ctx.drawImage(video, 0, 0, 640, 480);

                if (poses.length === 0) {
                    console.warn("Không phát hiện người!");
                    handleViolation("Không phát hiện người");
                } else if (poses.length > 1) {
                    console.warn("Phát hiện nhiều hơn 1 người!");
                    handleViolation("Phát hiện nhiều người");
                } else {
                    const keypoints = poses[0].keypoints;
                    const nose = keypoints.find(k => k.name === 'nose');
                    const leftEye = keypoints.find(k => k.name === 'left_eye');
                    const rightEye = keypoints.find(k => k.name === 'right_eye');

                    // Ví dụ: Kiểm tra nếu không thấy mắt và mũi (đầu quay đi)
                    if (nose.score < 0.5 || leftEye.score < 0.5 || rightEye.score < 0.5) {
                        console.warn("Nghi ngờ quay mặt đi!");
                        handleViolation("Nghi ngờ quay mặt đi");
                    } else {
                        // Nếu không vi phạm, reset bộ đếm
                        violationStartTime = null;
                    }
                }
            }
            requestAnimationFrame(detectPose);
        }

        function handleViolation(details) {
            if (violationStartTime === null) {
                violationStartTime = Date.now();
            } else if (Date.now() - violationStartTime > VIOLATION_THRESHOLD_MS) {
                console.error(`VI PHẠM: ${details}`);
                // Chụp ảnh và gửi đi
                const proofImage = canvas.toDataURL('image/jpeg');
                sendCheatingLog('pose_violation', details, proofImage);
                // Reset để không gửi liên tục
                violationStartTime = null;
            }
        }

        // Khởi chạy
        async function main() {
             // Chỉ khởi chạy nếu có element video
            if (!video) return;
            await setupCamera();
            video.play();
            await loadModel();
            detectPose();
        }

        main();
    });
    
