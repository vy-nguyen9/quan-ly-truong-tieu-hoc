<?php
session_start();
if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])) {
    header("Location: ../auth/login.php");
    exit();
}
require_once '../config/database.php';

$ma_gv = $_SESSION['user_id'];
$sql_nam = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
$result_nam = mysqli_query($conn, $sql_nam);
$nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam)['ma_nam'] ?? '2026-2027';

// Lấy danh sách lớp chủ nhiệm
$sql_lop = "
    SELECT DISTINCT lh.khoi, lh.ma_nhom_lop, lh.stt, lh.ma_truong 
    FROM lop_hoc lh
    WHERE lh.chu_nhiem = ? AND lh.ma_nam = ?
    ORDER BY lh.khoi, lh.stt
";
$stmt_lop = mysqli_prepare($conn, $sql_lop);
mysqli_stmt_bind_param($stmt_lop, "ss", $ma_gv, $nam_hoc_hien_tai);
mysqli_stmt_execute($stmt_lop);
$danh_sach_lop = mysqli_fetch_all(mysqli_stmt_get_result($stmt_lop), MYSQLI_ASSOC);

$get_lop = $_GET['lop'] ?? '';
$searchKeyword = $_GET['search'] ?? '';

// Lấy thông tin lớp đã chọn để hiển thị tiêu đề nếu có
$ten_lop_chon = "";
if (!empty($get_lop)) {
    $lopData = explode('_', $get_lop);
    if (count($lopData) >= 3) {
        $ten_lop_chon = "Lớp " . $lopData[0] . "/" . $lopData[2];
    }
}

?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="<?= $base_url ?>assets/css/transcript.css">

<div class="container-fluid py-4 bg-slate-50">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0 text-navy-dark">Danh sách Học bạ điện tử</h4>
    </div>

    <!-- BỘ LỌC TÌM KIẾM -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 main-card">
        <div class="card-body p-3">
            <form method="GET" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Năm học</label>
                    <input type="text" class="form-control bg-light" value="Năm học <?= htmlspecialchars($nam_hoc_hien_tai) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Lớp chủ nhiệm</label>
                    <select name="lop" class="form-select custom-input" id="selectLop" onchange="document.getElementById('filterForm').submit()">
                        <option value="" disabled <?= empty($get_lop) ? 'selected' : '' ?>>-- Chọn lớp --</option>
                        <?php foreach ($danh_sach_lop as $lop): ?>
                            <?php 
                                $val = $lop['khoi'] . '_' . $lop['ma_nhom_lop'] . '_' . $lop['stt'] . '_' . $lop['ma_truong']; 
                                $selected = ($val === $get_lop) ? 'selected' : '';
                            ?>
                            <option value="<?= $val ?>" <?= $selected ?>>Lớp <?= $lop['khoi'] ?>/<?= $lop['stt'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">Tên / Mã học sinh</label>
                    <input type="text" name="search" class="form-control custom-input" placeholder="Nhập tên hoặc mã học sinh..." value="<?= htmlspecialchars($searchKeyword) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-navy-bright w-100 fw-bold">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Tìm kiếm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- NÚT KÝ CHỐT TẬP THỂ (Chỉ hiện khi đã chọn lớp) -->
    <?php if (!empty($get_lop)): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-navy-dark mb-0">Danh sách học sinh <?= htmlspecialchars($ten_lop_chon) ?></h6>
        <div class="d-flex gap-2 align-items-center">
            <span id="statusChot" class="status-badge status-chua-chot d-none">Chưa chốt học bạ</span>
            <button class="btn btn-sm btn-navy-bright fw-bold d-none" id="btnChotHocBaToanLop">
                <i class="fa-solid fa-file-signature me-1"></i> Ký chốt cả lớp
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- BẢNG DANH SÁCH HỌC BẠ -->
    <div class="card border-0 shadow-sm rounded-4 main-card overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center custom-grade-table" id="hocBaTable">
                    <thead class="table-navy-custom align-middle">
                        <tr>
                            <th width="5%">STT</th>
                            <th width="15%">Mã học sinh</th>
                            <th width="20%">Họ và tên</th>
                            <th width="12%">Ngày sinh</th>
                            <th width="20%">Trạng thái</th>
                            <th width="28%">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="hocBaListBody" class="bg-white">
                        <?php if (empty($get_lop)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">Vui lòng chọn lớp chủ nhiệm để xem danh sách.</td></tr>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải dữ liệu...</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="namHocCurrent" value="<?= htmlspecialchars($nam_hoc_hien_tai) ?>">
<input type="hidden" id="searchKeyword" value="<?= htmlspecialchars($searchKeyword) ?>">
<input type="hidden" id="currentLop" value="<?= htmlspecialchars($get_lop) ?>">

<?php include '../includes/footer.php'; ?>
<script src="<?= $base_url ?>assets/js/transcript.js"></script>