<?php
session_start();
if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])) {
    header("Location: ../auth/login.php");
    exit();
}

// 1. GỌI KẾT NỐI CSDL TRƯỚC TIÊN
require_once '../config/database.php';

$ma_gv = $_SESSION['user_id']; 

// 2. Lấy năm học hiện tại
$sql_nam = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
$result_nam = mysqli_query($conn, $sql_nam);
if (mysqli_num_rows($result_nam) > 0) {
    $nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam)['ma_nam'];
} else {
    $sql_nam_backup = "SELECT ma_nam FROM nam_hoc ORDER BY ngay_ket_thuc DESC LIMIT 1";
    $result_nam_backup = mysqli_query($conn, $sql_nam_backup);
    $nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam_backup)['ma_nam'] ?? date('Y') . '-' . (date('Y') + 1);
}

// 3. Lấy danh sách lớp mà giáo viên được phân công giảng dạy
$sql_lop = "
    SELECT DISTINCT pcd.khoi, pcd.ma_nhom_lop, pcd.stt, pcd.ma_truong 
    FROM phan_cong_day pcd
    WHERE pcd.ma_nguoi_dung = ? AND pcd.ma_nam = ?
    ORDER BY pcd.khoi, pcd.stt
";
$stmt_lop = mysqli_prepare($conn, $sql_lop);
mysqli_stmt_bind_param($stmt_lop, "ss", $ma_gv, $nam_hoc_hien_tai);
mysqli_stmt_execute($stmt_lop);
$danh_sach_lop = mysqli_fetch_all(mysqli_stmt_get_result($stmt_lop), MYSQLI_ASSOC);

// 4. Lấy thời khóa biểu của giáo viên trong năm học hiện tại
$sql_tkb = "
    SELECT tkb.ma_tkb, tkb.khoi, tkb.ma_nhom_lop, tkb.stt, tkb.ma_truong, tkb.ma_mon_hoc, ct.thu, ct.tiet 
    FROM thoi_khoa_bieu tkb
    JOIN chi_tiet_tkb ct ON tkb.ma_tkb = ct.ma_tkb
    WHERE tkb.ma_can_bo = ? AND tkb.ma_nam = ?
";
$stmt_tkb = mysqli_prepare($conn, $sql_tkb);
mysqli_stmt_bind_param($stmt_tkb, "ss", $ma_gv, $nam_hoc_hien_tai);
mysqli_stmt_execute($stmt_tkb);
$thoi_khoa_bieu_gv = mysqli_fetch_all(mysqli_stmt_get_result($stmt_tkb), MYSQLI_ASSOC);

?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="../assets/css/attendance.css">
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

<div class="container-fluid py-4 bg-slate-50">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0 text-navy-dark">Quản lý Điểm danh</h4>
        <input type="file" id="importExcel" accept=".xlsx, .xls" style="display: none;">
        <div>
            <button class="btn btn-outline-success shadow-sm me-2" onclick="document.getElementById('importExcel').click()">
                <i class="fa-solid fa-file-import me-1"></i> Import Excel
            </button>
            <button class="btn btn-outline-primary shadow-sm" id="btnExportExcel" disabled>
                <i class="fa-solid fa-file-export me-1"></i> Xuất Excel mẫu
            </button>
        </div>
    </div>

    <!-- Bộ lọc -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 filter-card">
        <div class="card-body p-4">
            <form id="filterForm" class="row g-3 align-items-end">
                <input type="hidden" id="namHoc" value="<?= $nam_hoc_hien_tai ?>">
                <div class="col-md-5">
                    <label class="form-label fw-bold text-uppercase small text-muted">Chọn Lớp</label>
                    <select class="form-select shadow-none bg-light fw-semibold" id="lopHoc">
                        <option value="">-- Chọn lớp cần điểm danh --</option>
                        <?php foreach ($danh_sach_lop as $lop): ?>
                            <?php $val = $lop['khoi'] . '_' . $lop['ma_nhom_lop'] . '_' . $lop['stt'] . '_' . $lop['ma_truong']; ?>
                            <option value="<?= $val ?>">Lớp <?= $lop['khoi'] ?>/<?= $lop['stt'] ?> (Năm <?= $nam_hoc_hien_tai ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-uppercase small text-muted">Ngày</label>
                    <input type="date" id="ngayDiemDanh" class="form-control shadow-none bg-light fw-semibold" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-uppercase small text-muted">Tiết học</label>
                    <select class="form-select shadow-none bg-light fw-semibold" id="tietHoc">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>">Tiết <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-navy-bright w-100 fw-bold py-2 shadow-sm" id="btnBatDau">
                        <i class="fa-solid fa-check-to-slot me-1"></i> Bắt đầu
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Thống kê sỉ số -->
    <div class="row g-3 mb-4 d-none" id="statsRow">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center gap-3">
                <div class="rounded-3 bg-light text-secondary d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.5rem;"><i class="fa-solid fa-users"></i></div>
                <div><p class="mb-0 text-muted small fw-bold text-uppercase">Sĩ số lớp</p><h4 class="mb-0 fw-bold text-navy-dark" id="statSiSo">0</h4></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center gap-3 bg-success bg-opacity-10 border-success border-opacity-25">
                <div class="rounded-3 bg-success bg-opacity-25 text-success d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.5rem;"><i class="fa-solid fa-user-check"></i></div>
                <div><p class="mb-0 text-success small fw-bold text-uppercase">Có mặt</p><h4 class="mb-0 fw-bold text-success" id="statCoMat">0</h4></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center gap-3 bg-danger bg-opacity-10 border-danger border-opacity-25">
                <div class="rounded-3 bg-danger bg-opacity-25 text-danger d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.5rem;"><i class="fa-solid fa-user-xmark"></i></div>
                <div><p class="mb-0 text-danger small fw-bold text-uppercase">Vắng không phép</p><h4 class="mb-0 fw-bold text-danger" id="statKhongPhep">0</h4></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center gap-3 bg-warning bg-opacity-10 border-warning border-opacity-25">
                <div class="rounded-3 bg-warning bg-opacity-25 text-warning d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.5rem;"><i class="fa-solid fa-user-clock"></i></div>
                <div><p class="mb-0 text-warning small fw-bold text-uppercase">Vắng có phép</p><h4 class="mb-0 fw-bold text-warning" id="statCoPhep">0</h4></div>
            </div>
        </div>
    </div>

    <!-- Bảng điểm danh -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden d-none list-card" id="attendanceCard">
        <div class="card-header bg-light border-bottom p-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-navy-dark" id="tableTitle">Danh sách học sinh</h6>
            <button class="btn btn-sm btn-outline-success fw-semibold" id="btnCheckAll">
                <i class="fa-solid fa-check-double me-1"></i> Đánh dấu tất cả có mặt
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="attendanceTable">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="text-center py-3" width="5%">STT</th>
                            <th width="12%">Mã HS</th>
                            <th width="25%">Họ và tên</th>
                            <th class="text-center" width="40%">Trạng thái điểm danh</th>
                            <th width="18%">Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceListBody">
                        <!-- Nội dung render bằng JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light p-3 text-end">
            <button class="btn btn-navy-dark px-4 py-2 fw-bold shadow" id="btnSaveAttendance">
                <i class="fa-solid fa-floppy-disk me-2"></i> Lưu kết quả điểm danh
            </button>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
    // Nhúng mảng TKB từ PHP sang Javascript
    window.tkbGiaoVien = <?= json_encode($thoi_khoa_bieu_gv) ?>;
</script>
<script src="../assets/js/attendance.js"></script>