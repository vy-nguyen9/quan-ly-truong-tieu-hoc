<?php
session_start();
// Kiểm tra quyền học sinh
if (!isset($_SESSION['role']) || strtoupper($_SESSION['role']) !== 'HS') {
    header("Location: ../auth/login.php");
    exit();
}
require_once '../config/database.php';

$ma_hs = $_SESSION['user_id'];

// Xác định năm học và học kỳ
$sql_nam = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
$result_nam = mysqli_query($conn, $sql_nam);
$nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam)['ma_nam'] ?? '2026-2027';

$hoc_ky_chon = $_GET['hk'] ?? 'HK1';

// Lấy thông tin lớp học của học sinh trong năm học hiện hành
$sql_lop = "SELECT lh.khoi, lh.ma_nhom_lop, lh.stt, lh.ma_truong 
            FROM hoc_sinh_lop hsl
            JOIN lop_hoc lh ON hsl.ma_nam = lh.ma_nam AND hsl.khoi = lh.khoi AND hsl.ma_nhom_lop = lh.ma_nhom_lop AND hsl.stt = lh.stt AND hsl.ma_truong = lh.ma_truong
            WHERE hsl.ma_hoc_sinh = ? AND hsl.ma_nam = ? LIMIT 1";
$stmt_lop = mysqli_prepare($conn, $sql_lop);
mysqli_stmt_bind_param($stmt_lop, "ss", $ma_hs, $nam_hoc_hien_tai);
mysqli_stmt_execute($stmt_lop);
$lop_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_lop));

$tkb_grid = [];
$max_tiet = 5; 

if ($lop_info) {
    // Truy xuất Thời khóa biểu
    $sql_tkb = "
        SELECT ct.thu, ct.tiet, mh.ten_mon, nd.ho, nd.ten 
        FROM chi_tiet_tkb ct
        JOIN thoi_khoa_bieu tkb ON ct.ma_tkb = tkb.ma_tkb
        JOIN mon_hoc mh ON tkb.ma_mon_hoc = mh.ma_mon
        LEFT JOIN nguoi_dung nd ON tkb.ma_can_bo = nd.ma_nguoi_dung
        WHERE tkb.ma_nam = ? AND tkb.hoc_ky = ? 
          AND tkb.khoi = ? AND tkb.ma_nhom_lop = ? AND tkb.stt = ? AND tkb.ma_truong = ?
    ";
    $stmt_tkb = mysqli_prepare($conn, $sql_tkb);
    mysqli_stmt_bind_param($stmt_tkb, "ssssss", $nam_hoc_hien_tai, $hoc_ky_chon, $lop_info['khoi'], $lop_info['ma_nhom_lop'], $lop_info['stt'], $lop_info['ma_truong']);
    mysqli_stmt_execute($stmt_tkb);
    $result_tkb = mysqli_stmt_get_result($stmt_tkb);

    while ($row = mysqli_fetch_assoc($result_tkb)) {
        $thu = $row['thu'];
        $tiet = $row['tiet'];
        $tkb_grid[$tiet][$thu] = [
            'mon' => $row['ten_mon'],
            'gv' => trim($row['ho'] . ' ' . $row['ten'])
        ];
        if ($tiet > $max_tiet) $max_tiet = $tiet;
    }
}
?>

<?php include '../includes/header.php'; ?>

