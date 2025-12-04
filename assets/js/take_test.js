// Enhanced AI Proctoring System - ALL IN ONE
// Bao gồm: AI Detect + Chặn chuột/Phím/Tab + Popup Cảnh báo

// --- BIẾN TOÀN CỤC ---
window.isSubmitting = false;

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
    let cocoSsdModel = null;
    let videoInterval, objectDetectionInterval;

    // ============================================
    // 1. CONFIGURATION
    // ============================================
    const CONFIG = {
        face: {
            yawThreshold: 40, pitchDownThreshold: 30, pitchUpThreshold: 25,
            minViolationDuration: 2500, // 2.5s mới bắt lỗi
            detectionInterval: 500,
        },
        noFace: { duration: 6000, warningDuration: 3000 },
        multipleFace: { duration: 4000 },
        object: { enabled: false, scanInterval: 2000 },
        logCooldown: 5000, // Giãn cách log lỗi (5s)
    };

    // ============================================
    // 2. STATE MANAGEMENT
    // ============================================
    const state = {
        face: { lastSeen: Date.now(), startTime: null },
        lastLogTime: {},
        calibration: { isCalibrated: false, samples: [], neutralYaw: 0, neutralPitch: 0 }
    };

    // ============================================
    // 3. SECURITY EVENTS (CHẶN MOUSE, KEY, TAB)
    // ============================================
    function setupSecurityListeners() {
        console.log("🛡️ Đang kích hoạt lá chắn bảo mật...");

        // A. Chặn chuột phải
        document.addEventListener('contextmenu', event => {
            event.preventDefault();
            window.logCheating('right_click', 'Cố tình bấm chuột phải', null);
        });

        // B. Chặn Copy/Paste/Cut
        ['copy', 'paste', 'cut'].forEach(evt => {
            document.addEventListener(evt, (e) => {
                e.preventDefault();
                window.logCheating('copy_paste', `Thao tác ${evt}`, null);
            });
        });

        // C. Phát hiện chuyển Tab / Thu nhỏ
        document.addEventListener("visibilitychange", () => {
            if (document.hidden) {
                window.logCheating('switched_tab', 'Rời khỏi màn hình thi', null);
            }
        });

        // D. Phát hiện click ra ngoài (Mất focus)
        window.addEventListener("blur", () => {
            if (document.activeElement === document.body) {
                window.logCheating('window_blur', 'Click ra ngoài khu vực thi', null);
            }
        });

        // E. Chặn phím cấm (F12, Ctrl+P, PrintScreen...)
        document.addEventListener('keydown', function(e) {
            if (
                e.key === "F12" || 
                (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "i" || e.key === "C" || e.key === "c")) ||
                (e.ctrlKey && (e.key === "p" || e.key === "P" || e.key === "u" || e.key === "U"))
            ) {
                e.preventDefault();
                window.logCheating('devtools_key_attempt', 'Sử dụng phím tắt cấm', null);
            }
        });
    }

    // ============================================
    // 4. CAMERA & AI LOGIC
    // ============================================
    async function setupCamera() {
        try {
            statusBox.textContent = 'Đang khởi động camera...';
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { width: { ideal: 640 }, height: { ideal: 480 } }, audio: false 
            });
            webcamElement.srcObject = stream;
            return new Promise(resolve => webcamElement.onloadedmetadata = () => resolve(webcamElement));
        } catch (error) {
            statusBox.textContent = "Không thể truy cập camera";
            return null;
        }
    }

    async function loadModels() {
        statusBox.textContent = 'Đang tải AI...';
        try {
            const model = faceLandmarksDetection.SupportedModels.MediaPipeFaceMesh;
            const config = {
                runtime: 'mediapipe', solutionPath: 'https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh',
                maxFaces: 3, refineLandmarks: false
            };
            faceMeshModel = await faceLandmarksDetection.createDetector(model, config);
            
            // Load Object Detection ẩn (Lazy load)
            (async () => {
                try {
                    if (typeof cocoSsd !== 'undefined') {
                        cocoSsdModel = await cocoSsd.load();
                        CONFIG.object.enabled = true;
                    }
                } catch(e) {}
            })();

            statusBox.textContent = 'Hệ thống sẵn sàng';
            return true;
        } catch (error) {
            statusBox.textContent = "Lỗi tải AI";
            alert("Lỗi tải AI Model. Vui lòng tải lại trang.");
            return false;
        }
    }

    // --- AI: Face Logic ---
    async function detectFaces() {
        if (!faceMeshModel || !webcamElement || webcamElement.readyState < 2) return;
        
        try {
            const predictions = await faceMeshModel.estimateFaces(webcamElement, { flipHorizontal: false });

            // 1. Không thấy mặt
            if (predictions.length === 0) {
                if (!state.face.startTime) state.face.startTime = Date.now();
                const duration = Date.now() - state.face.startTime;
                
                if (duration > CONFIG.noFace.warningDuration) statusBox.textContent = "⚠️ Không tìm thấy khuôn mặt";
                if (duration > CONFIG.noFace.duration && canLog('no_face_detected')) {
                    window.logCheating('no_face_detected', `Mất mặt ${Math.round(duration/1000)}s`, captureFrame());
                }
                return;
            }
            state.face.startTime = null;

            // 2. Nhiều người
            if (predictions.length > 1) {
                statusBox.textContent = `⚠️ Phát hiện ${predictions.length} người`;
                if (canLog('multiple_faces')) {
                    window.logCheating('multiple_faces', `Phát hiện ${predictions.length} người`, captureFrame());
                }
                return;
            }

            // 3. Hướng nhìn (Head Pose)
            const keypoints = predictions[0].keypoints;
            const pose = calculateHeadPose(keypoints);
            if (pose) analyzePose(pose);

        } catch (e) { console.error(e); }
    }

    // --- AI: Pose Calculation ---
    function calculateHeadPose(kp) {
        const nose = kp.find(p => p.name === 'noseTip');
        const leftCheek = kp.find(p => p.index === 234);
        const rightCheek = kp.find(p => p.index === 454);
        if(!nose || !leftCheek || !rightCheek) return null;

        const rangeX = Math.abs(leftCheek.x - rightCheek.x);
        const midX = (leftCheek.x + rightCheek.x) / 2;
        // Yaw: Độ lệch của mũi so với trung tâm 2 má
        const yaw = ((nose.x - midX) / rangeX) * 100; // Giá trị tương đối
        
        const eyeLine = (kp.find(p=>p.name==='leftEye').y + kp.find(p=>p.name==='rightEye').y)/2;
        const chin = kp.find(p=>p.index===152).y;
        const faceH = Math.abs(chin - eyeLine);
        // Pitch: Độ cao mũi
        const pitch = ((nose.y - eyeLine) / faceH) * 100;

        return { yaw, pitch };
    }

    function analyzePose(pose) {
        // Auto Calibrate (Lấy mẫu vị trí ngồi ban đầu)
        if (!state.calibration.isCalibrated) {
            state.calibration.samples.push(pose);
            if(state.calibration.samples.length > 20) {
                const avgY = state.calibration.samples.reduce((a,b)=>a+b.yaw,0)/20;
                const avgP = state.calibration.samples.reduce((a,b)=>a+b.pitch,0)/20;
                state.calibration.neutralYaw = avgY;
                state.calibration.neutralPitch = avgP;
                state.calibration.isCalibrated = true;
                statusBox.textContent = "✅ Đã hiệu chuẩn tư thế";
            } else {
                statusBox.textContent = `Đang hiệu chuẩn... ${state.calibration.samples.length * 5}%`;
            }
            return;
        }

        const dy = pose.yaw - state.calibration.neutralYaw;
        const dp = pose.pitch - state.calibration.neutralPitch;

        let msg = '';
        let type = '';

        if (Math.abs(dy) > 25) { msg = "Quay mặt quá mức"; type = "looking_away"; }
        else if (dp > 20) { msg = "Cúi đầu xuống"; type = "head_down"; }
        else if (dp < -20) { msg = "Ngẩng đầu lên"; type = "head_up"; }

        if (msg) {
            statusBox.textContent = `⚠️ ${msg}`;
            if (canLog(type)) window.logCheating(type, msg, captureFrame());
        } else {
            if (statusBox.textContent.includes('⚠️')) statusBox.textContent = "Tư thế bình thường";
        }
    }

    // --- AI: Object Logic ---
    async function detectObjects() {
        if (!CONFIG.object.enabled || !cocoSsdModel) return;
        try {
            const predictions = await cocoSsdModel.detect(webcamElement);
            const phone = predictions.find(p => p.class === 'cell phone' && p.score > 0.6);
            if (phone) {
                statusBox.textContent = "⚠️ Phát hiện điện thoại";
                if(canLog('phone_detected')) window.logCheating('phone_detected', 'Sử dụng điện thoại', captureFrame());
            }
        } catch(e){}
    }

    // ============================================
    // 5. MAIN FLOW
    // ============================================
    async function main() {
        const cam = await setupCamera();
        if(!cam) { alert("Lỗi Camera! Không thể giám sát."); return; }
        
        const ai = await loadModels();
        if(!ai) return;

        // KÍCH HOẠT CÁC LÁ CHẮN BẢO MẬT (Quan trọng)
        setupSecurityListeners();

        startTimer();
        videoInterval = setInterval(detectFaces, CONFIG.face.detectionInterval);
        if(CONFIG.object.enabled) setInterval(detectObjects, CONFIG.object.scanInterval);
    }

    function startTimer() {
        let timeLeft = DURATION; // Biến từ PHP
        const interval = setInterval(() => {
            timeLeft--;
            const m = Math.floor(timeLeft / 60).toString().padStart(2,'0');
            const s = (timeLeft % 60).toString().padStart(2,'0');
            timerElement.textContent = `${Math.floor(timeLeft/3600)}:${m}:${s}`;
            if(timeLeft <= 0) {
                clearInterval(interval);
                alert("Hết giờ!");
                testForm.submit();
            }
        }, 1000);
    }

    // --- Helpers ---
    function canLog(type) {
        if(window.isSubmitting) return false;
        const last = state.lastLogTime[type] || 0;
        if (Date.now() - last > CONFIG.logCooldown) {
            state.lastLogTime[type] = Date.now();
            return true;
        }
        return false;
    }

    function captureFrame() {
        try {
            const ctx = captureCanvas.getContext('2d');
            captureCanvas.width = webcamElement.videoWidth;
            captureCanvas.height = webcamElement.videoHeight;
            ctx.drawImage(webcamElement, 0, 0);
            return captureCanvas.toDataURL('image/jpeg', 0.7);
        } catch(e) { return null; }
    }

    // Events
    startButton.addEventListener('click', () => {
        document.documentElement.requestFullscreen().catch(e=>console.log(e));
        startOverlay.style.display = 'none';
        testContent.style.display = 'block';
        timerElement.style.display = 'block';
        proctoringContainer.style.display = 'block';
        main();
    });

    testForm.addEventListener('submit', () => { window.isSubmitting = true; });
});

