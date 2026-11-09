document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById('leaveForm');
    const uploadBox = document.getElementById('uploadBox');
    const fileInput = document.getElementById('fileDinhKem');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const btnSubmit = document.getElementById('btnSubmit');

    const tuNgay = document.getElementById('tuNgay');
    const denNgay = document.getElementById('denNgay');

    // UI Trigger cho File Upload
    uploadBox.addEventListener('click', () => fileInput.click());
    
    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            const file = this.files[0];
            if (file.size > 5 * 1024 * 1024) {
                alert("Kích thước file vượt quá 5MB. Vui lòng chọn file khác.");
                this.value = "";
                fileNameDisplay.classList.add('d-none');
            } else {
                fileNameDisplay.innerHTML = `<i class="fa-solid fa-check"></i> Đã chọn file: ${file.name}`;
                fileNameDisplay.classList.remove('d-none');
            }
        }
    });

    // Ràng buộc Ngày tháng
    tuNgay.addEventListener('change', function() {
        if(denNgay.value && this.value > denNgay.value) {
            denNgay.value = this.value;
        }
        denNgay.min = this.value;
    });

    // Xử lý gửi Form
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const loaiNghi = document.getElementById('loaiNghi').value;
        const gvThay = document.getElementById('gvDayThay').value;
        const fromDate = tuNgay.value;
        const toDate = denNgay.value;
        const lyDo = document.getElementById('lyDo').value;

        if (fromDate > toDate) {
            alert("Ngày bắt đầu không được lớn hơn ngày kết thúc!");
            return;
        }

        // Ghép loại nghỉ và lý do (Phù hợp với cơ sở dữ liệu hiện tại chỉ có 1 cột ly_do)
        const fullLyDo = `[${loaiNghi}] ${lyDo}`;

        const formData = new FormData();
        formData.append('tuNgay', fromDate);
        formData.append('denNgay', toDate);
        formData.append('lyDo', fullLyDo);
        if (gvThay !== "") {
            formData.append('gvDayThay', gvThay);
        }
        // Lưu ý: File đính kèm sẽ không đưa vào formData vì database hiện chưa có cột lưu trữ đường dẫn file. (Sẽ xử lý riêng nếu DB có bổ sung).

        // Đổi trạng thái nút
        const originalBtnText = btnSubmit.innerHTML;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang gửi...';
        btnSubmit.disabled = true;

        fetch('ajax_leave_request.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            alert(res.message);
            if (res.status === 'success') {
                window.location.reload(); // Reload trang để cập nhật lịch sử
            } else {
                btnSubmit.innerHTML = originalBtnText;
                btnSubmit.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            alert("Đã xảy ra lỗi kết nối. Vui lòng thử lại.");
            btnSubmit.innerHTML = originalBtnText;
            btnSubmit.disabled = false;
        });
    });
});