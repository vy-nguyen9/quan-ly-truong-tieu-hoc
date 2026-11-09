</main> <!-- Đóng thẻ main từ header.php -->
    </div> <!-- Đóng thẻ div main-content -->
</div> <!-- Đóng thẻ div wrapper -->

<!-- Bootstrap 5 JS Bundle (Bắt buộc để chạy Dropdown Đăng xuất, Modal, Tabs) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script điều khiển chung cho toàn bộ hệ thống (Dark Mode & Sidebar) -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Điều khiển Sidebar (Dành cho Mobile)
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('show');
        });
    }

    // 2. Điều khiển Dark Mode
    const darkModeToggle = document.getElementById('darkModeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const htmlElement = document.documentElement;

    // Load lại trạng thái màu từ LocalStorage để không bị mất khi chuyển trang
    const savedTheme = localStorage.getItem('eduprimary_theme') || 'light';
    htmlElement.setAttribute('data-bs-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function(e) {
            e.preventDefault();
            const currentTheme = htmlElement.getAttribute('data-bs-theme');
            const newTheme = (currentTheme === 'light') ? 'dark' : 'light';
            
            htmlElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('eduprimary_theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }

    function updateThemeIcon(theme) {
        if (!themeIcon) return;
        if (theme === 'dark') {
            themeIcon.classList.replace('fa-moon', 'fa-sun');
        } else {
            themeIcon.classList.replace('fa-sun', 'fa-moon');
        }
    }
});
</script>

</body>
</html>