<?php
session_start();
// Kiểm tra quyền học sinh
if (!isset($_SESSION['role']) || strtoupper($_SESSION['role']) !== 'HS') {
    header("Location: ../auth/login.php");
    exit();
}
require_once '../config/database.php';

$ma_hs = $_SESSION['user_id'];

// 1. Lấy danh sách các Năm học học sinh đã học
$sql_years = "SELECT DISTINCT ma_nam, khoi, stt FROM hoc_sinh_lop WHERE ma_hoc_sinh = ? ORDER BY ma_nam DESC";
$stmt_years = mysqli_prepare($conn, $sql_years);
mysqli_stmt_bind_param($stmt_years, "s", $ma_hs);
mysqli_stmt_execute($stmt_years);
$years_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt_years), MYSQLI_ASSOC);

// Năm học mặc định là năm mới nhất, hoặc năm hiện hành nếu chưa có danh sách
$selected_year = $_GET['year'] ?? ($years_list[0]['ma_nam'] ?? date('Y').'-'.(date('Y')+1));

// Lấy thông tin lớp và khối của năm được chọn
$ten_lop = "Chưa xếp lớp";
$khoi_hoc = 1;
foreach($years_list as $y) {
    if($y['ma_nam'] === $selected_year) {
        $ten_lop = "Lớp " . $y['khoi'] . "/" . $y['stt'];
        $khoi_hoc = $y['khoi'];
        break;
    }
}

// 2. Lấy dữ liệu Điểm Môn học (Luôn hiển thị danh sách môn của khối đó)
$sql_mon = "
    SELECT mhk.ma_mon, mh.ten_mon, d.hoc_ky, d.muc_dat_cuoi_ky, d.diem_cuoi_ky, d.nhan_xet_cuoi_ky
    FROM mon_hoc_khoi mhk
    JOIN mon_hoc mh ON mhk.ma_mon = mh.ma_mon
    LEFT JOIN danh_gia_mon_hoc d ON mh.ma_mon = d.ma_mon_hoc AND d.ma_hoc_sinh = ? AND d.ma_nam = ?
    WHERE mhk.khoi = ?
    ORDER BY mh.ten_mon, d.hoc_ky
";
$stmt_mon = mysqli_prepare($conn, $sql_mon);
mysqli_stmt_bind_param($stmt_mon, "ssi", $ma_hs, $selected_year, $khoi_hoc);
mysqli_stmt_execute($stmt_mon);
$res_mon = mysqli_fetch_all(mysqli_stmt_get_result($stmt_mon), MYSQLI_ASSOC);

$mon_data = [];
foreach($res_mon as $row) {
    $mon_id = $row['ma_mon'];
    if(!isset($mon_data[$mon_id])) {
        $mon_data[$mon_id] = [
            'ten_mon' => $row['ten_mon'],
            'HK1' => ['muc_dat' => '', 'diem' => '', 'nhan_xet' => ''],
            'HK2' => ['muc_dat' => '', 'diem' => '', 'nhan_xet' => '']
        ];
    }
    $hk = $row['hoc_ky']; 
    if ($hk) { // Nếu có dữ liệu điểm
        $mon_data[$mon_id][$hk] = [
            'muc_dat' => $row['muc_dat_cuoi_ky'],
            'diem' => $row['diem_cuoi_ky'] !== null ? round($row['diem_cuoi_ky']) : '',
            'nhan_xet' => $row['nhan_xet_cuoi_ky']
        ];
    }
}

// 3. Lấy dữ liệu Phẩm chất & Năng lực (Luôn hiển thị toàn bộ PCNL)
$sql_pcnl = "
    SELECT p.ma_pcnl, p.ten_pcnl, d.hoc_ky, d.muc_dat, d.nhan_xet
    FROM pham_chat_nang_luc p
    LEFT JOIN danh_gia_pcnl d ON p.ma_pcnl = d.ma_pcnl AND d.ma_hoc_sinh = ? AND d.ma_nam = ?
    ORDER BY p.ma_pcnl, d.hoc_ky
";
$stmt_pcnl = mysqli_prepare($conn, $sql_pcnl);
mysqli_stmt_bind_param($stmt_pcnl, "ss", $ma_hs, $selected_year);
mysqli_stmt_execute($stmt_pcnl);
$res_pcnl = mysqli_fetch_all(mysqli_stmt_get_result($stmt_pcnl), MYSQLI_ASSOC);

