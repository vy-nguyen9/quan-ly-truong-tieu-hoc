<?php
session_start();
if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';

$ma_gv = $_SESSION['user_id'];

// Lấy thông tin giáo viên
$sql_gv = "SELECT ho, ten FROM nguoi_dung WHERE ma_nguoi_dung = ?";
$stmt_gv = mysqli_prepare($conn, $sql_gv);
mysqli_stmt_bind_param($stmt_gv, "s", $ma_gv);
mysqli_stmt_execute($stmt_gv);
$gv_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_gv));
$ten_giao_vien = $gv_info['ho'] . ' ' . $gv_info['ten'];

// Lấy năm học hiện tại
$sql_nam = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
$result_nam = mysqli_query($conn, $sql_nam);
if (mysqli_num_rows($result_nam) > 0) {
    $nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam)['ma_nam'];
} else {
    $sql_nam_backup = "SELECT ma_nam FROM nam_hoc ORDER BY ngay_ket_thuc DESC LIMIT 1";
    $result_nam_backup = mysqli_query($conn, $sql_nam_backup);
    $nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam_backup)['ma_nam'] ?? '2026-2027';
}

// Xử lý học kỳ được chọn (mặc định HK1)
$hoc_ky_chon = $_GET['hoc_ky'] ?? 'HK1';

// Lấy tên trường
$sql_truong = "
    SELECT tr.ten_truong 
    FROM can_bo cb 
    JOIN truong tr ON cb.ma_truong = tr.ma_truong 
    WHERE cb.ma_nguoi_dung = ? LIMIT 1
";
$stmt_tr = mysqli_prepare($conn, $sql_truong);
mysqli_stmt_bind_param($stmt_tr, "s", $ma_gv);
mysqli_stmt_execute($stmt_tr);
$truong_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tr));
$ten_truong = $truong_info ? $truong_info['ten_truong'] : 'TRƯỜNG TIỂU HỌC';

// Lấy thời khóa biểu
$sql_tkb = "
    SELECT ct.thu, ct.tiet, mh.ten_mon, tkb.khoi, tkb.stt 
    FROM thoi_khoa_bieu tkb
    JOIN chi_tiet_tkb ct ON tkb.ma_tkb = ct.ma_tkb
    JOIN mon_hoc mh ON tkb.ma_mon_hoc = mh.ma_mon
    WHERE tkb.ma_can_bo = ? AND tkb.ma_nam = ? AND tkb.hoc_ky = ?
";
$stmt_tkb = mysqli_prepare($conn, $sql_tkb);
mysqli_stmt_bind_param($stmt_tkb, "sss", $ma_gv, $nam_hoc_hien_tai, $hoc_ky_chon);
mysqli_stmt_execute($stmt_tkb);
$result_tkb = mysqli_stmt_get_result($stmt_tkb);

// Khởi tạo ma trận trống cho TKB
$schedule = [];
for ($t = 1; $t <= 7; $t++) {
    for ($th = 2; $th <= 6; $th++) {
        $schedule[$t][$th] = '';
    }
}

// Đẩy dữ liệu vào ma trận
while ($row = mysqli_fetch_assoc($result_tkb)) {
    $thu = (int)$row['thu'];
    $tiet = (int)$row['tiet'];
    if ($thu >= 2 && $thu <= 6 && $tiet >= 1 && $tiet <= 7) {
        $cell_content = '<div class="fw-bold text-navy-dark">' . $row['ten_mon'] . '</div><div class="small text-muted">Lớp ' . $row['khoi'] . '/' . $row['stt'] . '</div>';
        $schedule[$tiet][$thu] = $cell_content;
    }
}
?>

<?php include '../includes/header.php'; ?>

<style>
    .table-navy-custom th {
        background-color: var(--navy-medium) !important;
        color: white !important;
        border-bottom: 2px solid var(--navy-dark);
    }
    .tkb-cell {
        height: 80px;
        vertical-align: middle;
    }
    
    /* Dark mode adjustments */
    [data-bs-theme="dark"] .table-navy-custom th {
        background-color: #144272 !important;
        border-bottom-color: #2C74B3 !important;
    }
    [data-bs-theme="dark"] .bg-light {
        background-color: #071b33 !important;
        color: #e2e8f0 !important;
    }
    [data-bs-theme="dark"] td {
        border-color: #144272 !important;
    }
    [data-bs-theme="dark"] td .text-navy-dark {
        color: #60a5fa !important;
    }
    [data-bs-theme="dark"] td .text-muted {
        color: #94a3b8 !important;
    }
    
    /* Thêm màu trắng cho tiêu đề trang */
    [data-bs-theme="dark"] h4.text-navy-dark {
        color: #ffffff !important;
    }
