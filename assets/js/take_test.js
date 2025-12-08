// Enhanced AI Proctoring System - ALL IN ONE
// Bao gồm: AI Detect + Chặn chuột/Phím/Tab + Popup Cảnh báo

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

    // CONFIGURATION
    const CONFIG = {
        face: {
            yawThreshold: 30, pitchDownThreshold: 30, pitchUpThreshold: 25,
            minViolationDuration: 2500, detectionInterval: 500,
        },
        noFace: { duration: 4000, warningDuration: 3000 },
        multipleFace: { duration: 4000 },
        object: { enabled: false, scanInterval: 2000 },
        logCooldown: 5000,
    };

    const state = {
        face: { lastSeen: Date.now(), startTime: null },
        lastLogTime: {},
        calibration: { isCalibrated: false, samples: [], neutralYaw: 0, neutralPitch: 0 }
    };

    // 1. SECURITY LISTENERS (CHẶN CHUỘT/PHÍM/TAB)
    function setupSecurityListeners() {
        console.log("🛡️ Kích hoạt bảo mật...");
        
        // Chặn chuột phải
        document.addEventListener('contextmenu', event => {
            event.preventDefault();
            window.logCheating('right_click', 'Cố tình bấm chuột phải', null);
        });

        // Chặn Copy/Paste
        ['copy', 'paste', 'cut'].forEach(evt => {
            document.addEventListener(evt, (e) => {
                e.preventDefault();
                window.logCheating('copy_paste', `Thao tác ${evt}`, null);
            });
        });

        // Chuyển tab
        document.addEventListener("visibilitychange", () => {
            if (document.hidden) {
                window.logCheating('switched_tab', 'Rời khỏi màn hình thi', null);
            }
        });

        // Click ra ngoài
        window.addEventListener("blur", () => {
            if (document.activeElement === document.body) {
                window.logCheating('window_blur', 'Click ra ngoài khu vực thi', null);
            }
        });

        // Phím cấm
        document.addEventListener('keydown', function(e) {
            if (e.key === "F12" || (e.ctrlKey && (e.key === "p" || e.key === "P"))) {
                e.preventDefault();
                window.logCheating('devtools_key_attempt', 'Sử dụng phím tắt cấm', null);
            }
        });
    }

    // 2. CAMERA & AI
    async function setupCamera() {
        try {
            statusBox.textContent = 'Đang khởi động camera...';
            const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false });
            webcamElement.srcObject = stream;
            return new Promise(resolve => webcamElement.onloadedmetadata = () => resolve(webcamElement));
        } catch (error) {
            statusBox.textContent = "Không tìm thấy Camera";
            return null;
        }
    }

    async function loadModels() {
        statusBox.textContent = 'Đang tải AI...';
        try {
            // Face Mesh
            const model = faceLandmarksDetection.SupportedModels.MediaPipeFaceMesh;
            const config = { runtime: 'mediapipe', solutionPath: 'https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh', maxFaces: 3, refineLandmarks: false };
            faceMeshModel = await faceLandmarksDetection.createDetector(model, config);
            
            // Object Detection (Lazy load)
            (async () => {
                try { if (typeof cocoSsd !== 'undefined') cocoSsdModel = await cocoSsd.load(); } catch(e){}
            })();

            statusBox.textContent = 'Hệ thống sẵn sàng';
            return true;
        } catch (error) {
            statusBox.textContent = "Lỗi tải AI";
            return false;
        }
    }

    async function detectFaces() {
        if (!faceMeshModel || !webcamElement || webcamElement.readyState < 2) return;
        try {
            const predictions = await faceMeshModel.estimateFaces(webcamElement, { flipHorizontal: false });
            
            // 1. Không thấy mặt
            if (predictions.length === 0) {
                if (!state.face.startTime) state.face.startTime = Date.now();
                const duration = Date.now() - state.face.startTime;
                
                // Cập nhật trạng thái liên tục
                statusBox.textContent = `⚠️ Không tìm thấy khuôn mặt (${Math.floor(duration/1000)}s)`;

                // Nếu mất mặt quá 2 giây (2000ms) là bắt đầu cảnh báo ngay
                // CONFIG.noFace.duration nên set thấp xuống (ví dụ 2000)
                if (duration > 2000 && canLog('no_face_detected')) {
                    window.logCheating('no_face_detected', `Không tìm thấy khuôn mặt trong ${Math.floor(duration/1000)}s`, captureFrame());
                }
                return;
            }
            state.face.startTime = null;

            if (predictions.length > 1) {
                statusBox.textContent = `⚠️ Phát hiện ${predictions.length} người`;
                if (canLog('multiple_faces')) window.logCheating('multiple_faces', `Phát hiện ${predictions.length} người`, captureFrame());
                return;
            }

            const pose = calculateHeadPose(predictions[0].keypoints);
            if (pose) analyzePose(pose);
        } catch (e) {}
    }

    function calculateHeadPose(kp) {
        const nose = kp.find(p => p.name === 'noseTip');
        const leftCheek = kp.find(p => p.index === 234);
        const rightCheek = kp.find(p => p.index === 454);
        if(!nose || !leftCheek || !rightCheek) return null;

        const rangeX = Math.abs(leftCheek.x - rightCheek.x);
        const midX = (leftCheek.x + rightCheek.x) / 2;
        const yaw = ((nose.x - midX) / rangeX) * 100;
        
        const eyeLine = (kp.find(p=>p.name==='leftEye').y + kp.find(p=>p.name==='rightEye').y)/2;
        const chin = kp.find(p=>p.index===152).y;
        const faceH = Math.abs(chin - eyeLine);
        const pitch = ((nose.y - eyeLine) / faceH) * 100;
        return { yaw, pitch };
    }

    function analyzePose(pose) {
        if (!state.calibration.isCalibrated) {
            state.calibration.samples.push(pose);
            if(state.calibration.samples.length > 20) {
                state.calibration.neutralYaw = state.calibration.samples.reduce((a,b)=>a+b.yaw,0)/20;
                state.calibration.neutralPitch = state.calibration.samples.reduce((a,b)=>a+b.pitch,0)/20;
                state.calibration.isCalibrated = true;
                statusBox.textContent = "✅ Đã hiệu chuẩn";
            } else {
                statusBox.textContent = `Đang hiệu chuẩn... ${state.calibration.samples.length * 5}%`;
            }
            return;
        }

        const dy = pose.yaw - state.calibration.neutralYaw;
        const dp = pose.pitch - state.calibration.neutralPitch;
        let msg = ''; let type = '';

        if (Math.abs(dy) > 20) { msg = "Quay mặt quá mức"; type = "looking_away"; }
        else if (dp > 20) { msg = "Cúi đầu xuống"; type = "head_down"; }
        else if (dp < -20) { msg = "Ngẩng đầu lên"; type = "head_up"; }

        if (msg) {
            statusBox.textContent = `⚠️ ${msg}`;
            if (canLog(type)) window.logCheating(type, msg, captureFrame());
        } else if (statusBox.textContent.includes('⚠️')) statusBox.textContent = "Tư thế bình thường";
    }

    // 3. MAIN FLOW
    async function main() {
        // QUAN TRỌNG: Bật bảo mật TRƯỚC khi bật camera
        setupSecurityListeners(); 

        const cam = await setupCamera();
        if(!cam) alert("Không tìm thấy Camera! Tuy nhiên bài thi vẫn được giám sát thao tác.");
        
        const ai = await loadModels();

        startTimer();

        if (cam && ai) {
            videoInterval = setInterval(detectFaces, CONFIG.face.detectionInterval);
        }
    }

    function startTimer() {
        let timeLeft = DURATION;
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

    startButton.addEventListener('click', () => {
        document.documentElement.requestFullscreen().catch(e=>{});
        startOverlay.style.display = 'none';
        testContent.style.display = 'block';
        timerElement.style.display = 'block';
        proctoringContainer.style.display = 'block';
        main();
    });

    testForm.addEventListener('submit', () => { window.isSubmitting = true; });
});

// GLOBAL FUNCTIONS
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
    } catch(e) {}
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