$pc_data = []; $nl_data = [];
foreach($res_pcnl as $row) {
    $id = $row['ma_pcnl'];
    $is_pc = in_array($id, ['PCNL01', 'PCNL02', 'PCNL03', 'PCNL04', 'PCNL05']);
    $hk = $row['hoc_ky'];

    if($is_pc) {
        if(!isset($pc_data[$id])) $pc_data[$id] = ['ten' => $row['ten_pcnl'], 'HK1' => ['muc_dat' => ''], 'HK2' => ['muc_dat' => '', 'nhan_xet' => '']];
        if($hk) $pc_data[$id][$hk] = ['muc_dat' => $row['muc_dat'], 'nhan_xet' => $row['nhan_xet']];
    } else {
        if(!isset($nl_data[$id])) $nl_data[$id] = ['ten' => $row['ten_pcnl'], 'HK1' => ['muc_dat' => ''], 'HK2' => ['muc_dat' => '', 'nhan_xet' => '']];
        if($hk) $nl_data[$id][$hk] = ['muc_dat' => $row['muc_dat'], 'nhan_xet' => $row['nhan_xet']];
    }
}

// 4. Lấy Đánh giá tổng kết Học bạ cuối năm
$sql_hb = "SELECT danh_hieu, duoc_len_lop, da_chot FROM hoc_ba WHERE ma_hoc_sinh = ? AND ma_nam = ?";
$stmt_hb = mysqli_prepare($conn, $sql_hb);
mysqli_stmt_bind_param($stmt_hb, "ss", $ma_hs, $selected_year);
mysqli_stmt_execute($stmt_hb);
$hb_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_hb));

$da_chot = $hb_info['da_chot'] ?? 0;

