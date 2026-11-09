document.addEventListener("DOMContentLoaded", function() {
    const selectLop = document.getElementById('selectLop');
    const groupHocKy = document.getElementsByName('btnHocKy');
    const selectDotNhap = document.getElementById('selectDotNhap');
    
    const pageType = document.getElementById('pageType').value; 
    const bangDiemCard = document.getElementById('bangDiemCard');
    const thead = document.querySelector('#gradeTable thead');
    const tbody = document.getElementById('gradeListBody');

    const btnLuuDiem = document.getElementById('btnLuuDiem');
    const btnExport = document.getElementById('btnExport');
    const importExcel = document.getElementById('importExcel');

    let currentLop = selectLop.value;
    let currentMon = '';
    let dsHocSinh = [];

    // Map mã PC/NL thực tế trong CSDL
    // Phẩm chất: PC1 -> PCNL01, ..., PC5 -> PCNL05
    // Năng lực: NL1 -> PCNL06, NL2 -> PCNL07, NL3 -> PCNL08
    const mapPcnlCode = (type, index) => {
        if (type === 'quality') return `PCNL0${index}`;
        if (type === 'ability') return `PCNL0${index + 5}`;
        return '';
    };

    function updateStepperLinks() {
        if (!currentLop) return;
        const hk = document.querySelector('input[name="btnHocKy"]:checked').value;
        const dot = selectDotNhap.value;
        const queryParams = `?lop=${currentLop}&hk=${hk}&dot=${dot}`;

        const selectedOpt = selectLop.options[selectLop.selectedIndex];
        const isChuNhiem = selectedOpt && selectedOpt.getAttribute('data-cn') === '1';

        document.getElementById('step-subject').href = 'grade_subject.php' + queryParams;

        ['step-quality', 'step-ability', 'step-reward'].forEach(id => {
            const el = document.getElementById(id);
            if(el) {
                if(isChuNhiem) {
                    el.href = id.replace('step-', 'grade_') + '.php' + queryParams;
                    el.classList.remove('locked');
                } else {
                    el.removeAttribute('href');
                    el.classList.add('locked');
                }
            }
        });
    }

    selectLop.addEventListener('change', function() {
        currentLop = this.value;
        updateStepperLinks();
        if (pageType === 'subject') loadMonHocTabs();
        else loadDanhSach();
    });

    groupHocKy.forEach(btn => btn.addEventListener('change', () => { updateStepperLinks(); loadDanhSach(); }));
    selectDotNhap.addEventListener('change', () => { updateStepperLinks(); loadDanhSach(); });

    if (currentLop) {
        updateStepperLinks();
        if (pageType === 'subject') loadMonHocTabs();
        else loadDanhSach();
    }

    function loadMonHocTabs() {
        const monHocTabs = document.getElementById('monHocTabs');
        const monHocCard = document.getElementById('monHocCard');
        if(!monHocTabs) return;

        monHocCard.classList.remove('d-none');
        monHocTabs.innerHTML = '<li class="nav-item"><span class="nav-link py-3 text-muted">Đang tải...</span></li>';
        
        const formData = new FormData();
        formData.append('action', 'get_subjects');
        formData.append('lopData', currentLop);
        formData.append('namHoc', document.getElementById('namHocCurrent').value);

        fetch('../ajax_grade.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            monHocTabs.innerHTML = '';
            if(res.status === 'success' && res.data.length > 0) {
                res.data.forEach((mon, idx) => {
                    const li = document.createElement('li');
                    li.className = 'nav-item';
                    li.innerHTML = `<a class="nav-link ${idx === 0 ? 'active' : ''}" data-mon="${mon.ma_mon}">${mon.ten_mon}</a>`;
                    monHocTabs.appendChild(li);

                    li.querySelector('a').addEventListener('click', function() {
                        document.querySelectorAll('.custom-tabs .nav-link').forEach(a => a.classList.remove('active'));
                        this.classList.add('active');
                        currentMon = this.getAttribute('data-mon');
                        loadDanhSach();
                    });
                });
                currentMon = res.data[0].ma_mon;
                loadDanhSach();
            } else {
                monHocTabs.innerHTML = '<li class="nav-item"><span class="nav-link py-3 text-danger">Không có môn học</span></li>';
                bangDiemCard.classList.add('d-none');
            }
        }).catch(err => {
            monHocTabs.innerHTML = '<li class="nav-item"><span class="nav-link py-3 text-danger">Lỗi kết nối CSDL</span></li>';
        });
    }

    function loadDanhSach() {
        if(!currentLop) return;
        if(pageType === 'subject' && !currentMon) return;

        bangDiemCard.classList.remove('d-none');
        tbody.innerHTML = '<tr><td colspan="15" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải dữ liệu...</td></tr>';
        
        const hk = document.querySelector('input[name="btnHocKy"]:checked').value;
        const formData = new FormData();
        formData.append('action', 'get_students_grade');
        formData.append('lopData', currentLop);
        formData.append('namHoc', document.getElementById('namHocCurrent').value);
        formData.append('hocKy', hk);
        formData.append('pageType', pageType);
        if(pageType === 'subject') formData.append('maMon', currentMon);

        fetch('../ajax_grade.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success') {
                dsHocSinh = res.data;
                renderTable();
            } else {
                tbody.innerHTML = `<tr><td colspan="15" class="text-center text-danger py-4">${res.message}</td></tr>`;
            }
        }).catch(err => {
            tbody.innerHTML = '<tr><td colspan="15" class="text-center text-danger py-4">Lỗi kết nối CSDL</td></tr>';
        });
    }

    function createSelectHtml(type, currentValue, hsId, colIndex, dataField) {
        const val = currentValue ? currentValue.toUpperCase() : '';
        const listId = `datalist_${type}_${hsId}_${colIndex}`; 
        const opts = (type === 'subject') ? '<option value="T"><option value="H"><option value="C">' : '<option value="T"><option value="Đ"><option value="C">';
        return `
            <input list="${listId}" type="text" maxlength="1" data-hs="${hsId}" data-field="${dataField}" oninput="this.value = this.value.toUpperCase()" class="custom-select-grade fw-bold grade-text-color val-input" value="${val}">
            <datalist id="${listId}">${opts}</datalist>
        `;
    }

    function attachKeydownEvents() {
        document.querySelectorAll('.custom-select-grade').forEach(input => {
            input.addEventListener('keydown', function(e) {
                const key = e.key.toUpperCase();
                if(key === 'ARROWDOWN' || key === 'ARROWUP' || key === 'ENTER' || key === 'TAB') return;
                e.preventDefault(); 
                if (pageType === 'subject') {
                    if (key === '1' || key === 'T') this.value = 'T';
                    else if (key === '2' || key === 'H') this.value = 'H';
                    else if (key === '3' || key === 'C') this.value = 'C';
                    else if (key === 'BACKSPACE' || key === 'DELETE') this.value = '';
                } else {
                    if (key === '1' || key === 'T') this.value = 'T';
                    else if (key === '2' || key === 'D' || key === 'Đ') this.value = 'Đ';
                    else if (key === '3' || key === 'C') this.value = 'C';
                    else if (key === 'BACKSPACE' || key === 'DELETE') this.value = '';
                }
            });
        });
    }

    function renderTable() {
        let dot = selectDotNhap.value; 
        
        if (pageType === 'subject') {
            thead.innerHTML = `
                <tr>
                    <th rowspan="2" width="5%">STT</th>
                    <th rowspan="2" width="25%">Họ và tên</th>
                    ${(dot==='all' || dot==='giua_ky') ? '<th colspan="2">Giữa học kỳ</th>' : ''}
                    ${(dot==='all' || dot==='cuoi_ky') ? '<th colspan="3">Cuối học kỳ I</th>' : ''}
                </tr>
                <tr>
                    ${(dot==='all' || dot==='giua_ky') ? '<th>Mức đạt được</th><th>Nhận xét GK</th>' : ''}
                    ${(dot==='all' || dot==='cuoi_ky') ? '<th>Mức đạt được</th><th>Điểm KTĐK</th><th>Nhận xét</th>' : ''}
                </tr>
            `;
            tbody.innerHTML = '';
            if(dsHocSinh.length === 0) return tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Lớp chưa có học sinh.</td></tr>';

            dsHocSinh.forEach((hs, idx) => {
                const tr = document.createElement('tr');
                let html = `
                    <td class="fw-bold">${idx + 1}</td>
                    <td class="text-start">
                        <div class="fw-bold student-name">${hs.ho} ${hs.ten}</div>
                        <div style="font-size: 0.75rem;" class="text-muted">${hs.ma_hs}</div>
                    </td>
                `;
                if (dot==='all' || dot==='giua_ky') {
                    html += `
                        <td>${createSelectHtml('subject', hs.muc_dat_gk, hs.ma_hs, 'gk1', 'muc_dat_gk')}</td>
                        <td><input type="text" data-hs="${hs.ma_hs}" data-field="nx_gk" class="input-nx text-muted val-input" value="${hs.nx_gk || ''}"></td>
                    `;
                }
                if (dot==='all' || dot==='cuoi_ky') {
                    let val_diem_ck = (hs.diem_ck !== null && hs.diem_ck !== undefined && hs.diem_ck !== '') ? Math.round(Number(hs.diem_ck)) : '';
                    html += `
                        <td>${createSelectHtml('subject', hs.muc_dat_ck, hs.ma_hs, 'ck2', 'muc_dat_ck')}</td>
                        <td><input type="number" min="0" max="10" step="1" data-hs="${hs.ma_hs}" data-field="diem_ck" class="fw-bold grade-text-color val-input" value="${val_diem_ck}"></td>
                        <td><input type="text" data-hs="${hs.ma_hs}" data-field="nx_ck" class="input-nx text-muted val-input" value="${hs.nx_ck || ''}"></td>
                    `;
                }
                tr.innerHTML = html;
                tbody.appendChild(tr);
            });
        } 
        else if (pageType === 'quality' || pageType === 'ability') {
            let colName = pageType === 'quality' ? 'PC' : 'NL';
            let colCount = pageType === 'quality' ? 5 : 3;
            let thCols = '';
            for(let i=1; i<=colCount; i++) thCols += `<th>${colName}${i}</th>`;

            thead.innerHTML = `
                <tr>
                    <th rowspan="2" width="5%">STT</th>
                    <th rowspan="2" width="20%">Họ và tên</th>
                    ${(dot==='all' || dot==='giua_ky') ? `<th colspan="${colCount+1}">Giữa học kỳ I</th>` : ''}
                    ${(dot==='all' || dot==='cuoi_ky') ? `<th colspan="${colCount+1}">Cuối học kỳ I</th>` : ''}
                </tr>
                <tr>
                    ${(dot==='all' || dot==='giua_ky') ? thCols + '<th>Nhận xét GK</th>' : ''}
                    ${(dot==='all' || dot==='cuoi_ky') ? thCols + '<th>Nhận xét</th>' : ''}
                </tr>
            `;
            tbody.innerHTML = '';
            if(dsHocSinh.length === 0) return tbody.innerHTML = '<tr><td colspan="15" class="text-center text-muted py-4">Lớp chưa có học sinh.</td></tr>';

            dsHocSinh.forEach((hs, idx) => {
                const tr = document.createElement('tr');
                let html = `
                    <td class="fw-bold">${idx + 1}</td>
                    <td class="text-start">
                        <div class="fw-bold student-name">${hs.ho} ${hs.ten}</div>
                        <div style="font-size: 0.75rem;" class="text-muted">${hs.ma_hs}</div>
                    </td>
                `;
                if (dot==='all' || dot==='giua_ky') {
                    for(let i=1; i<=colCount; i++) {
                        let fieldName = `gk_${mapPcnlCode(pageType, i)}`; // Đổi data-field thành chuẩn DB (VD: gk_PCNL01)
                        html += `<td>${createSelectHtml(pageType, hs[fieldName], hs.ma_hs, 'gk_'+i, fieldName)}</td>`;
                    }
                    html += `<td><input type="text" data-hs="${hs.ma_hs}" data-field="nx_gk" class="input-nx text-muted val-input" value="${hs.nx_gk || ''}"></td>`;
                }
                if (dot==='all' || dot==='cuoi_ky') {
                    for(let i=1; i<=colCount; i++) {
                        let fieldName = `ck_${mapPcnlCode(pageType, i)}`; // Đổi data-field thành chuẩn DB
                        html += `<td>${createSelectHtml(pageType, hs[fieldName], hs.ma_hs, 'ck_'+i, fieldName)}</td>`;
                    }
                    html += `<td><input type="text" data-hs="${hs.ma_hs}" data-field="nx_ck" class="input-nx text-muted val-input" value="${hs.nx_ck || ''}"></td>`;
                }
                tr.innerHTML = html;
                tbody.appendChild(tr);
            });
        }
        attachKeydownEvents();
    }

    // ==========================================
    // 1. XUẤT EXCEL 
    // ==========================================
    btnExport.addEventListener('click', function() {
        if (typeof XLSX === 'undefined' || dsHocSinh.length === 0) {
            alert("Không có dữ liệu để xuất hoặc chưa tải thư viện Excel!");
            return;
        }

        let dot = selectDotNhap.value;
        let tenLop = selectLop.options[selectLop.selectedIndex].text.trim();
        let tenHK = document.querySelector('input[name="btnHocKy"]:checked').nextElementSibling.innerText.trim();
        let dotName = selectDotNhap.options[selectDotNhap.selectedIndex].text.trim();
        let matName = pageType === 'subject' ? "Môn học & HĐGD" : (pageType === 'quality' ? "Phẩm chất cơ bản" : "Năng lực chung");
        let monName = pageType === 'subject' ? document.querySelector('.custom-tabs .nav-link.active').innerText.trim() : "";

        let excelData = [
            ["TRƯỜNG:", "TRƯỜNG TIỂU HỌC", "", "", "", "", ""],
            ["LỚP:", tenLop, "HỌC KỲ:", tenHK, "ĐỢT:", dotName, ""],
            ["MẶT ĐÁNH GIÁ:", matName, pageType === 'subject' ? "MÔN:" : "", pageType === 'subject' ? monName : "", "", "", ""],
            ["BẢNG ĐIỂM", "", "", "", "", "", ""]
        ];

        let headers = ["STT", "Mã HS", "Họ", "Tên"];
        if (pageType === 'subject') {
            if (dot==='all' || dot==='giua_ky') headers.push("Mức đạt GK", "Nhận xét GK");
            if (dot==='all' || dot==='cuoi_ky') headers.push("Mức đạt CK", "Điểm CK", "Nhận xét CK");
        } else {
            let colName = pageType === 'quality' ? 'PC' : 'NL';
            let colCount = pageType === 'quality' ? 5 : 3;
            if (dot==='all' || dot==='giua_ky') {
                for(let i=1; i<=colCount; i++) headers.push(`GK_${colName}${i}`);
                headers.push("Nhận xét GK");
            }
            if (dot==='all' || dot==='cuoi_ky') {
                for(let i=1; i<=colCount; i++) headers.push(`CK_${colName}${i}`);
                headers.push("Nhận xét CK");
            }
        }
        excelData.push(headers);

        const rows = tbody.querySelectorAll('tr');
        rows.forEach((tr, index) => {
            let hsID = dsHocSinh[index].ma_hs;
            let ho = dsHocSinh[index].ho;
            let ten = dsHocSinh[index].ten;
            
            let rowData = [index + 1, hsID, ho, ten];
            let inputs = tr.querySelectorAll('.val-input');
            inputs.forEach(inp => {
                rowData.push(inp.value);
            });
            excelData.push(rowData);
        });

        const ws = XLSX.utils.aoa_to_sheet(excelData);
        ws['!cols'] = [{ wpx: 40 }, { wpx: 80 }, { wpx: 120 }, { wpx: 60 }]; 
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "BangDiem");
        XLSX.writeFile(wb, `BangDiem_${tenLop.replace(/\s+/g, '_')}_${matName.replace(/\s+/g, '')}.xlsx`);
    });

    // ==========================================
    // 2. IMPORT EXCEL
    // ==========================================
    importExcel.addEventListener('change', function(e) {
        if (dsHocSinh.length === 0) {
            alert("Vui lòng chọn lớp để tải danh sách học sinh trước khi import!");
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

            let startRow = 4; 
            let headers = jsonData[startRow];
            if(!headers || headers[1] !== "Mã HS") {
                alert("File không đúng định dạng mẫu. Vui lòng Xuất Excel để lấy mẫu chuẩn.");
                return;
            }

            let importData = [];
            for (let i = startRow + 1; i < jsonData.length; i++) {
                let row = jsonData[i];
                if (!row || !row[1]) continue;

                let rowObj = { ma_hs: row[1], ho: row[2], ten: row[3] };
                let colIdx = 4;
                
                document.querySelectorAll(`#gradeListBody tr:nth-child(${i - startRow}) .val-input`).forEach(inp => {
                    let field = inp.getAttribute('data-field');
                    rowObj[field] = row[colIdx] !== undefined ? String(row[colIdx]) : '';
                    colIdx++;
                });
                importData.push(rowObj);
            }

            const hk = document.querySelector('input[name="btnHocKy"]:checked').value;
            const formData = new FormData();
            formData.append('action', 'import_excel');
            formData.append('lopData', currentLop);
            formData.append('namHoc', document.getElementById('namHocCurrent').value);
            formData.append('hocKy', hk);
            formData.append('pageType', pageType);
            if(pageType === 'subject') formData.append('maMon', currentMon);
            formData.append('importData', JSON.stringify(importData));

            fetch('../ajax_grade.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    alert(res.message);
                    loadDanhSach();
                } else {
                    alert(res.message);
                }
            }).catch(err => {
                console.error(err);
                alert("Lỗi khi xử lý file import.");
            });
            importExcel.value = ""; 
        };
        reader.readAsArrayBuffer(file);
    });

    // ==========================================
    // 3. LƯU DỮ LIỆU
    // ==========================================
    btnLuuDiem.addEventListener('click', function() {
        if (dsHocSinh.length === 0) return;

        let saveData = [];
        const rows = tbody.querySelectorAll('tr');
        rows.forEach((tr, index) => {
            let rowObj = { ma_hs: dsHocSinh[index].ma_hs };
            let inputs = tr.querySelectorAll('.val-input');
            inputs.forEach(inp => {
                rowObj[inp.getAttribute('data-field')] = inp.value.trim();
            });
            saveData.push(rowObj);
        });

        const originalBtnText = btnLuuDiem.innerHTML;
        btnLuuDiem.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';
        btnLuuDiem.disabled = true;

        const hk = document.querySelector('input[name="btnHocKy"]:checked').value;
        const formData = new FormData();
        formData.append('action', 'save_grades');
        formData.append('lopData', currentLop);
        formData.append('namHoc', document.getElementById('namHocCurrent').value);
        formData.append('hocKy', hk);
        formData.append('pageType', pageType);
        if(pageType === 'subject') formData.append('maMon', currentMon);
        formData.append('saveData', JSON.stringify(saveData));

        fetch('../ajax_grade.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            alert(res.message);
            btnLuuDiem.innerHTML = originalBtnText;
            btnLuuDiem.disabled = false;
        }).catch(err => {
            console.error(err);
            alert("Lỗi kết nối khi lưu dữ liệu.");
            btnLuuDiem.innerHTML = originalBtnText;
            btnLuuDiem.disabled = false;
        });
    });
});