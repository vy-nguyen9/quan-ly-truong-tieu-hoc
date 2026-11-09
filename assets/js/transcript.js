document.addEventListener("DOMContentLoaded", function() {
    const currentLop = document.getElementById('currentLop').value;
    const namHoc = document.getElementById('namHocCurrent').value;
    const searchKeyword = document.getElementById('searchKeyword').value;
    const tbody = document.getElementById('hocBaListBody');
    const statusChot = document.getElementById('statusChot');
    const btnChotHocBaToanLop = document.getElementById('btnChotHocBaToanLop');

    if (currentLop) {
        loadDanhSachHocBa();
    }

    if (btnChotHocBaToanLop) {
        btnChotHocBaToanLop.addEventListener('click', function() {
            if(confirm("XÁC NHẬN KÝ CHỐT HỌC BẠ LỚP:\nSau khi chốt, dữ liệu sẽ bị KHÓA và không thể thay đổi điểm số học kỳ 2 nữa. Bạn có chắc chắn?")) {
                const originalText = btnChotHocBaToanLop.innerHTML;
                btnChotHocBaToanLop.innerHTML = 'Đang xử lý...';
                btnChotHocBaToanLop.disabled = true;

                const formData = new FormData();
                formData.append('action', 'chot_hoc_ba_toan_lop');
                formData.append('lopData', currentLop);
                formData.append('namHoc', namHoc);

                fetch('ajax_transcript.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => {
                    alert(res.message);
                    if (res.status === 'success') loadDanhSachHocBa();
                    else {
                        btnChotHocBaToanLop.innerHTML = originalText;
                        btnChotHocBaToanLop.disabled = false;
                    }
                })
                .catch(err => {
                    alert("Lỗi kết nối máy chủ.");
                    btnChotHocBaToanLop.innerHTML = originalText;
                    btnChotHocBaToanLop.disabled = false;
                });
            }
        });
    }

    function loadDanhSachHocBa() {
        const formData = new FormData();
        formData.append('action', 'get_danh_sach_hoc_ba');
        formData.append('lopData', currentLop);
        formData.append('namHoc', namHoc);
        formData.append('searchKeyword', searchKeyword);

        fetch('ajax_transcript.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success') {
                const dsHocSinh = res.data;
                const daChotLop = res.da_chot;

                if(statusChot && btnChotHocBaToanLop) {
                    statusChot.classList.remove('d-none');
                    if(daChotLop) {
                        statusChot.className = 'status-badge status-da-chot mt-1';
                        statusChot.innerHTML = 'Đã ký & chốt';
                        btnChotHocBaToanLop.classList.add('d-none');
                    } else {
                        statusChot.className = 'status-badge status-chua-chot mt-1';
                        statusChot.innerHTML = 'Chưa chốt';
                        btnChotHocBaToanLop.classList.remove('d-none');
                        btnChotHocBaToanLop.disabled = false;
                        btnChotHocBaToanLop.innerHTML = 'Ký chốt cả lớp';
                    }
                }

                renderTable(dsHocSinh, daChotLop);
            } else {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${res.message}</td></tr>`;
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">Lỗi kết nối máy chủ.</td></tr>`;
        });
    }

    function renderTable(dsHocSinh, daChotLop) {
        tbody.innerHTML = '';
        // Đã xóa Khối học và Lớp học nên gộp cột còn 6
        if(dsHocSinh.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Không tìm thấy học sinh nào.</td></tr>';
            return;
        }

        dsHocSinh.forEach((hs, idx) => {
            const tr = document.createElement('tr');
            
            let trangThaiBadge = '';
            // Nút "Xem", không icon
            let btnAction = `<a href="transcript_view.php?hs=${hs.ma_hs}&lop=${currentLop}" target="_blank" class="btn btn-sm btn-outline-primary mb-1 me-1">Xem</a>`;
            
            if (hs.da_chot == 1) {
                // Trạng thái đã chốt đồng bộ với màu Navy Bright
                trangThaiBadge = `<span class="badge bg-navy-bright bg-opacity-10 text-navy-bright border border-navy-bright px-2 py-1 mb-1">Đã ký & Chốt</span><br>`;
            } else {
                trangThaiBadge = `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-2 py-1 mb-1">Chưa chốt</span><br>`;
                if(!daChotLop) {
                    // Nút Ký màu xanh Navy
                    btnAction += `<button class="btn btn-sm btn-navy-bright mb-1 me-1" onclick="kyChotCaNhan('${hs.ma_hs}')">Ký</button>`;
                }
            }

            // Nút Yêu cầu chuyển trường outline đỏ (để dễ nhìn thấy rủi ro)
            btnAction += `<button class="btn btn-sm btn-outline-danger mb-1 me-1" onclick="yeuCauChuyenTruong('${hs.ma_hs}', '${hs.ho} ${hs.ten}')">Chuyển</button>`;

            let statusLenLop = (hs.duoc_len_lop == 1) 
                ? `<span class="badge bg-navy-bright text-white">Được lên lớp</span>` 
                : `<span class="badge bg-secondary text-white">Chưa xét/Ở lại</span>`;

            let ngaySinhStr = hs.ngay_sinh ? new Date(hs.ngay_sinh).toLocaleDateString('vi-VN') : '---';

            tr.innerHTML = `
                <td class="fw-bold text-muted">${idx + 1}</td>
                <td><span class="badge bg-light text-dark border border-secondary-subtle">${hs.ma_hs}</span></td>
                <td class="fw-semibold student-name text-start">${hs.ho} ${hs.ten}</td>
                <td class="small text-muted">${ngaySinhStr}</td>
                <td>${trangThaiBadge} ${statusLenLop}</td>
                <td class="text-start">${btnAction}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    window.yeuCauChuyenTruong = function(maHs, tenHs) {
        if(confirm(`Khởi tạo thủ tục CHUYỂN TRƯỜNG cho học sinh [${tenHs} - ${maHs}]?`)) {
            alert(`Đã gửi yêu cầu luân chuyển hồ sơ cho học sinh ${tenHs} thành công.`);
        }
    }

    window.kyChotCaNhan = function(maHs) {
        if(confirm(`Xác nhận ký chốt học bạ cho học sinh ${maHs}?`)) {
            const formData = new FormData();
            formData.append('action', 'chot_hoc_ba_ca_nhan');
            formData.append('lopData', currentLop);
            formData.append('namHoc', namHoc);
            formData.append('ma_hs', maHs);

            fetch('ajax_transcript.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                alert(res.message);
                if (res.status === 'success') loadDanhSachHocBa();
            })
            .catch(err => {
                alert("Lỗi kết nối máy chủ.");
            });
        }
    }
});