/**
 * Hàm khởi động hệ thống bảo mật
 * @param {number} attemptId - ID của lượt thi hiện tại
 */
function startExamMonitor(attemptId) {
    if (!attemptId) {
        console.error("Exam Monitor: Missing Attempt ID");
        return;
    }

    console.log("Exam Monitor: Started for attempt #" + attemptId);

    // 1. Polling trạng thái (5 giây/lần)
    setInterval(() => {
        fetch(`check_status.php?attempt_id=${attemptId}`)
            .then(response => response.json())
            .then(data => {
                // Nếu phát hiện trạng thái bị đình chỉ
                if (data.status === 'success' && data.exam_status === 'suspended') {
                    handleSuspension();
                }
                
                // Nếu phát hiện bài thi đã kết thúc (bởi giáo viên thu bài)
                if (data.status === 'success' && data.exam_status === 'completed') {
                    alert("Thời gian làm bài đã kết thúc hoặc giáo viên đã thu bài.");
                    window.location.href = 'result.php?attempt_id=' + attemptId;
                }
            })
            .catch(err => console.error("Monitor Error:", err));
    }, 5000); // 5000ms = 5 giây
}

function handleSuspension() {
    // 1. Chặn toàn bộ thao tác
    document.body.innerHTML = '';
    document.body.style.backgroundColor = '#450a0a'; // Màu đỏ đậm
    
    // 2. Hiển thị thông báo
    alert("⛔ BẠN ĐÃ BỊ ĐÌNH CHỈ THI!\n\nLý do: Vi phạm quy chế thi quá số lần quy định.\nĐiểm bài thi: 0\n\nHệ thống sẽ chuyển bạn về trang chủ.");
    
    // 3. Chuyển hướng cưỡng chế
    window.location.href = 'index.php';
}