<?php
session_start();
if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';

$ma_gv = $_SESSION['user_id']; 

// 1. Lấy thông tin thống kê số ngày nghỉ trong năm hiện tại (những đơn 'da duyet')
$sql_stats = "
    SELECT SUM(DATEDIFF(den_ngay, tu_ngay) + 1) as total_days 
    FROM don_xin_nghi 
    WHERE ma_can_bo = ? AND trang_thai = 'da duyet' AND YEAR(tu_ngay) = YEAR(CURDATE())
";
$stmt_stats = mysqli_prepare($conn, $sql_stats);
mysqli_stmt_bind_param($stmt_stats, "s", $ma_gv);
mysqli_stmt_execute($stmt_stats);
$stats_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stats));

$tong_ngay_phep = 12; // Mặc định mỗi GV có 12 ngày phép/năm theo luật
$da_nghi = $stats_result['total_days'] ? (int)$stats_result['total_days'] : 0;
$con_lai = $tong_ngay_phep - $da_nghi;

// 2. Lấy danh sách giáo viên khác để đề xuất dạy thay
$sql_gv = "
    SELECT nd.ma_nguoi_dung, nd.ho, nd.ten 
    FROM can_bo cb
    JOIN nguoi_dung nd ON cb.ma_nguoi_dung = nd.ma_nguoi_dung
    WHERE cb.ma_nguoi_dung != ? AND nd.ma_vai_tro IN ('GV', 'BGH')
    ORDER BY nd.ten, nd.ho
";
$stmt_gv = mysqli_prepare($conn, $sql_gv);
mysqli_stmt_bind_param($stmt_gv, "s", $ma_gv);
mysqli_stmt_execute($stmt_gv);
$ds_giao_vien = mysqli_fetch_all(mysqli_stmt_get_result($stmt_gv), MYSQLI_ASSOC);

// 3. Lấy lịch sử đơn xin nghỉ của bản thân
$sql_history = "SELECT * FROM don_xin_nghi WHERE ma_can_bo = ? ORDER BY ngay_tao DESC";
$stmt_history = mysqli_prepare($conn, $sql_history);
mysqli_stmt_bind_param($stmt_history, "s", $ma_gv);
mysqli_stmt_execute($stmt_history);
$history_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt_history), MYSQLI_ASSOC);

?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="../assets/css/leave_request.css">

