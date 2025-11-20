// Chờ cho toàn bộ trang được tải xong
document.addEventListener('DOMContentLoaded', () => {
    // Lấy các phần tử HTML cần thiết
    const timerElement = document.getElementById('timer');
    const webcamElement = document.getElementById('webcam');
    const statusBox = document.getElementById('status-box');
    const captureCanvas = document.getElementById('captureCanvas');
    const testForm = document.getElementById('test-form');
    const startButton = document.getElementById('start-test-button');
    const testContent = document.getElementById('test-content');
    const proctoringContainer = document.getElementById('proctoring-container');
    const startOverlay = document.getElementById('start-test-overlay');

    let faceMeshModel, cocoSsdModel, videoInterval, objectDetectionInterval;
    let isSubmitting = false;

    // --- CÁC HẰNG SỐ ĐIỀU CHỈNH ĐỘ NHẠY (ĐÃ CẢI TIẾN) ---
    // Thông số cho Face Tracking - THOẢI MÁI HƠN
    const YAW_THRESHOLD = 35;           // Tăng từ 20 -> 35 (cho phép quay đầu tự nhiên hơn)
    const PITCH_DOWN_THRESHOLD = 25;    // Tăng từ 15 -> 25 (cho phép nhìn xuống bài nhiều hơn)
    const PITCH_UP_THRESHOLD = 20;      // Ngưỡng riêng cho nhìn lên
    const CONSECUTIVE_VIOLATIONS = 4;    // Số lần vi phạm liên tiếp mới cảnh báo (thay vì 1 lần)
    const VIOLATION_RESET_TIME = 3000;  // Reset bộ đếm vi phạm sau 3s (nếu không vi phạm)
    
    // Thông số cho No Face Detection - THOẢI MÁI HƠN
    const NO_FACE_DURATION = 5000;      // Tăng từ 2s -> 5s (cho phép rời khỏi camera lâu hơn)
    const MULTIPLE_FACE_DURATION = 3000; // 3s có nhiều người mới cảnh báo
    
    // Thông số cho Object Detection
    const PHONE_CONFIDENCE = 0.5;       // Độ tin cậy tối thiểu để phát hiện điện thoại
    const PHONE_DETECTION_DURATION = 2000; // 2s liên tục phát hiện phone mới cảnh báo
    const OBJECT_SCAN_INTERVAL = 1000;  // Quét object mỗi 1s (tiết kiệm tài nguyên)
    
    const LOG_COOLDOWN_MS = 8000;       // Tăng từ 5s -> 8s giữa các lần ghi log

    // --- TRACKING STATES ---
    let violationCounter = {
        looking_away: 0,
        head_down: 0,
        head_up: 0
    };
    let lastNormalTime = Date.now();
    let noFaceStartTime = null;
    let multipleFaceStartTime = null;
    let phoneDetectionStartTime = null;
    let lastLogTime = {};
    let isLogging = false;

    // --- PHẦN 1: GIÁM SÁT HỆ THỐNG (GIỮ NGUYÊN) ---
    let timerInterval = null;

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && timerInterval) {
            logCheating('switched_tab', 'Người dùng đã chuyển tab khác', null);
        }
    });

    window.addEventListener('blur', () => {
        if (timerInterval) {
            logCheating('window_blur', 'Người dùng đã nhấp ra ngoài cửa sổ bài thi.', null);
        }
    });

    ['copy', 'paste', 'cut'].forEach(event => {
        document.addEventListener(event, (e) => {
            if (timerInterval) {
                e.preventDefault();
                logCheating('clipboard_attempt', `Cố gắng ${event} nội dung.`, null);
            }
        });
    });

    document.addEventListener('contextmenu', (e) => {
        if (timerInterval) {
            e.preventDefault();
            logCheating('context_menu_attempt', 'Cố gắng mở menu chuột phải.', null);
        }
    });

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

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && timerInterval) {
            logCheating('fullscreen_exit', 'Người dùng đã thoát khỏi chế độ toàn màn hình.', null);
            statusBox.textContent = "Cảnh báo: Bạn vừa thoát toàn màn hình!";
        }
    });

    let devToolsCheckInterval = null;
    function checkDevTools() {
        if (!timerInterval) return;
        const widthThreshold = window.outerWidth - window.innerWidth > 160;
        const heightThreshold = window.outerHeight - window.innerHeight > 160;
        if (widthThreshold || heightThreshold) {
            logCheating('devtools_resize', 'Phát hiện DevTools có thể đang mở.', null);
        }
    }

    // --- PHẦN 2: KHỞI TẠO WEBCAM VÀ MÔ HÌNH AI ---

    async function setupCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { width: 640, height: 480 }, // Tăng độ phân giải cho object detection
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

    async function loadModels() {
        statusBox.textContent = 'Đang tải mô hình AI nâng cao...';
        try {
            // Load Face Mesh Model
            const faceModelType = faceLandmarksDetection.SupportedModels.MediaPipeFaceMesh;
            const faceDetectorConfig = {
                runtime: 'mediapipe',
                solutionPath: 'https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh',
                maxFaces: 3
            };
            faceMeshModel = await faceLandmarksDetection.createDetector(faceModelType, faceDetectorConfig);
            console.log('✓ Face Mesh model loaded');

            // Load COCO-SSD Model for Object Detection
            cocoSsdModel = await cocoSsd.load();
            console.log('✓ COCO-SSD model loaded');

            statusBox.textContent = 'Hệ thống giám sát AI đã sẵn sàng (Face + Object Detection).';
            return true;
        } catch (error) {
            statusBox.textContent = "Lỗi: Không thể tải mô hình AI.";
            console.error("Lỗi tải mô hình:", error);
            return false;
        }
    }

    // --- PHẦN 3: FACE DETECTION VỚI LOGIC THÔNG MINH HƠN ---

    async function detectFaces() {
        if (!faceMeshModel || !webcamElement || webcamElement.readyState < 2) return;
        
        try {
            const predictions = await faceMeshModel.estimateFaces(webcamElement, { flipHorizontal: false });

            // Case 1: Không tìm thấy khuôn mặt
            if (predictions.length === 0) {
                if (!noFaceStartTime) {
                    noFaceStartTime = Date.now();
                }
                
                const noFaceDuration = Date.now() - noFaceStartTime;
                if (noFaceDuration > NO_FACE_DURATION) {
                    statusBox.textContent = `⚠️ Không tìm thấy khuôn mặt (${Math.floor(noFaceDuration/1000)}s)`;
                    if (canLogViolation('no_face_detected')) {
                        const imageData = captureFrame();
                        logCheating('no_face_detected', `Không tìm thấy khuôn mặt trong ${Math.floor(noFaceDuration/1000)} giây.`, imageData);
                    }
                }
                return;
            } else {
                noFaceStartTime = null; // Reset no face timer
            }

            // Case 2: Phát hiện nhiều người
            if (predictions.length > 1) {
                if (!multipleFaceStartTime) {
                    multipleFaceStartTime = Date.now();
                }
                
                const multipleFaceDuration = Date.now() - multipleFaceStartTime;
                if (multipleFaceDuration > MULTIPLE_FACE_DURATION) {
                    statusBox.textContent = `⚠️ Phát hiện ${predictions.length} người!`;
                    if (canLogViolation('multiple_faces')) {
                        const imageData = captureFrame();
                        logCheating('multiple_faces', `Phát hiện ${predictions.length} người trong khung hình.`, imageData);
                    }
                }
                return;
            } else {
                multipleFaceStartTime = null; // Reset multiple face timer
            }

            // Case 3: Phân tích tư thế khuôn mặt (1 người)
            const face = predictions[0];
            const keypoints = face.keypoints;

            const leftEye = keypoints.find(p => p.name === 'leftEye');
            const rightEye = keypoints.find(p => p.name === 'rightEye');
            const nose = keypoints.find(p => p.name === 'noseTip');
            const leftCheek = keypoints.find(p => p.index === 234);
            const rightCheek = keypoints.find(p => p.index === 454);

            if (!leftEye || !rightEye || !nose || !leftCheek || !rightCheek) {
                return;
            }

            // Tính toán các chỉ số tư thế
            const noseToLeftDist = Math.abs(nose.x - leftCheek.x);
            const noseToRightDist = Math.abs(nose.x - rightCheek.x);
            const yawRatio = (noseToLeftDist + 1) / (noseToRightDist + 1);

            const eyeMidY = (leftEye.y + rightEye.y) / 2;
            const pitchOffset = nose.y - eyeMidY;

            // Phát hiện vi phạm với ngưỡng mới
            let violation = null;

            const YAW_RIGHT_THRESHOLD = 2.0;  // Thoải mái hơn
            const YAW_LEFT_THRESHOLD = 0.45;  // Thoải mái hơn

            if (yawRatio > YAW_RIGHT_THRESHOLD || yawRatio < YAW_LEFT_THRESHOLD) {
                violation = {
                    type: 'looking_away',
                    details: `Quay đầu sang ngang (Tỷ lệ: ${yawRatio.toFixed(2)})`,
                    severity: Math.abs(yawRatio - 1.0) > 1.5 ? 'high' : 'medium'
                };
            } else if (pitchOffset > PITCH_DOWN_THRESHOLD) {
                violation = {
                    type: 'head_down',
                    details: `Cúi đầu xuống (Độ lệch: ${pitchOffset.toFixed(2)})`,
                    severity: pitchOffset > 35 ? 'high' : 'medium'
                };
            } else if (pitchOffset < -PITCH_UP_THRESHOLD) {
                violation = {
                    type: 'head_up',
                    details: `Ngẩng đầu lên (Độ lệch: ${pitchOffset.toFixed(2)})`,
                    severity: pitchOffset < -30 ? 'high' : 'medium'
                };
            }

            // Xử lý vi phạm với bộ đếm
            if (violation) {
                violationCounter[violation.type]++;
                
                // Chỉ cảnh báo sau khi vi phạm liên tiếp
                if (violationCounter[violation.type] >= CONSECUTIVE_VIOLATIONS) {
                    statusBox.textContent = `⚠️ ${violation.details}`;
                    
                    if (canLogViolation(violation.type)) {
                        const imageData = captureFrame();
                        logCheating(
                            violation.type, 
                            `${violation.details} (Mức độ: ${violation.severity})`, 
                            imageData
                        );
                    }
                }
                
                lastNormalTime = Date.now();
            } else {
                // Reset bộ đếm nếu đã ở tư thế bình thường đủ lâu
                if (Date.now() - lastNormalTime > VIOLATION_RESET_TIME) {
                    violationCounter = {
                        looking_away: 0,
                        head_down: 0,
                        head_up: 0
                    };
                    if (statusBox.textContent.startsWith('⚠️')) {
                        statusBox.textContent = '✓ Tư thế bình thường';
                    }
                }
                lastNormalTime = Date.now();
            }

        } catch (error) {
            console.error("Lỗi trong detectFaces:", error);
        }
    }

    // --- PHẦN 4: OBJECT DETECTION (MỚI) ---

    async function detectObjects() {
        if (!cocoSsdModel || !webcamElement || webcamElement.readyState < 2) return;
        
        try {
            const predictions = await cocoSsdModel.detect(webcamElement);
            
            // Tìm các object đáng ngờ
            const suspiciousObjects = predictions.filter(pred => {
                const label = pred.class.toLowerCase();
                return (
                    (label === 'cell phone' || label === 'phone') && pred.score > PHONE_CONFIDENCE ||
                    label === 'book' && pred.score > 0.6 ||
                    label === 'laptop' && pred.score > 0.6
                );
            });

            if (suspiciousObjects.length > 0) {
                const phoneDetected = suspiciousObjects.some(obj => 
                    obj.class.toLowerCase().includes('phone')
                );

                if (phoneDetected) {
                    if (!phoneDetectionStartTime) {
                        phoneDetectionStartTime = Date.now();
                    }

                    const phoneDuration = Date.now() - phoneDetectionStartTime;
                    
                    if (phoneDuration > PHONE_DETECTION_DURATION) {
                        statusBox.textContent = `🚨 Phát hiện điện thoại trong tay!`;
                        
                        if (canLogViolation('phone_detected')) {
                            const imageData = captureFrame();
                            const objectDetails = suspiciousObjects.map(obj => 
                                `${obj.class} (${(obj.score * 100).toFixed(0)}%)`
                            ).join(', ');
                            
                            logCheating(
                                'phone_detected',
                                `Phát hiện vật dụng không được phép: ${objectDetails}`,
                                imageData
                            );
                        }
                    }
                } else {
                    // Phát hiện vật khác (sách, laptop...)
                    if (canLogViolation('suspicious_object')) {
                        statusBox.textContent = `⚠️ Phát hiện vật dụng đáng ngờ`;
                        const imageData = captureFrame();
                        const objectDetails = suspiciousObjects.map(obj => 
                            `${obj.class} (${(obj.score * 100).toFixed(0)}%)`
                        ).join(', ');
                        
                        logCheating(
                            'suspicious_object',
                            `Phát hiện: ${objectDetails}`,
                            imageData
                        );
                    }
                }
            } else {
                phoneDetectionStartTime = null;
            }

        } catch (error) {
            console.error("Lỗi trong detectObjects:", error);
        }
    }

    // --- PHẦN 5: HELPER FUNCTIONS ---

    function canLogViolation(type) {
        if (isLogging) return false;
        
        const lastLog = lastLogTime[type] || 0;
        const timeSinceLastLog = Date.now() - lastLog;
        
        if (timeSinceLastLog > LOG_COOLDOWN_MS) {
            lastLogTime[type] = Date.now();
            return true;
        }
        return false;
    }

    function captureFrame() {
        try {
            const context = captureCanvas.getContext('2d');
            if (webcamElement.videoWidth > 0 && webcamElement.videoHeight > 0) {
                captureCanvas.width = webcamElement.videoWidth;
                captureCanvas.height = webcamElement.videoHeight;
                context.drawImage(webcamElement, 0, 0, captureCanvas.width, captureCanvas.height);
                return captureCanvas.toDataURL('image/jpeg', 0.8);
            }
        } catch (e) {
            console.error("Lỗi khi chụp ảnh:", e);
            return null;
        }
        return null;
    }

    async function logCheating(type, details, imageData) {
        if (isSubmitting) {
            console.log("Đang nộp bài, bỏ qua ghi log.");
            return;
        }

        console.log(`📸 Phát hiện vi phạm: ${type} - ${details}`);
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
                console.error(`Lỗi HTTP: ${response.status}`);
            }
        } catch (error) {
            console.error('Lỗi mạng khi gửi log:', error);
        }
    }

    // --- PHẦN 6: HÀM KHỞI CHẠY CHÍNH ---

    async function main() {
        const cameraReady = await setupCamera();
        if (!cameraReady) {
            alert("Không thể truy cập camera. Vui lòng cấp quyền và tải lại trang.");
            return;
        }

        const modelsReady = await loadModels();
        if (!modelsReady) {
            alert("Không thể tải mô hình AI. Vui lòng kiểm tra kết nối mạng.");
            return;
        }

        startTimer();
        
        // Face detection chạy thường xuyên hơn (mỗi 600ms)
        videoInterval = setInterval(detectFaces, 600);
        
        // Object detection chạy ít hơn để tiết kiệm tài nguyên (mỗi 1s)
        objectDetectionInterval = setInterval(detectObjects, OBJECT_SCAN_INTERVAL);
        
        devToolsCheckInterval = setInterval(checkDevTools, 2000);

        console.log('🚀 Hệ thống giám sát AI đã khởi động');
    }

    // --- PHẦN 7: TIMER VÀ FORM SUBMISSION ---

    function startTimer() {
        let timeLeft = DURATION;
        timerInterval = setInterval(() => {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `Thời gian: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            if (timeLeft <= 0) {
                endTest();
            }
        }, 1000);
    }

    function endTest() {
        isSubmitting = true;
        clearInterval(timerInterval);
        if (videoInterval) clearInterval(videoInterval);
        if (objectDetectionInterval) clearInterval(objectDetectionInterval);
        if (devToolsCheckInterval) clearInterval(devToolsCheckInterval);
        alert('Hết giờ làm bài!');
        testForm.submit();
    }

    startButton.addEventListener('click', () => {
        document.documentElement.requestFullscreen().then(() => {
            startOverlay.style.display = 'none';
            testContent.style.display = 'block';
            timerElement.style.display = 'block';
            proctoringContainer.style.display = 'block';
            main();
        }).catch(err => {
            alert(`Không thể vào chế độ toàn màn hình. Lỗi: ${err.message}`);
        });
    });

    testForm.addEventListener('submit', () => {
        isSubmitting = true;
    });

});