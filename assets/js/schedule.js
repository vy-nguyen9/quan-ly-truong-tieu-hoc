document.addEventListener("DOMContentLoaded", function() {
    const btnPrintTKB = document.getElementById('btnPrintTKB');
    const info = window.thongTinTKB;

    // Chức năng In tài liệu
    if (btnPrintTKB) {
        btnPrintTKB.addEventListener('click', function() {
            let printWindow = window.open('', '_blank');
            
            // Lấy nguyên mảng các ô của bảng hiển thị trên web
            let cells = document.querySelectorAll('.tkb-cell');
            let tkbMatrix = {
                1: { 2: '', 3: '', 4: '', 5: '', 6: '' },
                2: { 2: '', 3: '', 4: '', 5: '', 6: '' },
                3: { 2: '', 3: '', 4: '', 5: '', 6: '' },
                4: { 2: '', 3: '', 4: '', 5: '', 6: '' },
                5: { 2: '', 3: '', 4: '', 5: '', 6: '' },
                6: { 2: '', 3: '', 4: '', 5: '', 6: '' },
                7: { 2: '', 3: '', 4: '', 5: '', 6: '' }
            };

            let index = 0;
            for (let tiet = 1; tiet <= 7; tiet++) {
                for (let thu = 2; thu <= 6; thu++) {
                    if (cells[index]) {
                        tkbMatrix[tiet][thu] = cells[index].innerHTML.trim();
                    }
                    index++;
                }
            }

            let htmlToPrint = `
            <html>
            <head>
                <title>Thời khóa biểu - ${info.giaoVien}</title>
                <style>
                    @page { size: A4 landscape; margin: 15mm; }
                    body { font-family: 'Times New Roman', Times, serif; font-size: 13pt; line-height: 1.5; color: #000; }
                    .header-left { font-weight: bold; margin-bottom: 20px; text-transform: uppercase; }
                    .title { text-align: center; font-size: 16pt; font-weight: bold; margin-bottom: 5px; }
                    .subtitle { text-align: center; font-size: 13pt; margin-bottom: 5px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 25px; }
                    th, td { border: 1px solid black; padding: 12px; text-align: center; vertical-align: middle; }
                    th { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; font-weight: bold; }
                    .text-navy-dark { color: #000 !important; font-size: 12pt; }
                    .text-muted { color: #555 !important; font-size: 11pt; }
                    @media print {
                        .no-print { display: none; }
                        th { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; }
                    }
                </style>
            </head>
            <body>
                <div class="header-left">${info.truong}</div>
                
                <div class="title">THỜI KHÓA BIỂU GIẢNG DẠY</div>
                <div class="subtitle">Năm học: ${info.namHoc} - Học kỳ: ${info.hocKy.replace('HK', '')}</div>
                <div class="subtitle"><strong>Giáo viên: ${info.giaoVien}</strong></div>

                <table>
                    <thead>
                        <tr>
                            <th width="8%">Buổi</th>
                            <th width="8%">Tiết</th>
                            <th width="16.8%">Thứ 2</th>
                            <th width="16.8%">Thứ 3</th>
                            <th width="16.8%">Thứ 4</th>
                            <th width="16.8%">Thứ 5</th>
                            <th width="16.8%">Thứ 6</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            for (let i = 1; i <= 7; i++) {
                htmlToPrint += `<tr>`;
                
                if (i === 1) htmlToPrint += `<td rowspan="4"><b>SÁNG</b></td>`;
                if (i === 5) htmlToPrint += `<td rowspan="3"><b>CHIỀU</b></td>`;

                htmlToPrint += `
                        <td><b>Tiết ${i}</b></td>
                        <td>${tkbMatrix[i][2]}</td>
                        <td>${tkbMatrix[i][3]}</td>
                        <td>${tkbMatrix[i][4]}</td>
                        <td>${tkbMatrix[i][5]}</td>
                        <td>${tkbMatrix[i][6]}</td>
                    </tr>
                `;
            }

            htmlToPrint += `
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 30px;" class="no-print">
                    <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; border: 1px solid #000; background: #fff;">IN THỜI KHÓA BIỂU</button>
                </div>
            </body>
            </html>`;

            printWindow.document.write(htmlToPrint);
            printWindow.document.close();
            
            // Tự động bật hộp thoại in khi cửa sổ load xong
            printWindow.onload = function() {
                printWindow.print();
            };
        });
    }
});