function getTenMucDat($val) {
    if ($val === 'T') return 'Tốt';
    if ($val === 'Đ' || $val === 'H') return 'Đạt';
    if ($val === 'C') return 'Cần cố gắng';
    return '-';
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="<?= $base_url ?>assets/css/results_student.css">

<div class="container-fluid py-4 bg-slate-50">
    <!-- Tiêu đề & Chọn Năm học -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-navy-dark">Kết quả học tập</h4>
            <p class="text-muted mb-0 small">Học sinh: <span class="fw-bold"><?= htmlspecialchars($_SESSION['user_name']) ?></span> | <?= $ten_lop ?></p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="fw-semibold text-navy-dark">Năm học:</span>
            <select id="yearSelector" class="form-select custom-input fw-bold text-navy-dark cursor-pointer" style="width: 140px;">
                <?php if(empty($years_list)): ?>
                    <option value="<?= htmlspecialchars($selected_year) ?>"><?= htmlspecialchars($selected_year) ?></option>
                <?php else: ?>
                    <?php foreach($years_list as $y): ?>
                        <option value="<?= $y['ma_nam'] ?>" <?= $y['ma_nam'] === $selected_year ? 'selected' : '' ?>><?= htmlspecialchars($y['ma_nam']) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <!-- 1. BẢNG MÔN HỌC -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 main-card overflow-hidden">
        <div class="card-header bg-light border-bottom p-3">
            <h6 class="fw-bold text-navy-dark mb-0 text-uppercase">1. Kết quả các môn học và hoạt động giáo dục</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered text-center align-middle mb-0 custom-result-table">
                    <thead class="table-navy-custom">
                        <tr>
                            <th rowspan="2" width="25%">Môn học</th>
                            <th colspan="3">Học kỳ 1</th>
                            <th colspan="3">Học kỳ 2</th>
                        </tr>
                        <tr>
                            <th width="10%">Mức đạt</th>
                            <th width="10%">Điểm</th>
                            <th width="17%">Nhận xét</th>
                            <th width="10%">Mức đạt</th>
                            <th width="10%">Điểm</th>
                            <th width="18%">Nhận xét</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <?php if(empty($mon_data)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">Không có môn học nào được phân bổ cho khối này.</td></tr>
                        <?php else: ?>
                            <?php foreach($mon_data as $mon): ?>
                                <tr>
                                    <td class="text-start fw-bold text-navy-dark ps-3"><?= htmlspecialchars($mon['ten_mon']) ?></td>
                                    
                                    <!-- HK1 -->
                                    <td class="fw-semibold text-primary"><?= getTenMucDat($mon['HK1']['muc_dat']) ?></td>
                                    <td class="fw-bold text-danger"><?= $mon['HK1']['diem'] !== '' ? $mon['HK1']['diem'] : '-' ?></td>
                                    <td class="text-start small text-muted"><?= htmlspecialchars($mon['HK1']['nhan_xet'] ?? '') ?></td>
                                    
                                    <!-- HK2 -->
                                    <td class="fw-semibold text-primary"><?= getTenMucDat($mon['HK2']['muc_dat']) ?></td>
                                    <td class="fw-bold text-danger"><?= $mon['HK2']['diem'] !== '' ? $mon['HK2']['diem'] : '-' ?></td>
                                    <td class="text-start small text-muted"><?= htmlspecialchars($mon['HK2']['nhan_xet'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- 2. PHẨM CHẤT -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 main-card overflow-hidden">
                <div class="card-header bg-light border-bottom p-3">
                    <h6 class="fw-bold text-navy-dark mb-0 text-uppercase">2. Đánh giá phẩm chất</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered text-center align-middle mb-0 custom-result-table">
                            <thead class="table-navy-custom">
                                <tr>
                                    <th width="35%">Phẩm chất</th>
                                    <th width="20%">HK1</th>
                                    <th width="20%">HK2</th>
                                    <th width="25%">Nhận xét chung</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <?php $first = true; foreach($pc_data as $pc): ?>
                                    <tr>
                                        <td class="text-start fw-bold text-navy-dark ps-3"><?= htmlspecialchars($pc['ten']) ?></td>
                                        <td class="fw-semibold text-primary"><?= getTenMucDat($pc['HK1']['muc_dat']) ?></td>
                                        <td class="fw-semibold text-primary"><?= getTenMucDat($pc['HK2']['muc_dat']) ?></td>
                                        <?php if($first): ?>
                                            <td rowspan="<?= count($pc_data) ?>" class="text-start small text-muted align-top p-3"><?= htmlspecialchars($pc['HK2']['nhan_xet'] ?? '') ?></td>
                                            <?php $first = false; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. NĂNG LỰC -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 main-card overflow-hidden">
                <div class="card-header bg-light border-bottom p-3">
                    <h6 class="fw-bold text-navy-dark mb-0 text-uppercase">3. Đánh giá năng lực</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered text-center align-middle mb-0 custom-result-table">
                            <thead class="table-navy-custom">
                                <tr>
                                    <th width="40%">Năng lực chung</th>
                                    <th width="15%">HK1</th>
                                    <th width="15%">HK2</th>
                                    <th width="30%">Nhận xét chung</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <?php $first = true; foreach($nl_data as $nl): ?>
                                    <tr>
                                        <td class="text-start fw-bold text-navy-dark ps-3"><?= htmlspecialchars($nl['ten']) ?></td>
                                        <td class="fw-semibold text-primary"><?= getTenMucDat($nl['HK1']['muc_dat']) ?></td>
                                        <td class="fw-semibold text-primary"><?= getTenMucDat($nl['HK2']['muc_dat']) ?></td>
                                        <?php if($first): ?>
                                            <td rowspan="<?= count($nl_data) ?>" class="text-start small text-muted align-top p-3"><?= htmlspecialchars($nl['HK2']['nhan_xet'] ?? '') ?></td>
                                            <?php $first = false; ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. KẾT QUẢ CUỐI NĂM (Chỉ hiện khi học bạ đã được chốt) -->
    <?php if ($da_chot): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4 main-card summary-card">
            <div class="card-body p-4">
                <h5 class="fw-bold text-navy-bright mb-4 text-center text-uppercase">Kết quả tổng hợp cuối năm</h5>
                <div class="row text-center g-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <p class="text-muted fw-semibold mb-2">Đánh giá chung</p>
                            <h5 class="fw-bold text-success mb-0"><?= ($hb_info['duoc_len_lop'] == 1) ? 'Hoàn thành' : 'Chưa hoàn thành' ?></h5>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <p class="text-muted fw-semibold mb-2">Khen thưởng / Danh hiệu</p>
                            <h5 class="fw-bold text-danger mb-0"><?= !empty($hb_info['danh_hieu']) ? htmlspecialchars($hb_info['danh_hieu']) : 'Không có' ?></h5>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <p class="text-muted fw-semibold mb-2">Trạng thái lên lớp</p>
                            <h5 class="fw-bold text-navy-dark mb-0"><?= ($hb_info['duoc_len_lop'] == 1) ? 'Được lên lớp' : 'Ở lại lớp' ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary border-0 shadow-sm fw-bold text-center py-3">
            Kết quả học bạ cuối năm của năm học này chưa được giáo viên chủ nhiệm chốt.
        </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>
<script src="<?= $base_url ?>assets/js/results_student.js"></script>