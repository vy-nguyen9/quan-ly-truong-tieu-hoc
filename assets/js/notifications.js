document.addEventListener("DOMContentLoaded", function() {
    const notiItems = document.querySelectorAll('.noti-item');
    const btnMarkAll = document.getElementById('btnMarkAllRead');

    // Hàm gọi AJAX cập nhật DB
    function updateReadStatus(notiId, isAll = false) {
        const formData = new FormData();
        formData.append('action', isAll ? 'mark_all_read' : 'mark_read');
        if (!isAll) formData.append('noti_id', notiId);

        fetch('ajax_notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success' && isAll) {
                window.location.reload(); // Reload trang nếu mark all
            }
        })
        .catch(err => console.error(err));
    }

    // Click vào 1 thông báo
    notiItems.forEach(item => {
        item.addEventListener('click', function() {
            if (this.dataset.status === '0') {
                const notiId = this.dataset.id;
                
                // Cập nhật giao diện lập tức
                this.classList.remove('unread', 'bg-primary', 'bg-opacity-10');
                this.dataset.status = '1';
                
                // Đổi icon màu xám
                const iconBox = this.querySelector('.noti-icon');
                if(iconBox) {
                    iconBox.classList.remove('bg-navy-bright', 'shadow-sm');
                    iconBox.classList.add('bg-secondary');
                }
                
                // Đổi tiêu đề về màu thường
                const title = this.querySelector('h6');
                if(title) {
                    title.classList.remove('text-navy-dark');
                    title.classList.add('text-body');
                }

                // Xóa đường viền xanh bên trái
                const leftLine = this.querySelector('.position-absolute.bg-primary');
                if (leftLine) leftLine.remove();

                // Giảm số lượng badge
                const badge = document.getElementById('badge-unread-count');
                if (badge) {
                    let currentCount = parseInt(badge.innerText);
                    if (currentCount > 1) {
                        badge.innerText = currentCount - 1;
                    } else {
                        badge.remove(); // Xóa badge nếu = 0
                    }
                }

                // Gọi Server lưu lại
                updateReadStatus(notiId, false);
            }
        });
    });

    // Click đánh dấu đọc tất cả
    if (btnMarkAll) {
        btnMarkAll.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
            this.disabled = true;
            updateReadStatus(null, true);
        });
    }
});