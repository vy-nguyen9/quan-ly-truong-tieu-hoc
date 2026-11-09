document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Xử lý ẩn/hiện Sidebar trên thiết bị di động
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    // 2. Xử lý Dark Mode có lưu trạng thái (Local Storage)
    const darkModeToggle = document.getElementById('darkModeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const htmlEl = document.documentElement;

    // Kiểm tra trạng thái đã lưu khi vừa load trang
    const savedTheme = localStorage.getItem('theme') || 'light';
    htmlEl.setAttribute('data-bs-theme', savedTheme);
    updateIcon(savedTheme);

    if (darkModeToggle && themeIcon) {
        darkModeToggle.addEventListener('click', function() {
            const currentTheme = htmlEl.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            // Cập nhật giao diện và lưu vào bộ nhớ trình duyệt
            htmlEl.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            updateIcon(newTheme);
        });
    }

    // Hàm cập nhật icon mặt trăng/mặt trời
    function updateIcon(theme) {
        if (!themeIcon) return;
        if (theme === 'dark') {
            themeIcon.classList.remove('fa-moon', 'text-secondary');
            themeIcon.classList.add('fa-sun', 'text-warning');
        } else {
            themeIcon.classList.remove('fa-sun', 'text-warning');
            themeIcon.classList.add('fa-moon', 'text-secondary');
        }
    }
});