<?php
session_start();
if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])) {
    header("Location: ../../auth/login.php");
    exit();
}
require_once '../../config/database.php';

$ma_gv = $_SESSION['user_id'];
$sql_nam = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
$result_nam = mysqli_query($conn, $sql_nam);
$nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam)['ma_nam'] ?? '2026-2027';

$sql_lop = "
    SELECT DISTINCT khoi, ma_nhom_lop, stt, ma_truong, chu_nhiem 
    FROM lop_hoc 
    WHERE chu_nhiem = ? AND ma_nam = ?
    ORDER BY khoi, stt
";
$stmt_lop = mysqli_prepare($conn, $sql_lop);
mysqli_stmt_bind_param($stmt_lop, "ss", $ma_gv, $nam_hoc_hien_tai);
mysqli_stmt_execute($stmt_lop);
$danh_sach_lop = mysqli_fetch_all(mysqli_stmt_get_result($stmt_lop), MYSQLI_ASSOC);

$get_lop = $_GET['lop'] ?? '';
$get_hk = $_GET['hk'] ?? 'HK1';
$get_dot = $_GET['dot'] ?? 'all';
?>

<?php include '../../includes/header.php'; ?>

<link rel="stylesheet" href="<?= $base_url ?>assets/css/grade.css">
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

<div class="container-fluid py-4 bg-slate-50">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0 text-navy-dark">Sổ đánh giá học sinh</h4>
    </div>

    <!-- STEPPER -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 main-card">
        <div class="card-header bg-light border-bottom p-3 d-flex justify-content-between align-items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#stepperCollapse">
            <h6 class="fw-bold text-navy-dark mb-0">Luồng nhập điểm</h6>
            <i class="fa-solid fa-chevron-up transition-icon" id="stepperIcon"></i>
        </div>
        <div class="collapse show" id="stepperCollapse">
            <div class="card-body p-4">
                <div class="stepper-wrapper">
                    <a href="grade_subject.php" id="step-subject" class="stepper-item text-decoration-none">
                        <div class="step-counter">1</div>
                        <div class="step-name">Đánh giá môn học & HĐGD</div>
                    </a>
                    <a href="grade_quality.php" id="step-quality" class="stepper-item text-decoration-none">
                        <div class="step-counter">2</div>
                        <div class="step-name">Đánh giá phẩm chất</div>
                    </a>
                    <a href="grade_ability.php" id="step-ability" class="stepper-item active text-decoration-none">
                        <div class="step-counter">3</div>
                        <div class="step-name">Đánh giá năng lực</div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- BỘ LỌC -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 main-card p-2 d-flex flex-row align-items-center gap-2 flex-wrap" style="background-color: var(--bs-tertiary-bg);">
        <select class="form-select shadow-none bg-light fw-bold text-navy-dark w-auto custom-input" id="selectLop">
            <option value="" disabled <?= empty($get_lop) ? 'selected' : '' ?>><i class="fa-solid fa-list"></i> Chọn lớp chủ nhiệm</option>
            <?php foreach ($danh_sach_lop as $lop): ?>
                <?php 
                    $val = $lop['khoi'] . '_' . $lop['ma_nhom_lop'] . '_' . $lop['stt'] . '_' . $lop['ma_truong']; 
                    $selected = ($val === $get_lop) ? 'selected' : '';
                ?>
                <option value="<?= $val ?>" data-cn="1" <?= $selected ?>>
                    Lớp <?= $lop['khoi'] ?>/<?= $lop['stt'] ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <div class="btn-group shadow-sm" role="group" id="groupHocKy">
            <input type="radio" class="btn-check" name="btnHocKy" id="btnHK1" value="HK1" <?= ($get_hk=='HK1')?'checked':'' ?>>
            <label class="btn btn-outline-navy custom-pill fw-medium" for="btnHK1">Học kỳ 1</label>
            <input type="radio" class="btn-check" name="btnHocKy" id="btnHK2" value="HK2" <?= ($get_hk=='HK2')?'checked':'' ?>>
            <label class="btn btn-outline-navy custom-pill fw-medium" for="btnHK2">Học kỳ 2</label>
        </div>

        <div class="d-flex align-items-center gap-2 ms-2">
            <span class="small text-muted fw-medium text-nowrap">Đợt nhập điểm:</span>
            <select class="form-select form-select-sm shadow-none custom-input w-auto" id="selectDotNhap">
                <option value="all" <?= ($get_dot=='all')?'selected':'' ?>>-- Tất cả --</option>
                <option value="giua_ky" <?= ($get_dot=='giua_ky')?'selected':'' ?>>Giữa học kỳ</option>
                <option value="cuoi_ky" <?= ($get_dot=='cuoi_ky')?'selected':'' ?>>Cuối học kỳ</option>
            </select>
        </div>

        <!-- <div class="ms-auto d-flex align-items-center gap-2">
            <span class="small text-muted fw-medium text-nowrap">Mặt đánh giá:</span>
            <select class="form-select form-select-sm shadow-none custom-input fw-bold border-0 bg-transparent text-navy-dark" disabled>
                <option selected>Năng lực chung</option>
            </select>
        </div> -->
    </div>

    <!-- BẢNG ĐÁNH GIÁ -->
    <div class="card border-0 shadow-sm rounded-4 main-card overflow-hidden d-none" id="bangDiemCard">
        <div class="card-header bg-light border-bottom p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="fw-bold text-navy-dark mb-0"><i class="fa-solid fa-list-check me-2"></i>Danh sách đánh giá</h6>
            <div class="d-flex gap-2">
                <input type="file" id="importExcel" accept=".xlsx, .xls" class="d-none">
                <button class="btn btn-sm btn-outline-secondary fw-bold bg-white shadow-sm" onclick="document.getElementById('importExcel').click()"><i class="fa-solid fa-file-import me-1"></i> Nhập dữ liệu</button>
                <button class="btn btn-sm btn-outline-secondary fw-bold bg-white shadow-sm" id="btnExport"><i class="fa-solid fa-file-export me-1"></i> Xuất Excel</button>
                <button class="btn btn-sm btn-navy-bright fw-bold text-white shadow-sm px-4" id="btnLuuDiem"><i class="fa-solid fa-floppy-disk me-1"></i> Lưu</button>
            </div>
        </div>

        <div class="card-body p-0">
            <!-- Xóa class text-danger gây lỗi hiển thị màu vàng -->
            <div class="custom-alert-info p-2 px-3 small border-bottom">
                Trong các cột đánh giá: Tốt nhập <strong>T hoặc 1</strong>, Đạt nhập <strong>Đ hoặc 2</strong>, Cần cố gắng nhập <strong>C hoặc 3</strong>.
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0 text-center custom-grade-table" id="gradeTable">
                    <thead class="table-navy-custom align-middle"></thead>
                    <tbody id="gradeListBody" class="bg-white"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="pageType" value="ability">
<input type="hidden" id="namHocCurrent" value="<?= htmlspecialchars($nam_hoc_hien_tai) ?>">
<?php include '../../includes/footer.php'; ?>
<script src="<?= $base_url ?>assets/js/grade.js"></script>