<div class="container-fluid py-4 bg-slate-50">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0 text-navy-dark">Nghỉ phép</h4>
    </div>

    <!-- Thống kê -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 h-100 stat-card" style="border-left: 4px solid var(--navy-bright) !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" style="width: 50px; height: 50px; font-size: 1.2rem;">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div>
                        <p class="text-muted small fw-bold text-uppercase mb-1">Số ngày phép năm</p>
                        <h3 class="fw-bold text-navy-dark mb-0"><?= $tong_ngay_phep ?> <span class="fs-6 text-muted fw-medium">ngày</span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 h-100 stat-card" style="border-left: 4px solid #f59e0b !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning" style="width: 50px; height: 50px; font-size: 1.2rem;">
                        <i class="fa-solid fa-calendar-minus"></i>
                    </div>
                    <div>
                        <p class="text-muted small fw-bold text-uppercase mb-1">Đã nghỉ</p>
                        <h3 class="fw-bold text-navy-dark mb-0"><?= $da_nghi ?> <span class="fs-6 text-muted fw-medium">ngày</span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 h-100 stat-card" style="border-left: 4px solid #10b981 !important;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success" style="width: 50px; height: 50px; font-size: 1.2rem;">
                        <i class="fa-solid fa-leaf"></i>
                    </div>
                    <div>
                        <p class="text-muted small fw-bold text-uppercase mb-1">Còn lại</p>
                        <h3 class="fw-bold text-success mb-0"><?= $con_lai ?> <span class="fs-6 text-muted fw-medium">ngày</span></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Cột trái: Form tạo đơn -->
        <div class="col-lg-7 col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden main-card">
                <div class="card-header bg-light border-bottom p-4">
                    <h5 class="fw-bold text-navy-dark mb-1">Tạo đơn xin nghỉ phép</h5>
                    
                </div>
                <div class="card-body p-4">
                    <form id="leaveForm">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small text-muted">Loại nghỉ phép <span class="text-danger">*</span></label>
                                <select class="form-select shadow-none custom-input" id="loaiNghi" required>
                                    <option value="" disabled selected>-- Chọn loại nghỉ phép --</option>
                                    <option value="Nghỉ ốm">Nghỉ ốm (Có giấy khám bệnh)</option>
                                    <option value="Nghỉ việc cá nhân (Có lương)">Nghỉ việc cá nhân (Có lương)</option>
                                    <option value="Nghỉ việc cá nhân (Không lương)">Nghỉ việc cá nhân (Không lương)</option>
                                    <option value="Nghỉ thai sản">Nghỉ thai sản</option>
                                    <option value="Công tác/Tập huấn">Cử đi công tác / Tập huấn</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small text-muted">Giáo viên dạy thay (Nếu có)</label>
                                <select class="form-select shadow-none custom-input" id="gvDayThay">
                                    <option value="">-- Trống --</option>
                                    <?php foreach($ds_giao_vien as $gv): ?>
                                        <option value="<?= $gv['ma_nguoi_dung'] ?>">Cô/Thầy <?= $gv['ho'] . ' ' . $gv['ten'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small text-muted">Từ ngày <span class="text-danger">*</span></label>
                                <input type="date" class="form-control shadow-none custom-input" id="tuNgay" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small text-muted">Đến ngày <span class="text-danger">*</span></label>
                                <input type="date" class="form-control shadow-none custom-input" id="denNgay" min="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small text-muted">Lý do cụ thể <span class="text-danger">*</span></label>
                            <textarea class="form-control shadow-none custom-input" id="lyDo" rows="3" placeholder="Trình bày lý do xin nghỉ phép..." required></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase small text-muted">Tài liệu đính kèm (Giấy khám bệnh, Giấy mời...)</label>
                            <div class="upload-box text-center p-4 rounded-3 border-2 border-dashed" id="uploadBox">
                                <i class="fa-solid fa-cloud-arrow-up fs-2 text-navy-bright mb-2"></i>
                                <p class="mb-1 fw-medium">Click để chọn file</p>
                                <p class="small text-muted mb-0">Định dạng hỗ trợ: PDF, JPG, PNG (Tối đa 5MB)</p>
                                <input type="file" id="fileDinhKem" class="d-none" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div id="fileNameDisplay" class="text-success small mt-2 fw-bold d-none"></div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <button type="reset" class="btn btn-light border px-4 fw-bold text-secondary">Hủy bỏ</button>
                            <button type="submit" class="btn btn-navy-dark px-4 fw-bold shadow-sm d-flex align-items-center gap-2" id="btnSubmit">
                                <i class="fa-regular fa-paper-plane"></i> Gửi Đơn Xin Phép
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Cột phải: Lịch sử -->
        <div class="col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden main-card h-100 d-flex flex-column">
                <div class="card-header bg-light border-bottom p-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-navy-dark mb-0">Lịch sử đơn từ</h6>
                    <span class="badge bg-navy-bright rounded-pill"><?= count($history_list) ?> đơn</span>
                </div>
                <div class="card-body p-3 overflow-y-auto custom-scrollbar" style="max-height: 600px;" id="historyList">
                    
                    <?php if(empty($history_list)): ?>
                        <div class="text-center text-muted p-4">
                            <i class="fa-solid fa-folder-open fs-1 mb-2 opacity-50"></i>
                            <p class="mb-0">Chưa có lịch sử xin nghỉ phép.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach($history_list as $don): 
                        // Bóc tách loại nghỉ và lý do từ CSDL (Quy ước format: [Loại] Lý do)
                        $fullLyDo = $don['ly_do'];
                        $loaiNghi = "Nghỉ phép";
                        $lyDoChiTiet = $fullLyDo;
                        if(preg_match('/^\[(.*?)\]\s*(.*)$/', $fullLyDo, $matches)) {
                            $loaiNghi = $matches[1];
                            $lyDoChiTiet = $matches[2];
                        }

                        $soNgay = (strtotime($don['den_ngay']) - strtotime($don['tu_ngay'])) / (60 * 60 * 24) + 1;
                        $trangThai = strtolower($don['trang_thai']);
                        
                        $bgClass = 'bg-warning bg-opacity-10 border-warning';
                        $badgeClass = 'bg-warning text-dark';
                        $lineClass = 'bg-warning';
                        $txtStatus = 'Chờ duyệt';

                        if(str_contains($trangThai, 'da duyet') || str_contains($trangThai, 'đã duyệt')) {
                            $bgClass = 'bg-success bg-opacity-10 border-success';
                            $badgeClass = 'bg-success';
                            $lineClass = 'bg-success';
                            $txtStatus = 'Đã duyệt';
                        } elseif(str_contains($trangThai, 'tu choi') || str_contains($trangThai, 'từ chối')) {
                            $bgClass = 'bg-danger bg-opacity-10 border-danger opacity-75';
                            $badgeClass = 'bg-danger';
                            $lineClass = 'bg-danger';
                            $txtStatus = 'Từ chối';
                        }
                    ?>
                    <!-- Item Lịch sử -->
                    <div class="history-item position-relative border rounded-3 p-3 mb-3 <?= $bgClass ?>">
                        <div class="position-absolute top-0 start-0 h-100 <?= $lineClass ?>" style="width: 4px; border-radius: 4px 0 0 4px;"></div>
                        <div class="d-flex justify-content-between align-items-start mb-2 ps-2">
                            <span class="badge <?= $badgeClass ?>"><?= $txtStatus ?></span>
                            <span class="text-muted small" style="font-size: 0.7rem;"><?= date('d/m/Y', strtotime($don['ngay_tao'])) ?></span>
                        </div>
                        <h6 class="fw-bold text-body mb-1 ps-2"><?= htmlspecialchars($loaiNghi) ?></h6>
                        <p class="text-muted small mb-2 ps-2">
                            <i class="fa-regular fa-clock me-1"></i> 
                            <?= date('d/m', strtotime($don['tu_ngay'])) ?> - <?= date('d/m/Y', strtotime($don['den_ngay'])) ?> (<?= $soNgay ?> ngày)
                        </p>
                        <p class="text-secondary small fst-italic mb-0 ps-2 text-truncate">"<?= htmlspecialchars($lyDoChiTiet) ?>"</p>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="../assets/js/leave_request.js"></script>