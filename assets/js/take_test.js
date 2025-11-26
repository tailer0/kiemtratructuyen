// Enhanced AI Proctoring System - OPTIMIZED FOR FAST LOADING
// Face detection only, object detection optional/lazy loaded

document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements
    const timerElement = document.getElementById('timer');
    const webcamElement = document.getElementById('webcam');
    const statusBox = document.getElementById('status-box');
    const captureCanvas = document.getElementById('captureCanvas');
    const testForm = document.getElementById('test-form');
    const startButton = document.getElementById('start-test-button');
    const testContent = document.getElementById('test-content');
    const proctoringContainer = document.getElementById('proctoring-container');
    const startOverlay = document.getElementById('start-test-overlay');

    // AI Models
    let faceMeshModel;
    let cocoSsdModel = null; // Will be loaded lazily
    let videoInterval, objectDetectionInterval;
    let isSubmitting = false;

    // ============================================
    // OPTIMIZED CONFIGURATION
    // ============================================
    
    const CONFIG = {
        // Face Detection - Intelligent Calibration
        face: {
            yawThreshold: 40,
            pitchDownThreshold: 30,
            pitchUpThreshold: 25,
            minViolationDuration: 2500,
            consecutiveFramesRequired: 5,
            recoveryFrames: 3,
            smoothingWindow: 5,
            outlierThreshold: 2.5,
        },
        
        noFace: {
            duration: 6000,
            warningDuration: 3000,
        },
        
        multipleFace: {
            duration: 4000,
            confidenceThreshold: 0.7,
        },
        
        object: {
            enabled: false, // Start with face detection only
            phoneConfidence: 0.6,
            phoneDuration: 2500,
            scanInterval: 2000,
            bookConfidence: 0.65,
        },
        
        logCooldown: 10000,
        detectionInterval: 500,
    };

    // ============================================
    // TRACKING STATE
    // ============================================
    
    const state = {
        violations: {
            looking_away: {
                count: 0,
                startTime: null,
                frames: [],
                active: false
            },
            head_down: {
                count: 0,
                startTime: null,
                frames: [],
                active: false
            },
            head_up: {
                count: 0,
                startTime: null,
                frames: [],
                active: false
            }
        },
        
        measurements: {
            yaw: [],
            pitch: [],
            roll: []
        },
        
        face: {
            lastSeenTime: Date.now(),
            normalFrames: 0,
            isPresent: false,
            count: 0
        },
        
        noFaceStartTime: null,
        multipleFaceStartTime: null,
        phoneDetectionStartTime: null,
        lastLogTime: {},
        
        calibration: {
            isCalibrated: false,
            samples: [],
            neutralYaw: 0,
            neutralPitch: 0,
            samplesNeeded: 30
        }
    };

    // ============================================
    // SYSTEM MONITORING
    // ============================================
    
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
            logCheating('devtools_key_attempt', 'Cố gắng mở Developer Tools.', null);
        }
        if (e.ctrlKey && (e.key === 'p' || e.key === 'P')) {
            e.preventDefault();
            logCheating('print_attempt', 'Cố gắng in trang.', null);
        }
    });

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && timerInterval) {
            logCheating('fullscreen_exit', 'Thoát toàn màn hình.', null);
        }
    });

    // ============================================
    // CAMERA & MODEL INITIALIZATION - OPTIMIZED
    // ============================================

    async function setupCamera() {
        try {
            statusBox.textContent = 'Đang khởi động camera...';
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    frameRate: { ideal: 24 }
                }, 
                audio: false 
            });
            webcamElement.srcObject = stream;
            return new Promise((resolve) => {
                webcamElement.onloadedmetadata = () => {
                    console.log('✓ Camera ready');
                    resolve(webcamElement);
                };
            });
        } catch (error) {
            statusBox.textContent = "Không thể truy cập camera";
            console.error("Camera error:", error);
            return null;
        }
    }

    async function loadModels() {
        statusBox.textContent = 'Đang tải AI Face Detection (10-20s)...';
        
        try {
            // Only load Face Mesh initially - it's faster
            console.log('Loading Face Mesh model...');
            
            const faceModelType = faceLandmarksDetection.SupportedModels.MediaPipeFaceMesh;
            const faceDetectorConfig = {
                runtime: 'mediapipe',
                solutionPath: 'https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh',
                maxFaces: 3,
                refineLandmarks: false, // Faster without refinement
                minDetectionConfidence: 0.6,
                minTrackingConfidence: 0.6
            };
            
            faceMeshModel = await faceLandmarksDetection.createDetector(faceModelType, faceDetectorConfig);
            console.log('Face Mesh loaded successfully');

            statusBox.textContent = 'AI sẵn sàng - Đang hiệu chuẩn...';
            
            // Load object detection in background (optional)
            loadObjectDetectionLazy();
            
            return true;
        } catch (error) {
            statusBox.textContent = "Lỗi tải AI model";
            console.error("Model load error:", error);
            alert(`Lỗi tải AI model: ${error.message}\n\nVui lòng:\n1. Kiểm tra kết nối internet\n2. Tải lại trang (Ctrl+F5)\n3. Thử trình duyệt khác (Chrome/Edge)`);
            return false;
        }
    }

    // Load object detection in background (non-blocking)
    async function loadObjectDetectionLazy() {
        try {
            console.log('Loading object detection in background...');
            
            // Check if cocoSsd is available
            if (typeof cocoSsd === 'undefined') {
                console.warn('COCO-SSD library not loaded, skipping object detection');
                return;
            }
            
            cocoSsdModel = await cocoSsd.load();
            CONFIG.object.enabled = true;
            console.log('Object Detection loaded (background)');
            
            // Start object detection if test has started
            if (timerInterval && !objectDetectionInterval) {
                objectDetectionInterval = setInterval(detectObjects, CONFIG.object.scanInterval);
            }
        } catch (error) {
            console.warn('Object detection disabled:', error.message);
            CONFIG.object.enabled = false;
        }
    }

    // ============================================
    // ADVANCED FACE ANALYSIS
    // ============================================

    function calibrateNeutralPosition(yaw, pitch) {
        if (state.calibration.isCalibrated) return;

        state.calibration.samples.push({ yaw, pitch });

        if (state.calibration.samples.length >= state.calibration.samplesNeeded) {
            const avgYaw = state.calibration.samples.reduce((sum, s) => sum + s.yaw, 0) / state.calibration.samples.length;
            const avgPitch = state.calibration.samples.reduce((sum, s) => sum + s.pitch, 0) / state.calibration.samples.length;
            
            state.calibration.neutralYaw = avgYaw;
            state.calibration.neutralPitch = avgPitch;
            state.calibration.isCalibrated = true;
            
            console.log(`Hiệu chuẩn hoàn tất - Neutral: Yaw=${avgYaw.toFixed(2)}, Pitch=${avgPitch.toFixed(2)}`);
            statusBox.textContent = 'Hệ thống giám sát đã sẵn sàng';
        } else {
            const progress = Math.round((state.calibration.samples.length / state.calibration.samplesNeeded) * 100);
            statusBox.textContent = `Hiệu chuẩn... ${progress}%`;
        }
    }

    function smoothMeasurement(type, value) {
        const buffer = state.measurements[type];
        buffer.push(value);
        
        if (buffer.length > CONFIG.face.smoothingWindow) {
            buffer.shift();
        }

        const sorted = [...buffer].sort((a, b) => a - b);
        const mid = Math.floor(sorted.length / 2);
        return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
    }

    function calculateHeadPose(keypoints) {
        const leftEye = keypoints.find(p => p.name === 'leftEye');
        const rightEye = keypoints.find(p => p.name === 'rightEye');
        const nose = keypoints.find(p => p.name === 'noseTip');
        const leftCheek = keypoints.find(p => p.index === 234);
        const rightCheek = keypoints.find(p => p.index === 454);
        const chin = keypoints.find(p => p.index === 152);
        const forehead = keypoints.find(p => p.index === 10);

        if (!leftEye || !rightEye || !nose || !leftCheek || !rightCheek) {
            return null;
        }

        // Calculate YAW
        const noseToLeftDist = Math.abs(nose.x - leftCheek.x);
        const noseToRightDist = Math.abs(nose.x - rightCheek.x);
        const rawYaw = Math.atan2(noseToLeftDist - noseToRightDist, noseToLeftDist + noseToRightDist) * (180 / Math.PI);
        const yaw = smoothMeasurement('yaw', rawYaw);

        // Calculate PITCH
        const eyeMidY = (leftEye.y + rightEye.y) / 2;
        const faceHeight = chin && forehead ? Math.abs(chin.y - forehead.y) : 100;
        const rawPitch = ((nose.y - eyeMidY) / faceHeight) * 100;
        const pitch = smoothMeasurement('pitch', rawPitch);

        // Calculate ROLL
        const eyeDeltaY = rightEye.y - leftEye.y;
        const eyeDeltaX = rightEye.x - leftEye.x;
        const rawRoll = Math.atan2(eyeDeltaY, eyeDeltaX) * (180 / Math.PI);
        const roll = smoothMeasurement('roll', rawRoll);

        return { yaw, pitch, roll };
    }

    function analyzeViolation(pose) {
        if (!state.calibration.isCalibrated) {
            calibrateNeutralPosition(pose.yaw, pose.pitch);
            return null;
        }

        const yawDeviation = Math.abs(pose.yaw - state.calibration.neutralYaw);
        const pitchDeviation = pose.pitch - state.calibration.neutralPitch;

        let violation = null;

        const YAW_RIGHT_THRESHOLD = 2.0;
        const YAW_LEFT_THRESHOLD = 0.45;

        if (yawDeviation > CONFIG.face.yawThreshold) {
            violation = {
                type: 'looking_away',
                severity: yawDeviation > CONFIG.face.yawThreshold * 1.5 ? 'high' : 'medium',
                details: `Quay đầu ${pose.yaw > state.calibration.neutralYaw ? 'phải' : 'trái'} (${yawDeviation.toFixed(1)}°)`,
                value: yawDeviation
            };
        } else if (pitchDeviation > CONFIG.face.pitchDownThreshold) {
            violation = {
                type: 'head_down',
                severity: pitchDeviation > CONFIG.face.pitchDownThreshold * 1.3 ? 'high' : 'medium',
                details: `Cúi đầu xuống (${pitchDeviation.toFixed(1)}°)`,
                value: pitchDeviation
            };
        } else if (pitchDeviation < -CONFIG.face.pitchUpThreshold) {
            violation = {
                type: 'head_up',
                severity: pitchDeviation < -CONFIG.face.pitchUpThreshold * 1.3 ? 'high' : 'medium',
                details: `Ngẩng đầu lên (${Math.abs(pitchDeviation).toFixed(1)}°)`,
                value: Math.abs(pitchDeviation)
            };
        }

        return violation;
    }

    function processViolation(violation) {
        if (!violation) {
            Object.keys(state.violations).forEach(key => {
                const v = state.violations[key];
                v.frames.push(false);
                
                if (v.frames.length > CONFIG.face.smoothingWindow) {
                    v.frames.shift();
                }
                
                const recentNormalCount = v.frames.slice(-CONFIG.face.recoveryFrames).filter(f => !f).length;
                if (recentNormalCount === CONFIG.face.recoveryFrames) {
                    v.active = false;
                    v.count = 0;
                    v.startTime = null;
                }
            });
            
            state.face.normalFrames++;
            if (state.face.normalFrames > 10 && statusBox.textContent.includes('⚠️')) {
                statusBox.textContent = 'Tư thế bình thường';
            }
            return;
        }

        state.face.normalFrames = 0;
        const v = state.violations[violation.type];
        v.frames.push(true);
        
        if (v.frames.length > CONFIG.face.smoothingWindow) {
            v.frames.shift();
        }

        const recentViolationCount = v.frames.slice(-CONFIG.face.consecutiveFramesRequired).filter(f => f).length;
        
        if (!v.active && recentViolationCount === CONFIG.face.consecutiveFramesRequired) {
            v.active = true;
            v.startTime = Date.now();
            v.count++;
        }

        if (v.active) {
            const duration = Date.now() - v.startTime;
            statusBox.textContent = `⚠️ ${violation.details} (${(duration/1000).toFixed(1)}s)`;
            
            if (duration > CONFIG.face.minViolationDuration && canLogViolation(violation.type)) {
                const imageData = captureFrame();
                logCheating(
                    violation.type,
                    `${violation.details} - Kéo dài ${(duration/1000).toFixed(1)}s (Mức độ: ${violation.severity})`,
                    imageData
                );
            }
        }
    }

    async function detectFaces() {
        if (!faceMeshModel || !webcamElement || webcamElement.readyState < 2) return;
        
        try {
            const predictions = await faceMeshModel.estimateFaces(webcamElement, { flipHorizontal: false });

            state.face.count = predictions.length;
            state.face.isPresent = predictions.length > 0;

            // No face
            if (predictions.length === 0) {
                if (!state.noFaceStartTime) {
                    state.noFaceStartTime = Date.now();
                }
                
                const duration = Date.now() - state.noFaceStartTime;
                
                if (duration > CONFIG.noFace.warningDuration) {
                    statusBox.textContent = `Không tìm thấy khuôn mặt (${Math.floor(duration/1000)}s)`;
                }
                
                if (duration > CONFIG.noFace.duration && canLogViolation('no_face_detected')) {
                    const imageData = captureFrame();
                    logCheating('no_face_detected', `Không tìm thấy khuôn mặt trong ${Math.floor(duration/1000)}s`, imageData);
                }
                return;
            }
            
            state.noFaceStartTime = null;

            // Multiple faces
            if (predictions.length > 1) {
                if (!state.multipleFaceStartTime) {
                    state.multipleFaceStartTime = Date.now();
                }
                
                const duration = Date.now() - state.multipleFaceStartTime;
                statusBox.textContent = `Phát hiện ${predictions.length} người (${Math.floor(duration/1000)}s)`;
                
                if (duration > CONFIG.multipleFace.duration && canLogViolation('multiple_faces')) {
                    const imageData = captureFrame();
                    logCheating('multiple_faces', `Phát hiện ${predictions.length} người trong ${Math.floor(duration/1000)}s`, imageData);
                }
                return;
            }
            
            state.multipleFaceStartTime = null;

            // Analyze pose
            const face = predictions[0];
            const pose = calculateHeadPose(face.keypoints);
            
            if (pose) {
                const violation = analyzeViolation(pose);
                processViolation(violation);
            }

        } catch (error) {
            console.error("Face detection error:", error);
        }
    }

    // ============================================
    // OBJECT DETECTION (Optional)
    // ============================================

    async function detectObjects() {
        if (!CONFIG.object.enabled || !cocoSsdModel || !webcamElement || webcamElement.readyState < 2) return;
        
        try {
            const predictions = await cocoSsdModel.detect(webcamElement);
            
            const suspiciousObjects = predictions.filter(pred => {
                const label = pred.class.toLowerCase();
                return (
                    (label.includes('phone') || label === 'cell phone') && pred.score > CONFIG.object.phoneConfidence ||
                    label === 'book' && pred.score > CONFIG.object.bookConfidence ||
                    label === 'laptop' && pred.score > 0.65
                );
            });

            if (suspiciousObjects.length > 0) {
                const phoneDetected = suspiciousObjects.some(obj => obj.class.toLowerCase().includes('phone'));

                if (phoneDetected) {
                    if (!state.phoneDetectionStartTime) {
                        state.phoneDetectionStartTime = Date.now();
                    }

                    const duration = Date.now() - state.phoneDetectionStartTime;
                    
                    if (duration > CONFIG.object.phoneDuration) {
                        statusBox.textContent = `Phát hiện sử dụng điện thoại!`;
                        
                        if (canLogViolation('phone_detected')) {
                            const imageData = captureFrame();
                            const details = suspiciousObjects.map(obj => 
                                `${obj.class} (${(obj.score * 100).toFixed(0)}%)`
                            ).join(', ');
                            
                            logCheating('phone_detected', `Vật dụng không được phép: ${details}`, imageData);
                        }
                    }
                }
            } else {
                state.phoneDetectionStartTime = null;
            }

        } catch (error) {
            console.error("Object detection error:", error);
        }
    }

    // ============================================
    // HELPER FUNCTIONS
    // ============================================

    function canLogViolation(type) {
        if (isSubmitting) return false;
        
        const lastLog = state.lastLogTime[type] || 0;
        const timeSince = Date.now() - lastLog;
        
        if (timeSince > CONFIG.logCooldown) {
            state.lastLogTime[type] = Date.now();
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
                return captureCanvas.toDataURL('image/jpeg', 0.85);
            }
        } catch (e) {
            console.error("Capture error:", e);
        }
        return null;
    }

    async function logCheating(type, details, imageData) {
        if (isSubmitting) return;

        console.log(`📸 Violation logged: ${type} - ${details}`);
        
        const formData = new FormData();
        formData.append('attempt_id', ATTEMPT_ID);
        formData.append('violation_type', type);
        formData.append('details', details);
        if (imageData) {
            formData.append('screenshot', imageData);
        }

        try {
            const response = await fetch('log_cheating.php', { 
                method: 'POST', 
                body: formData 
            });
            
            if (!response.ok) {
                console.error(`HTTP error: ${response.status}`);
            }
        } catch (error) {
            console.error('Network error:', error);
        }
    }

    // ============================================
    // MAIN INITIALIZATION
    // ============================================

    async function main() {
        console.log('Starting proctoring system...');
        
        const cameraReady = await setupCamera();
        if (!cameraReady) {
            alert("Không thể truy cập camera. Vui lòng cấp quyền và tải lại.");
            return;
        }

        const modelsReady = await loadModels();
        if (!modelsReady) {
            return; // Alert already shown in loadModels
        }

        startTimer();
        
        // Start face detection
        videoInterval = setInterval(detectFaces, CONFIG.detectionInterval);
        
        // Object detection will start automatically when loaded
        
        console.log('✓ Proctoring system active');
        console.log('📊 Face detection: ACTIVE');
        console.log('📊 Object detection: Loading in background...');
    }

    // ============================================
    // TIMER & FORM HANDLING
    // ============================================

    function startTimer() {
        let timeLeft = DURATION;
        timerInterval = setInterval(() => {
            timeLeft--;
            const hours = Math.floor(timeLeft / 3600);
            const minutes = Math.floor((timeLeft % 3600) / 60);
            const seconds = timeLeft % 60;
            
            let timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            if (hours > 0) timeString = `${hours}:${timeString}`;
            
            timerElement.textContent = timeString;
            
            if (timeLeft <= 300) {
                timerElement.style.background = '#e74c3c';
                timerElement.style.color = 'white';
            }

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
            alert(`❌ Không thể vào toàn màn hình: ${err.message}`);
        });
    });

    testForm.addEventListener('submit', () => {
        isSubmitting = true;
    });
});