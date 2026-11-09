document.addEventListener("DOMContentLoaded", function() {
    const selectMon = document.getElementById('monHoc');
    const selectLop = document.getElementById('lopHoc');
    const selectNam = document.getElementById('namHoc');
    const tbody = document.getElementById('studentListBody');
    const btnExport = document.getElementById('btnExportPDF');
    const btnPrint = document.getElementById('btnPrint');
    const studentCount = document.getElementById('studentCount');
    const tableTitle = document.getElementById('tableTitle');

    let currentStudentData = [];

    // 1. Bắt sự kiện chọn Môn học
    selectMon.addEventListener('change', function() {
        const maMon = this.value;
        selectLop.innerHTML = '<option value="">-- Chọn lớp --</option>';
        
        if (maMon === "") {
            selectLop.disabled = true;
            resetTable();
            return;
        }

        selectLop.disabled = false;
        
        const lopCuaMon = window.dsLopPhanCong.filter(lop => lop.ma_mon === maMon);
        const uniqueClasses = new Set();
        
        lopCuaMon.forEach(lop => {
            const classValue = `${lop.khoi}_${lop.ma_nhom_lop}_${lop.stt}`;
            if (!uniqueClasses.has(classValue)) {
                uniqueClasses.add(classValue);
                const option = document.createElement('option');
                option.value = classValue;
                option.textContent = `Lớp ${lop.khoi}/${lop.stt}`;
                selectLop.appendChild(option);
            }
        });
        resetTable();
    });

    // 2. Bắt sự kiện chọn Lớp -> Load học sinh
    selectLop.addEventListener('change', function() {
        const lopData = this.value;
        const namHoc = selectNam.value;

        if (lopData === "") {
            resetTable();
            return;
        }

        const selectedMonText = selectMon.options[selectMon.selectedIndex].text;
        const selectedLopText = this.options[this.selectedIndex].text;
        tableTitle.innerHTML = `<i class="fa-solid fa-list me-2"></i>Danh sách học sinh - Môn: ${selectedMonText} - ${selectedLopText}`;

        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>';

        const formData = new FormData();
        formData.append('lopData', lopData);
        formData.append('namHoc', namHoc);

        fetch('ajax_get_students.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                currentStudentData = res.data;
                // Lưu lại thông tin Trường và GVCN để xuất file
                window.currentSchoolName = res.school_name.toUpperCase();
                window.currentTeacherName = res.teacher_name;

                renderTable(currentStudentData);
                studentCount.textContent = `Sĩ số: ${currentStudentData.length}`;
                
                if(currentStudentData.length > 0) {
                    btnExport.disabled = false;
                    btnPrint.disabled = false;
                } else {
                    btnExport.disabled = true;
                    btnPrint.disabled = true;
                }
            } else {
                alert("Lỗi: " + res.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Lỗi kết nối dữ liệu!</td></tr>';
        });
    });

    function resetTable() {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Vui lòng chọn môn và lớp để xem danh sách.</td></tr>';
        btnExport.disabled = true;
        btnPrint.disabled = true;
        studentCount.textContent = "Sĩ số: 0";
        currentStudentData = [];
        tableTitle.innerHTML = `<i class="fa-solid fa-list me-2"></i>Danh sách học sinh`;
    }

    function renderTable(data) {
        tbody.innerHTML = '';
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Lớp này hiện chưa có học sinh.</td></tr>';
            return;
        }

        data.forEach((student, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-center fw-semibold text-secondary">${index + 1}</td>
                <td class="fw-bold">${student.ho} ${student.ten}</td>
                <td class="text-center">${student.gioi_tinh}</td>
                <td class="text-center">${student.ngay_sinh}</td>
                <td></td>
            `;
            tbody.appendChild(tr);
        });
    }

    // 3. XUẤT FILE EXCEL
    btnExport.addEventListener('click', function() {
        if (typeof XLSX === 'undefined') {
            alert("Vui lòng đợi tải thư viện Excel.");
            return;
        }

        const tenLop = selectLop.options[selectLop.selectedIndex].text.replace("Lớp ", "");
        const namHoc = selectNam.value;
        const truong = window.currentSchoolName || "TRƯỜNG TIỂU HỌC";
        const gvcn = window.currentTeacherName || "";

        // Hàm hỗ trợ tạo border nét mảnh cho Excel
        const getBorder = () => ({
            top: { style: "thin" }, bottom: { style: "thin" },
            left: { style: "thin" }, right: { style: "thin" }
        });

        // Tạo mảng dữ liệu có kèm Style định dạng (Font Times New Roman, Size 12)
        let excelData = [
            [{ v: truong, s: { font: { name: "Times New Roman", sz: 12, bold: true }, alignment: { horizontal: "center" } } }, "", "", "", ""],
            ["", "", "", "", ""],
            [{ v: `DANH SÁCH HỌC SINH LỚP ${tenLop} NĂM HỌC ${namHoc}`, s: { font: { name: "Times New Roman", sz: 14, bold: true }, alignment: { horizontal: "center" } } }, "", "", "", ""],
            [{ v: `Giáo viên chủ nhiệm: ${gvcn}`, s: { font: { name: "Times New Roman", sz: 12, bold: true }, alignment: { horizontal: "center" } } }, "", "", "", ""],
            ["", "", "", "", ""],
            [
                { v: "STT", s: { font: { name: "Times New Roman", sz: 12, bold: true }, alignment: { horizontal: "center", vertical: "center" }, border: getBorder() } },
                { v: "Họ và tên", s: { font: { name: "Times New Roman", sz: 12, bold: true }, alignment: { horizontal: "center", vertical: "center" }, border: getBorder() } },
                { v: "Giới tính", s: { font: { name: "Times New Roman", sz: 12, bold: true }, alignment: { horizontal: "center", vertical: "center" }, border: getBorder() } },
                { v: "Ngày sinh", s: { font: { name: "Times New Roman", sz: 12, bold: true }, alignment: { horizontal: "center", vertical: "center" }, border: getBorder() } },
                { v: "Ghi chú", s: { font: { name: "Times New Roman", sz: 12, bold: true }, alignment: { horizontal: "center", vertical: "center" }, border: getBorder() } }
            ]
        ];

        // Đẩy danh sách học sinh vào
        currentStudentData.forEach((student, index) => {
            excelData.push([
                { v: index + 1, s: { font: { name: "Times New Roman", sz: 12 }, alignment: { horizontal: "center", vertical: "center" }, border: getBorder() } },
                { v: `${student.ho} ${student.ten}`, s: { font: { name: "Times New Roman", sz: 12 }, alignment: { vertical: "center" }, border: getBorder() } },
                { v: student.gioi_tinh, s: { font: { name: "Times New Roman", sz: 12 }, alignment: { horizontal: "center", vertical: "center" }, border: getBorder() } },
                { v: student.ngay_sinh, s: { font: { name: "Times New Roman", sz: 12 }, alignment: { horizontal: "center", vertical: "center" }, border: getBorder() } },
                { v: "", s: { font: { name: "Times New Roman", sz: 12 }, border: getBorder() } }
            ]);
        });

        const ws = XLSX.utils.aoa_to_sheet(excelData);

        // GỘP Ô CHO EXCEL (r = row, c = column, tính từ 0)
        ws['!merges'] = [
            { s: { r: 0, c: 0 }, e: { r: 0, c: 2 } }, // Gộp dòng Tên trường
            { s: { r: 2, c: 0 }, e: { r: 2, c: 4 } }, // Gộp Tiêu đề Danh sách
            { s: { r: 3, c: 0 }, e: { r: 3, c: 4 } }  // Gộp Tên GVCN
        ];

        // Độ rộng các cột
        ws['!cols'] = [ { wpx: 40 }, { wpx: 180 }, { wpx: 70 }, { wpx: 100 }, { wpx: 120 } ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Danh sách lớp");
        XLSX.writeFile(wb, `Danh_sach_Lop_${tenLop.replace('/', '_')}.xlsx`);
    });

    // 4. IN TÀI LIỆU
    btnPrint.addEventListener('click', function() {
        const tenLop = selectLop.options[selectLop.selectedIndex].text.replace("Lớp ", "");
        const namHoc = selectNam.value;
        const truong = window.currentSchoolName || "TRƯỜNG TIỂU HỌC";
        const gvcn = window.currentTeacherName || "";

        let printWindow = window.open('', '_blank');
        
        let htmlToPrint = `
        <html>
        <head>
            <title>Danh sách lớp ${tenLop}</title>
            <style>
                @page { size: A4; margin: 15mm; }
                body { 
                    font-family: 'Times New Roman', Times, serif; 
                    font-size: 12pt; 
                    line-height: 1.5;
                }
                .header-left { text-align: left; margin-bottom: 20px; font-weight: bold; width: 40%; text-align: center; }
                .title { text-align: center; font-size: 14pt; font-weight: bold; margin-bottom: 5px; margin-top: 20px;}
                .teacher { text-align: center; font-size: 12pt; font-weight: bold; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px;}
                th, td { border: 1px solid black; padding: 6px; text-align: center; font-size: 12pt;}
                .text-left { text-align: left; padding-left: 10px;}
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <div class="header-left">${truong}</div>
            
            <div class="title">DANH SÁCH HỌC SINH LỚP ${tenLop} NĂM HỌC ${namHoc}</div>
            <div class="teacher">Giáo viên chủ nhiệm: ${gvcn}</div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 8%;">STT</th>
                        <th style="width: 37%;">Họ và tên</th>
                        <th style="width: 15%;">Giới tính</th>
                        <th style="width: 20%;">Ngày sinh</th>
                        <th style="width: 20%;">Ghi chú</th>
                    </tr>
                </thead>
                <tbody>`;

        currentStudentData.forEach((student, index) => {
            htmlToPrint += `
                <tr>
                    <td>${index + 1}</td>
                    <td class="text-left">${student.ho} ${student.ten}</td>
                    <td>${student.gioi_tinh}</td>
                    <td>${student.ngay_sinh}</td>
                    <td></td>
                </tr>`;
        });

        htmlToPrint += `
                </tbody>
            </table>
            
            <div style="text-align: center; margin-top: 30px;" class="no-print">
                <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">IN DANH SÁCH NÀY</button>
            </div>
        </body>
        </html>`;

        printWindow.document.write(htmlToPrint);
        printWindow.document.close();
    });
});