// ============================================
// 6. GLOBAL FUNCTIONS (LOG & TOAST)
// ============================================

window.logCheating = async function(type, details, imageData) {
    if (window.isSubmitting) return;
    if (typeof ATTEMPT_ID === 'undefined') return;

    const formData = new FormData();
    formData.append('attempt_id', ATTEMPT_ID);
    formData.append('violation_type', type);
    formData.append('details', details);
    if (imageData) formData.append('screenshot', imageData);

    try {
        const res = await fetch('log_cheating.php', { method: 'POST', body: formData });
        if (res.ok) {
            const data = await res.json();
            if (data.status === 'suspended') {
                window.showSuspendedScreen(data.reason, data.total_violations);
                window.isSubmitting = true;
            } else if (data.status === 'warning') {
                window.showViolationToast(data.message, data.remaining, data.limit);
            }
        }
    } catch(e) { console.error(e); }
};

window.showViolationToast = function(msg, remaining, limit) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    let conf = { color: 'border-amber-500', bg: 'bg-white', icon: '⚠️', sub: '' };
    
    if (remaining < 0) { conf.sub = 'Đã ghi lại hành vi.'; conf.color = 'border-blue-500'; }
    else if (remaining === 0) {
        conf.bg = 'bg-red-50'; conf.color = 'border-red-600'; conf.icon = '☠️';
        conf.sub = '<b class="text-red-700">CẢNH BÁO CUỐI! (0 lần)</b>';
    } else {
        conf.sub = `Còn <b class="text-orange-600">${remaining}</b>/${limit} lần.`;
    }

    const toast = document.createElement('div');
    toast.className = `toast-enter pointer-events-auto w-full p-4 rounded-lg shadow-xl border-l-4 ${conf.color} ${conf.bg} flex items-start gap-3 mb-2 backdrop-blur-md relative`;
    toast.innerHTML = `
        <div class="mt-1 text-xl">${conf.icon}</div>
        <div class="flex-1">
            <h4 class="font-bold text-gray-800 text-sm uppercase">CẢNH BÁO</h4>
            <p class="font-bold text-gray-900 text-sm mt-1">${msg}</p>
            <div class="text-xs mt-1 text-slate-600">${conf.sub}</div>
        </div>
        <button onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
    `;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 8000);
};

window.showSuspendedScreen = function(reason, count) {
    document.body.innerHTML = `
        <div style="position: fixed; inset: 0; background: #450a0a; color: white; display: flex; flex-direction: column; align-items: center; justify-content: center; font-family: sans-serif; text-align: center; z-index: 99999;">
            <div style="font-size: 80px; margin-bottom: 20px;">🚫</div>
            <h1 style="font-size: 32px; font-weight: bold; color: #fca5a5;">ĐÌNH CHỈ THI</h1>
            <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; max-width: 600px; margin-top: 20px;">
                <p style="font-size: 18px;">${reason}</p>
                <p style="font-size: 14px; color: #ccc; margin-top: 10px;">Tổng lỗi: ${count}</p>
            </div>
            <a href="index.php" style="margin-top: 30px; background: white; color: #7f1d1d; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: bold;">VỀ TRANG CHỦ</a>
        </div>
    `;
};