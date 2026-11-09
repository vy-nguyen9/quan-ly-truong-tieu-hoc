// ==========================================
// 1. XỬ LÝ ẨN/HIỆN MẬT KHẨU
// ==========================================
function togglePassword() {
    const pwdInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        pwdInput.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    
    // ==========================================
    // 2. LOGIC GHI NHỚ MẬT KHẨU
    // ==========================================
    const loginForm = document.querySelector('form');
    const usernameInput = document.querySelector('input[name="username"]');
    const passwordInput = document.getElementById('password');
    const rememberCheckbox = document.getElementById('remember');

    if (loginForm && usernameInput && passwordInput && rememberCheckbox) {
        const savedUsername = localStorage.getItem('edu_username');
        const savedPassword = localStorage.getItem('edu_password');
        const shouldRemember = localStorage.getItem('edu_remember') === 'true';

        if (shouldRemember && savedUsername !== null && savedPassword !== null) {
            usernameInput.value = savedUsername;
            passwordInput.value = savedPassword;
            rememberCheckbox.checked = true;
        }

        loginForm.addEventListener('submit', function () {
            if (rememberCheckbox.checked) {
                localStorage.setItem('edu_username', usernameInput.value);
                localStorage.setItem('edu_password', passwordInput.value);
                localStorage.setItem('edu_remember', 'true');
            } else {
                localStorage.removeItem('edu_username');
                localStorage.removeItem('edu_password');
                localStorage.removeItem('edu_remember');
            }
        });
    }

    // ==========================================
    // 3. LOGIC VẼ TRỐNG ĐỒNG SVG TỪ TÂM RA NGOÀI
    // ==========================================
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const svgContainer = document.querySelector('.dong-son-wrapper');

    if (svgContainer && !prefersReducedMotion) {
        // Đợi 150ms để đảm bảo SVG được nhúng hoàn toàn vào DOM qua file_get_contents
        setTimeout(() => {
            const svgEl = svgContainer.querySelector('svg');
            if (!svgEl) return;

            // Lấy tất cả các thẻ cấu thành nên đường nét
            const paths = Array.from(svgEl.querySelectorAll('path, circle, rect, polygon, polyline, line'));
            const total = paths.length;
            if (total === 0) return;

            // Đo lường tọa độ Tâm của toàn bộ khối SVG trên màn hình
            const svgRect = svgEl.getBoundingClientRect();
            const centerX = svgRect.left + svgRect.width / 2;
            const centerY = svgRect.top + svgRect.height / 2;

            // Thu thập chiều dài và khoảng cách của TỪNG NÉT VẼ tới tâm
            const pathData = paths.map(p => {
                let len = 100;
                if (p.getTotalLength) {
                    try { len = p.getTotalLength(); } catch(e){}
                } else if (p.tagName.toLowerCase() === 'circle') {
                    const r = parseFloat(p.getAttribute('r')) || 0;
                    len = 2 * Math.PI * r;
                }

                const rect = p.getBoundingClientRect();
                const cx = rect.left + rect.width / 2;
                const cy = rect.top + rect.height / 2;
                
                // Khoảng cách Pythagore
                const dist = Math.hypot(cx - centerX, cy - centerY);

                return { el: p, len: len, dist: dist };
            });

            // Sắp xếp các nét vẽ: Ưu tiên nét gần tâm vẽ trước, xa tâm vẽ sau
            pathData.sort((a, b) => a.dist - b.dist);

            // Cấu hình Animation vẽ nét
            pathData.forEach((item, index) => {
                const p = item.el;
                
                // Giấu nét vẽ ban đầu
                p.style.strokeDasharray = item.len;
                p.style.strokeDashoffset = item.len;
                
                // Độ trễ lan tỏa từ 0s đến 4s
                let delay = (index / total) * 4.0;
                p.style.animation = `dong-son-draw 2.5s ease-out forwards ${delay}s`;
            });

            // Sau 6.5 giây (khi hoàn tất vẽ), kích hoạt CSS animation xoay tròn
            setTimeout(() => {
                svgEl.style.animation = 'dong-son-rotate 160s linear infinite';
            }, 6500);

        }, 150);
    }
});