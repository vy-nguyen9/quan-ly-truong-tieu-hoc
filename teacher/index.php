<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])) {
    header("Location: ../auth/login.php");
    exit();
}

$ma_gv = $_SESSION['user_id'];

// Lấy thông tin Năm học hiện tại
$sql_nam = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
$result_nam = mysqli_query($conn, $sql_nam);
$nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam)['ma_nam'] ?? '2026-2027';

// 1. Lấy Lịch dạy (Thời khóa biểu) của HÔM NAY
$currentDayOfWeek = date('N') + 1; // date('N') trả về 1 (Thứ 2) -> 7 (CN). CSDL lưu từ 2 -> 8
$sql_tkb = "
    SELECT ct.tiet, mh.ten_mon, lh.khoi, lh.stt, lh.ma_nhom_lop
    FROM chi_tiet_tkb ct
    JOIN thoi_khoa_bieu tkb ON ct.ma_tkb = tkb.ma_tkb
    JOIN mon_hoc mh ON tkb.ma_mon_hoc = mh.ma_mon
    JOIN lop_hoc lh ON tkb.ma_nam = lh.ma_nam AND tkb.khoi = lh.khoi AND tkb.ma_nhom_lop = lh.ma_nhom_lop AND tkb.stt = lh.stt AND tkb.ma_truong = lh.ma_truong
    WHERE tkb.ma_can_bo = ? AND ct.thu = ? AND tkb.ma_nam = ?
    ORDER BY ct.tiet ASC
";
$stmt_tkb = mysqli_prepare($conn, $sql_tkb);
mysqli_stmt_bind_param($stmt_tkb, "sis", $ma_gv, $currentDayOfWeek, $nam_hoc_hien_tai);
mysqli_stmt_execute($stmt_tkb);
$lich_day_hom_nay = mysqli_fetch_all(mysqli_stmt_get_result($stmt_tkb), MYSQLI_ASSOC);

// 2. Lấy 5 Thông báo mới nhất
$header_truong = '';
$sql_htr = "SELECT ma_truong FROM can_bo WHERE ma_nguoi_dung = ?";
$stmt_htr = mysqli_prepare($conn, $sql_htr);
mysqli_stmt_bind_param($stmt_htr, "s", $ma_gv);
mysqli_stmt_execute($stmt_htr);
$res_htr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_htr));
if ($res_htr) $header_truong = $res_htr['ma_truong'];

$sql_tb = "
    SELECT tb.ma_thong_bao, tb.tieu_de, tb.noi_dung, tb.ngay_gui, COALESCE(ntb.da_doc, 0) AS da_doc
    FROM thong_bao tb
    LEFT JOIN nhan_thong_bao ntb ON tb.ma_thong_bao = ntb.ma_thong_bao AND ntb.ma_nguoi_nhan = ?
    WHERE (ntb.ma_nguoi_nhan = ?) OR (tb.ma_truong = ? AND tb.ma_doi_tuong IN ('all', 'gv'))
    ORDER BY tb.ngay_gui DESC LIMIT 5
";
$stmt_tb = mysqli_prepare($conn, $sql_tb);
mysqli_stmt_bind_param($stmt_tb, "sss", $ma_gv, $ma_gv, $header_truong);
mysqli_stmt_execute($stmt_tb);
$thong_bao_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt_tb), MYSQLI_ASSOC);

// Hàm tính thời gian
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime) {
        $now = new DateTime; $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        if ($diff->d == 0) {
            if ($diff->h == 0) return ($diff->i == 0) ? "Vừa xong" : $diff->i . " phút trước";
            return $diff->h . " giờ trước";
        } elseif ($diff->d == 1) return "Hôm qua";
        else return $ago->format('d/m/Y');
    }
}
?>

<?php include '../includes/header.php'; ?>
<!-- Import CSS riêng cho Dashboard -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/dashboard.css">

