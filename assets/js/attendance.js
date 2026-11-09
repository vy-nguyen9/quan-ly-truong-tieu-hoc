document.addEventListener("DOMContentLoaded", function() {
    const btnBatDau = document.getElementById('btnBatDau');
    const selectLop = document.getElementById('lopHoc');
    const inputNgay = document.getElementById('ngayDiemDanh');
    const selectTiet = document.getElementById('tietHoc');
    const namHoc = document.getElementById('namHoc').value;
    
    const statsRow = document.getElementById('statsRow');
    const attendanceCard = document.getElementById('attendanceCard');
    const tbody = document.getElementById('attendanceListBody');
    const tableTitle = document.getElementById('tableTitle');
    const btnCheckAll = document.getElementById('btnCheckAll');
    const btnSave = document.getElementById('btnSaveAttendance');
    
    const statSiSo = document.getElementById('statSiSo');
    const statCoMat = document.getElementById('statCoMat');
    const statKhongPhep = document.getElementById('statKhongPhep');
    const statCoPhep = document.getElementById('statCoPhep');

    const btnExportExcel = document.getElementById('btnExportExcel');
    const importExcel = document.getElementById('importExcel');

    let currentStudentList = [];
    let isReadOnly = false; // Cờ khóa bảng điểm danh

    // Hàm kiểm tra quyền dạy dựa vào TKB
    function kiemTraTKB(lopData, ngayStr, tiet) {
        if (!window.tkbGiaoVien || window.tkbGiaoVien.length === 0) return false;
        
        const lopParts = lopData.split('_'); // khoi_manhomlop_stt_matruong
        const khoi = parseInt(lopParts[0]);
        const ma_nhom_lop = lopParts[1];
        const stt = parseInt(lopParts[2]);
        const ma_truong = lopParts[3];
        
        const dateObj = new Date(ngayStr);
        // Date.getDay() trả về 0(CN), 1(T2), 2(T3)... TKB lưu thứ 2->7
        const dayOfWeek = dateObj.getDay() === 0 ? 8 : dateObj.getDay() + 1; 

        // Kiểm tra xem có tkb nào trùng Lớp, Thứ, Tiết không
        const coTiet = window.tkbGiaoVien.find(t => 
            parseInt(t.khoi) === khoi && 
            t.ma_nhom_lop === ma_nhom_lop && 
            parseInt(t.stt) === stt && 
            t.ma_truong === ma_truong &&
            parseInt(t.thu) === dayOfWeek &&
            parseInt(t.tiet) === parseInt(tiet)
        );

        return coTiet !== undefined;
    }

    // Xử lý nút "Bắt đầu điểm danh"
    // Xử lý nút "Bắt đầu điểm danh"
    btnBatDau.addEventListener('click', function() {
        const lopData = selectLop.value;
        const ngay = inputNgay.value;
        const tiet = selectTiet.value;

        if (lopData === "") {
            alert("Vui lòng chọn lớp!");
            return;
        }

        // Bắt lỗi ngày tương lai (Sử dụng cách so sánh chuỗi YYYY-MM-DD để tránh lỗi múi giờ)
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const todayStr = `${yyyy}-${mm}-${dd}`;
        
        if (ngay > todayStr) {
            alert("Không thể điểm danh cho ngày trong tương lai!");
            return;
        }

        // Kiểm tra TKB
        const duocPhepDiemDanh = kiemTraTKB(lopData, ngay, tiet);
        if (!duocPhepDiemDanh) {
            const xacNhan = confirm("Bạn không được phân công dạy tiết này theo Thời khóa biểu.\nBạn chỉ có thể Xem trạng thái điểm danh.\nTiếp tục?");
            if (!xacNhan) return;
            isReadOnly = true;
        } else {
            isReadOnly = false;
        }

        const tenLop = selectLop.options[selectLop.selectedIndex].text;
        const ttTitle = isReadOnly ? "(Chỉ Xem)" : "";
        tableTitle.innerHTML = `Danh sách học sinh (Tiết ${tiet} - Ngày ${formatDate(ngay)}) - ${tenLop} ${ttTitle}`;

        // Quản lý hiển thị nút bấm tùy theo quyền
        btnCheckAll.style.display = isReadOnly ? 'none' : 'inline-block';
        btnSave.style.display = isReadOnly ? 'none' : 'inline-block';
        btnExportExcel.disabled = false;

        statsRow.classList.remove('d-none');
        attendanceCard.classList.remove('d-none');
        
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>';

        // Gọi AJAX lấy dữ liệu
        const formData = new FormData();
        formData.append('action', 'get');
        formData.append('lopData', lopData);
        formData.append('ngay', ngay);
        formData.append('tiet', tiet);
        formData.append('namHoc', namHoc);

        fetch('ajax_attendance.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                currentStudentList = res.data;
                window.currentSchoolName = res.school_name; 
                renderTable(currentStudentList);
                updateStats();
            } else {
                alert(res.message);
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${res.message}</td></tr>`;
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Lỗi kết nối cơ sở dữ liệu!</td></tr>';
        });
    });

    // Render bảng HTML
    function renderTable(data) {
        tbody.innerHTML = '';
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Lớp chưa có học sinh.</td></tr>';
            return;
        }

        const disabledAttr = isReadOnly ? 'disabled' : '';
        const readonlyAttr = isReadOnly ? 'readonly' : '';

        data.forEach((student, index) => {
            const tr = document.createElement('tr');
            
            if (student.trang_thai === 'vang khong phep') tr.className = 'row-absent-u';
            else if (student.trang_thai === 'vang co phep') tr.className = 'row-absent-e';
            
            tr.innerHTML = `
                <td class="text-center text-muted fw-bold">${index + 1}</td>
                <td class="fw-bold text-navy-bright">${student.ma_hs}</td>
                <td class="fw-bold text-navy-dark">${student.ho} ${student.ten}</td>
                <td>
                    <div class="d-flex justify-content-center gap-2">
                        <input type="radio" name="st_${student.ma_hs}" id="${student.ma_hs}_1" class="radio-status" value="co mat" ${student.trang_thai==='co mat'?'checked':''} ${disabledAttr}>
                        <label for="${student.ma_hs}_1" class="status-label status-present" style="${isReadOnly ? 'cursor: not-allowed; opacity: 0.7;' : ''}">Có mặt</label>
                        
                        <input type="radio" name="st_${student.ma_hs}" id="${student.ma_hs}_2" class="radio-status" value="vang khong phep" ${student.trang_thai==='vang khong phep'?'checked':''} ${disabledAttr}>
                        <label for="${student.ma_hs}_2" class="status-label status-absent-u" style="${isReadOnly ? 'cursor: not-allowed; opacity: 0.7;' : ''}">K. Phép</label>

                        <input type="radio" name="st_${student.ma_hs}" id="${student.ma_hs}_3" class="radio-status" value="vang co phep" ${student.trang_thai==='vang co phep'?'checked':''} ${disabledAttr}>
                        <label for="${student.ma_hs}_3" class="status-label status-absent-e" style="${isReadOnly ? 'cursor: not-allowed; opacity: 0.7;' : ''}">Có phép</label>

                        <input type="radio" name="st_${student.ma_hs}" id="${student.ma_hs}_4" class="radio-status" value="di muon" ${student.trang_thai==='di muon'?'checked':''} ${disabledAttr}>
                        <label for="${student.ma_hs}_4" class="status-label status-late" style="${isReadOnly ? 'cursor: not-allowed; opacity: 0.7;' : ''}">Đi muộn</label>
                    </div>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm note-input shadow-none" data-hs="${student.ma_hs}" value="${student.ghi_chu || ''}" placeholder="Nhập ghi chú..." ${readonlyAttr}>
                </td>
            `;
            tbody.appendChild(tr);

            if (!isReadOnly) {
                const radios = tr.querySelectorAll(`input[name="st_${student.ma_hs}"]`);
                radios.forEach(r => r.addEventListener('change', function() {
                    student.trang_thai = this.value;
                    tr.className = (this.value === 'vang khong phep') ? 'row-absent-u' : (this.value === 'vang co phep') ? 'row-absent-e' : '';
                    updateStats();
                }));
            }
        });
    }

    // Đánh dấu tất cả có mặt
    btnCheckAll.addEventListener('click', function() {
        if (isReadOnly) return;
        const radios = tbody.querySelectorAll('input[value="co mat"]');
        radios.forEach(r => {
            r.checked = true;
            r.dispatchEvent(new Event('change'));
        });
    });

    function updateStats() {
        let comat = 0, kophep = 0, cophep = 0;
        currentStudentList.forEach(s => {
            if (s.trang_thai === 'co mat' || s.trang_thai === 'di muon') comat++;
            if (s.trang_thai === 'vang khong phep') kophep++;
            if (s.trang_thai === 'vang co phep') cophep++;
        });

        statSiSo.textContent = currentStudentList.length;
        statCoMat.textContent = comat;
        statKhongPhep.textContent = kophep;
        statCoPhep.textContent = cophep;
    }

    btnSave.addEventListener('click', function() {
        if (currentStudentList.length === 0 || isReadOnly) return;

        const hocSinhChuaDiemDanh = currentStudentList.filter(s => s.trang_thai === '');
        if (hocSinhChuaDiemDanh.length > 0) {
            alert(`Vui lòng chọn trạng thái điểm danh cho tất cả học sinh!\nCòn ${hocSinhChuaDiemDanh.length} học sinh chưa được điểm danh.`);
            return;
        }

        const noteInputs = tbody.querySelectorAll('.note-input');
        noteInputs.forEach(input => {
            const hsID = input.getAttribute('data-hs');
            const hsObj = currentStudentList.find(s => s.ma_hs === hsID);
            if (hsObj) hsObj.ghi_chu = input.value;
        });

        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('lopData', selectLop.value);
        formData.append('ngay', inputNgay.value);
        formData.append('tiet', selectTiet.value);
        formData.append('namHoc', namHoc);
        formData.append('attendanceData', JSON.stringify(currentStudentList));

        fetch('ajax_attendance.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            alert(res.message);
        });
    });

    // XUẤT EXCEL THEO YÊU CẦU ĐỊNH DẠNG "X" VÀ CÓ HEADER
    btnExportExcel.addEventListener('click', function() {
        if (typeof XLSX === 'undefined' || currentStudentList.length === 0) return;

        const tenLop = selectLop.options[selectLop.selectedIndex].text;
        const truong = window.currentSchoolName ? window.currentSchoolName.toUpperCase() : "TRƯỜNG TIỂU HỌC";
        const ngay = inputNgay.value;
        const tiet = selectTiet.value;
        
        // Chuyển format yyyy-mm-dd sang dd/mm/yyyy
        const formattedDate = formatDate(ngay);

        let excelData = [
            [truong, "", "", "", "", "", "", ""],
            [`ĐIỂM DANH ${tenLop.toUpperCase()}`, "", "", "", "", "", "", ""],
            [`Ngày: ${formattedDate} - Tiết: ${tiet}`, "", "", "", "", "", "", ""],
            ["", "", "", "", "", "", "", ""],
            ["STT", "Mã HS", "Họ và tên", "Có mặt", "K.Phép", "Có phép", "Đi muộn", "Ghi chú"]
        ];

        currentStudentList.forEach((s, i) => {
            excelData.push([
                i + 1, 
                s.ma_hs, 
                `${s.ho} ${s.ten}`,
                s.trang_thai === 'co mat' ? 'x' : '',
                s.trang_thai === 'vang khong phep' ? 'x' : '',
                s.trang_thai === 'vang co phep' ? 'x' : '',
                s.trang_thai === 'di muon' ? 'x' : '',
                s.ghi_chu || ''
            ]);
        });

        const ws = XLSX.utils.aoa_to_sheet(excelData);
        
        // Gộp ô (Merge Cells) cho phần header
        ws['!merges'] = [
            { s: { r: 0, c: 0 }, e: { r: 0, c: 4 } }, // Tên trường
            { s: { r: 1, c: 0 }, e: { r: 1, c: 7 } }, // Tiêu đề ĐIỂM DANH LỚP...
            { s: { r: 2, c: 0 }, e: { r: 2, c: 7 } }  // Ngày... Tiết...
        ];

        // Độ rộng cột
        ws['!cols'] = [{ wpx: 40 }, { wpx: 80 }, { wpx: 150 }, { wpx: 60 }, { wpx: 60 }, { wpx: 60 }, { wpx: 60 }, { wpx: 120 }];
        
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "DiemDanh");
        XLSX.writeFile(wb, `Diem_Danh_${tenLop.replace(/\s+/g, '_')}_Tiet${tiet}_${ngay}.xlsx`);
    });

// IMPORT EXCEL VÀ SÁNG ĐÈN
    importExcel.addEventListener('change', function(e) {
        if (currentStudentList.length === 0) {
            alert("Vui lòng Bắt đầu điểm danh lớp cần import trước!");
            importExcel.value = "";
            return;
        }

        const file = e.target.files[0];
        const reader = new FileReader();

        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(firstSheet, {header: 1});

            // Tìm index dòng chứa Tiêu đề bảng (có chữ "Mã HS" hoặc "STT")
            let startRow = 1;
            for (let i = 0; i < jsonData.length; i++) {
                if (jsonData[i] && (jsonData[i][1] === "Mã HS" || jsonData[i][0] === "STT")) {
                    startRow = i + 1; // Dữ liệu học sinh sẽ bắt đầu từ dòng kế tiếp
                    break;
                }
            }

            // Quét dữ liệu từ dòng startRow
            for (let i = startRow; i < jsonData.length; i++) {
                const row = jsonData[i];
                if (!row || !row[1]) continue; // Bỏ qua dòng trống hoặc không có mã HS

                const hsID = row[1];
                let status = '';

                if (row[3] === 'x' || row[3] === 'X') status = 'co mat';
                else if (row[4] === 'x' || row[4] === 'X') status = 'vang khong phep';
                else if (row[5] === 'x' || row[5] === 'X') status = 'vang co phep';
                else if (row[6] === 'x' || row[6] === 'X') status = 'di muon';

                const note = row[7] || '';

                // Cập nhật trên giao diện
                const tr = tbody.querySelector(`input[name="st_${hsID}"]`)?.closest('tr');
                if (tr) {
                    if (status !== '') {
                        const radio = tr.querySelector(`input[value="${status}"]`);
                        if (radio) {
                            radio.checked = true;
                            radio.dispatchEvent(new Event('change')); 
                        }
                    }
                    const noteInput = tr.querySelector('.note-input');
                    if (noteInput) noteInput.value = note;
                }
            }
            alert("Đã import dữ liệu thành công! Hãy kiểm tra và nhấn Lưu.");
            importExcel.value = ""; 
        };
        reader.readAsArrayBuffer(file);
    });

    function formatDate(dateStr) {
        const parts = dateStr.split('-');
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
});