<style>
/* CSS TKB Custom */
.custom-input { border-color: #cbd5e1; background-color: transparent; }
.custom-input:focus { border-color: var(--navy-bright); box-shadow: 0 0 0 0.2rem rgba(44, 116, 179, 0.25); }

.tkb-table th, .tkb-table td {
    vertical-align: middle !important;
    padding: 15px;
}
.tkb-table th {
    background-color: var(--navy-medium) !important;
    color: white !important;
    border-color: var(--navy-deep) !important;
    font-weight: 600;
}
.bg-light-custom { background-color: #f8fafc !important; }
.tkb-cell:hover { background-color: rgba(44, 116, 179, 0.05); }

.subject-name {
    font-weight: 700;
    color: var(--navy-bright);
    margin-bottom: 3px;
    font-size: 0.95rem;
}
.teacher-name {
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 0;
}

/* Nút bấm Navy */
.btn-navy-bright {
    background-color: var(--navy-bright) !important;
    border-color: var(--navy-bright) !important;
    color: white !important;
}
.btn-navy-bright:hover {
    background-color: var(--navy-medium) !important;
    border-color: var(--navy-medium) !important;
    color: white !important;
}

/* --- Dark Mode --- */
[data-bs-theme="dark"] .bg-slate-50 { background-color: #051426 !important; }
[data-bs-theme="dark"] .main-card { 
    background-color: #0A2647 !important; 
    border: 1px solid #144272 !important; 
}
[data-bs-theme="dark"] .main-card .card-header {
    background-color: #0A2647 !important;
    border-bottom: 1px solid #144272 !important;
}
[data-bs-theme="dark"] .text-navy-dark { color: #f8fafc !important; }
[data-bs-theme="dark"] .custom-input {
    background-color: #071b33 !important;
    color: #f8fafc !important;
    border-color: #1e293b !important;
}
[data-bs-theme="dark"] .tkb-table th { 
    background-color: #144272 !important; 
    border-color: #0A2647 !important; 
}
[data-bs-theme="dark"] .tkb-table td { border-color: #144272 !important; }
[data-bs-theme="dark"] .tkb-table tbody { background-color: #0A2647 !important; color: #cbd5e1; }
[data-bs-theme="dark"] .bg-light-custom { background-color: #071b33 !important; }
[data-bs-theme="dark"] .tkb-cell:hover { background-color: #144272; }
[data-bs-theme="dark"] .subject-name { color: #38bdf8; }
[data-bs-theme="dark"] .teacher-name { color: #94a3b8; }

/* --- Print CSS --- */
@media print {
    @page { size: A4 landscape; margin: 15mm; }
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: absolute; left: 0; top: 0; width: 100%; color: #000 !important; box-shadow: none !important;}
    
    .no-print { display: none !important; }
    .print-header, .print-footer { display: block !important; text-align: center;}
    .print-header { margin-bottom: 20px;}
    
    .tkb-table { width: 100%; border-collapse: collapse; }
    .tkb-table th, .tkb-table td { border: 1px solid #000 !important; padding: 10px; background: transparent !important;}
    .tkb-table th { background-color: #f0f0f0 !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;}
    .bg-light-custom { background-color: #fff !important; color: #000 !important; }
    .subject-name { color: #000 !important; font-size: 11pt; }
    .teacher-name { color: #555 !important; font-size: 9pt; }
}
</style>

<div class="container-fluid py-4 bg-slate-50">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h4 class="fw-bold mb-0 text-navy-dark">Thời khóa biểu</h4>
    </div>

    <!-- Bộ lọc & Thao tác -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 main-card p-3 d-flex flex-row align-items-center gap-3 no-print">
        <form method="GET" class="d-flex gap-3 m-0" id="filterForm">
            <select name="hk" class="form-select custom-input fw-bold text-navy-dark" onchange="document.getElementById('filterForm').submit()">
                <option value="HK1" <?= $hoc_ky_chon === 'HK1' ? 'selected' : '' ?>>Học kỳ 1</option>
                <option value="HK2" <?= $hoc_ky_chon === 'HK2' ? 'selected' : '' ?>>Học kỳ 2</option>
            </select>
        </form>
        
        <div class="ms-auto">
            <button class="btn btn-navy-bright fw-bold shadow-sm" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> In Lịch Học
            </button>
        </div>
    </div>

    <?php if (!$lop_info): ?>
        <div class="alert alert-warning fw-bold border-0 shadow-sm no-print">
            Bạn chưa được xếp lớp trong năm học hiện hành (<?= htmlspecialchars($nam_hoc_hien_tai) ?>).
        </div>
    <?php else: ?>
        <!-- Khung Thời Khóa Biểu (Vùng in) -->
        <div class="card border-0 shadow-sm rounded-4 main-card" id="printArea">
            
            <!-- Header hiển thị khi in -->
            <div class="print-header d-none pt-4 pb-3">
                <h3 class="fw-bold text-uppercase mb-1">THỜI KHÓA BIỂU</h3>
                <p class="mb-1 fs-5">Lớp: <strong><?= $lop_info['khoi'].'/'.$lop_info['stt'] ?></strong> &nbsp;|&nbsp; Học sinh: <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong></p>
                <p class="mb-3">Năm học: <?= htmlspecialchars($nam_hoc_hien_tai) ?> &nbsp;|&nbsp; <?= ($hoc_ky_chon === 'HK1') ? 'Học kỳ I' : 'Học kỳ II' ?></p>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered text-center align-middle mb-0 tkb-table">
                        <thead>
                            <tr>
                                <th width="10%">Tiết</th>
                                <th width="18%">Thứ Hai</th>
                                <th width="18%">Thứ Ba</th>
                                <th width="18%">Thứ Tư</th>
                                <th width="18%">Thứ Năm</th>
                                <th width="18%">Thứ Sáu</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <?php for ($t = 1; $t <= $max_tiet; $t++): ?>
                                <tr>
                                    <td class="fw-bold text-navy-dark bg-light-custom">Tiết <?= $t ?></td>
                                    <?php for ($thu = 2; $thu <= 6; $thu++): ?>
                                        <td class="tkb-cell">
                                            <?php if (isset($tkb_grid[$t][$thu])): ?>
                                                <div class="subject-name"><?= htmlspecialchars($tkb_grid[$t][$thu]['mon']) ?></div>
                                                <div class="teacher-name"><?= htmlspecialchars($tkb_grid[$t][$thu]['gv']) ?></div>
                                            <?php else: ?>
                                                <span class="text-black-50 opacity-25">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Footer ký tên khi in -->
            <div class="print-footer d-none mt-4 p-4 text-end">
                <p class="fst-italic mb-1">Ngày ..... tháng ..... năm 202...</p>
                <p class="fw-bold pe-5">GIÁO VIÊN CHỦ NHIỆM</p>
                <div style="height: 80px;"></div>
            </div>

        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>