<div class="container-fluid py-4 bg-slate-50">
    <!-- Dòng chào mừng -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-navy-dark mb-1">Tổng quan hệ thống</h4>
            <p class="text-muted mb-0">Xin chào, <span class="fw-bold text-navy-bright"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>! Chúc bạn một ngày làm việc hiệu quả.</p>
        </div>
        <div class="d-none d-md-block text-end">
            <h5 class="fw-bold text-navy-dark mb-0" id="clock">--:--:--</h5>
            <small class="text-muted" id="currentDateFull">Hôm nay</small>
        </div>
    </div>

    <div class="row g-4">
        <!-- KHỐI TRÁI (Thời khóa biểu & Thống kê nhanh) -->
        <div class="col-xl-8 col-lg-7">
            
            <!-- Widget Thống kê nhanh -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 main-card widget-card">
                        <div class="card-body d-flex align-items-center p-4">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1">Số tiết dạy hôm nay</h6>
                                <h3 class="fw-bold text-navy-dark mb-0"><?= count($lich_day_hom_nay) ?> <span class="fs-6 fw-normal text-muted">tiết</span></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 main-card widget-card">
                        <div class="card-body d-flex align-items-center p-4">
                            
                            <div>
                                <h6 class="text-muted fw-semibold mb-1">Năm học hiện tại</h6>
                                <h4 class="fw-bold text-navy-dark mb-0"><?= htmlspecialchars($nam_hoc_hien_tai) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Thời khóa biểu hôm nay -->
            <div class="card border-0 shadow-sm rounded-4 main-card">
                <div class="card-header bg-light border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-navy-dark mb-0">Lịch giảng dạy hôm nay (Thứ <?= $currentDayOfWeek ?>)</h6>
                    <a href="schedule.php" class="btn btn-sm btn-outline-secondary bg-white shadow-sm fw-bold">Xem cả tuần</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center dashboard-table">
                            <thead class="table-navy-custom">
                                <tr>
                                    <th width="15%">Tiết học</th>
                                    <th width="40%" class="text-start">Môn giảng dạy</th>
                                    <th width="25%">Lớp học</th>
                                    
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <?php if(empty($lich_day_hom_nay)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-mug-hot fs-3 mb-2 opacity-50 d-block"></i>
                                            Hôm nay bạn không có lịch lên lớp.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($lich_day_hom_nay as $tiet): ?>
                                        <tr>
                                            <td class="fw-bold text-navy-bright">Tiết <?= $tiet['tiet'] ?></td>
                                            <td class="text-start fw-semibold text-navy-dark">
                                                
                                                <?= htmlspecialchars($tiet['ten_mon']) ?>
                                            </td>
                                            <!-- Sửa Lớp học hiển thị dạng văn bản bình thường -->
                                            <td class="fw-semibold text-navy-dark">
                                                Lớp <?= $tiet['khoi'].'/'.$tiet['stt'] ?>
                                            </td>
                                            
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- KHỐI PHẢI (Calendar & Thông báo) -->
        <div class="col-xl-4 col-lg-5">
            
            <!-- Calendar Widget -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 main-card">
                <div class="card-header bg-light border-bottom p-3">
                    <h6 class="fw-bold text-navy-dark mb-0">Lịch làm việc</h6>
                </div>
                <div class="card-body p-3">
                    <div class="calendar-wrapper">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button id="prevMonth" class="btn btn-sm btn-light border"><i class="fa-solid fa-chevron-left"></i></button>
                            <h6 class="mb-0 fw-bold text-navy-dark" id="monthYear"></h6>
                            <button id="nextMonth" class="btn btn-sm btn-light border"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                        <div class="calendar-grid fw-bold text-muted border-bottom pb-2 mb-2">
                            <div class="cal-day">T2</div><div class="cal-day">T3</div><div class="cal-day">T4</div>
                            <div class="cal-day">T5</div><div class="cal-day">T6</div><div class="cal-day">T7</div>
                            <div class="cal-day text-danger">CN</div>
                        </div>
                        <div class="calendar-grid" id="calendarDays">
                            <!-- JS Generate -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bảng Thông báo -->
            <div class="card border-0 shadow-sm rounded-4 main-card">
                <div class="card-header bg-light border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-navy-dark mb-0"><i class="fa-regular fa-bell me-2"></i>Thông báo mới</h6>
                    <a href="notifications.php" class="text-decoration-none small text-navy-bright fw-bold">Xem tất cả</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush custom-list-group">
                        <?php if(empty($thong_bao_list)): ?>
                            <div class="p-4 text-center text-muted small">Không có thông báo mới.</div>
                        <?php else: ?>
                            <?php foreach($thong_bao_list as $tb): 
                                $unread = ($tb['da_doc'] == 0) ? 'unread-item' : '';
                            ?>
                                <a href="notifications.php" class="list-group-item list-group-item-action border-0 p-3 <?= $unread ?>">
                                    <div class="d-flex gap-3 align-items-start">
                                        <div class="bg-navy-bright bg-opacity-10 text-navy-bright rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px; height:38px;">
                                            <i class="fa-solid fa-bullhorn"></i>
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="fw-bold mb-1 text-navy-dark text-truncate"><?= htmlspecialchars($tb['tieu_de']) ?></h6>
                                            <p class="small text-muted mb-1 text-truncate"><?= htmlspecialchars($tb['noi_dung']) ?></p>
                                            <small class="text-navy-bright fw-semibold" style="font-size: 0.7rem;"><?= time_elapsed_string($tb['ngay_gui']) ?></small>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="<?= $base_url ?>assets/js/dashboard.js"></script>