</style>

<div class="container-fluid py-4">
    <!-- Tiêu đề trang -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-navy-dark">Thời khóa biểu giảng dạy</h4>
        </div>
        <!-- <div>
            <button type="button" class="btn btn-outline-secondary shadow-sm ms-2 px-3" id="btnPrintTKB" title="In Thời khóa biểu">
                <i class="fa-solid fa-print"></i>
            </button>
        </div> -->
    </div>

    <!-- Bộ lọc -->
    <div class="card border-0 shadow-sm mb-4" style="background-color: var(--bs-tertiary-bg);">
        <div class="card-body p-3">
            <form method="GET" class="d-flex gap-3 align-items-end w-100">
                <div>
                    <label class="form-label fw-semibold small mb-1">Năm học</label>
                    <input type="text" class="form-control shadow-none bg-light fw-bold" value="<?= htmlspecialchars($nam_hoc_hien_tai) ?>" disabled style="width: 150px;">
                </div>
                <div>
                    <label class="form-label fw-semibold small mb-1">Học kỳ</label>
                    <select name="hoc_ky" class="form-select shadow-none bg-light fw-bold" onchange="this.form.submit()" style="width: 150px;">
                        <option value="HK1" <?= $hoc_ky_chon == 'HK1' ? 'selected' : '' ?>>Học kỳ 1</option>
                        <option value="HK2" <?= $hoc_ky_chon == 'HK2' ? 'selected' : '' ?>>Học kỳ 2</option>
                    </select>
                </div>
                <!-- Nút in nằm sát góc phải -->
                <div class="ms-auto">
                    <button type="button" class="btn btn-outline-secondary shadow-sm px-3" id="btnPrintTKB" title="In Thời khóa biểu">
                        <i class="fa-solid fa-print"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bảng TKB -->
    <div class="card border-0 shadow-sm overflow-hidden" style="background-color: var(--bs-tertiary-bg);">
        <div class="table-responsive p-3">
            <table class="table table-bordered text-center mb-0">
                <thead class="table-navy-custom">
                    <tr>
                        <th width="10%">Buổi</th>
                        <th width="10%">Tiết</th>
                        <th width="16%">Thứ 2</th>
                        <th width="16%">Thứ 3</th>
                        <th width="16%">Thứ 4</th>
                        <th width="16%">Thứ 5</th>
                        <th width="16%">Thứ 6</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Buổi Sáng -->
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <tr>
                            <?php if ($i == 1): ?>
                                <td rowspan="4" class="align-middle fw-bold bg-light">SÁNG</td>
                            <?php endif; ?>
                            <td class="align-middle fw-bold bg-light">Tiết <?= $i ?></td>
                            <?php for ($thu = 2; $thu <= 6; $thu++): ?>
                                <td class="tkb-cell"><?= $schedule[$i][$thu] ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>

                    <!-- Buổi Chiều -->
                    <?php for ($i = 5; $i <= 7; $i++): ?>
                        <tr>
                            <?php if ($i == 5): ?>
                                <td rowspan="3" class="align-middle fw-bold bg-light">CHIỀU</td>
                            <?php endif; ?>
                            <td class="align-middle fw-bold bg-light">Tiết <?= $i ?></td>
                            <?php for ($thu = 2; $thu <= 6; $thu++): ?>
                                <td class="tkb-cell"><?= $schedule[$i][$thu] ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    // Lấy nguyên mã HTML của bảng TKB để tái sử dụng khi in
    window.thongTinTKB = {
        truong: "<?= htmlspecialchars($ten_truong) ?>",
        namHoc: "<?= htmlspecialchars($nam_hoc_hien_tai) ?>",
        hocKy: "<?= htmlspecialchars($hoc_ky_chon) ?>",
        giaoVien: "<?= htmlspecialchars($ten_giao_vien) ?>"
    };
</script>
<script src="../assets/js/schedule.js"></script>