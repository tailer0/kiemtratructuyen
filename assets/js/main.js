// --- JavaScript cho Hiệu ứng Track Trỏ chuột ---
document.addEventListener('DOMContentLoaded', () => {
    
    const body = document.body;
    
    // Chỉ chạy hiệu ứng này nếu không phải là thiết bị cảm ứng (tăng hiệu suất)
    if (window.matchMedia('(pointer: fine)').matches) {
        body.addEventListener('mousemove', e => {
            // Cập nhật biến CSS (--x, --y) bằng vị trí của trỏ chuột
            // Thêm window.scrollY để hiệu ứng đi theo khi cuộn trang
            body.style.setProperty('--x', e.clientX + 'px');
            body.style.setProperty('--y', (e.clientY + window.scrollY) + 'px'); 
        });
    }

    // === PHẦN MỚI: KÍCH HOẠT ANIMATION KHI CUỘN ===

    // 1. Tạo một IntersectionObserver
    // Observer này sẽ "quan sát" các phần tử
    const observer = new IntersectionObserver(
        (entries) => {
            // Lặp qua từng phần tử được quan sát
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    // Nếu phần tử đi vào trong khung hình
                    entry.target.classList.add('is-visible'); // Thêm class để kích hoạt CSS transition
                    observer.unobserve(entry.target); // Dừng quan sát sau khi đã kích hoạt
                }
            });
        },
        {
            rootMargin: '0px',
            threshold: 0.1 // Kích hoạt khi 10% phần tử hiện ra
        }
    );

    // 2. Lấy tất cả các phần tử có class 'animate-on-scroll'
    const elementsToAnimate = document.querySelectorAll('.animate-on-scroll');

    // 3. Yêu cầu Observer quan sát từng phần tử
    elementsToAnimate.forEach((el) => {
        observer.observe(el);
    });

});

