document.addEventListener("DOMContentLoaded", function() {
    // 1. Preview Ảnh Đại Diện
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreviewBox = document.querySelector('.avatar-preview-box');

    if (avatarInput && avatarPreviewBox) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                    alert("Chỉ hỗ trợ file hình ảnh (JPG, PNG, WEBP).");
                    this.value = ""; return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarPreviewBox.innerHTML = `<img src="${e.target.result}" alt="Preview" class="avatar-img">`;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // 2. Preview Chữ Ký
    const sigInput = document.getElementById('sigInput');
    const sigPreviewBox = document.querySelector('.signature-preview-box');

    if (sigInput && sigPreviewBox) {
        sigInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                    alert("Chỉ hỗ trợ file hình ảnh (JPG, PNG, WEBP).");
                    this.value = ""; return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    sigPreviewBox.innerHTML = `<img src="${e.target.result}" alt="Preview" class="signature-img">`;
                };
                reader.readAsDataURL(file);
            }
        });
    }
});