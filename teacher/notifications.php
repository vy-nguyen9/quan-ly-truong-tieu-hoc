<?php
session_start();
if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';

$ma_gv = $_SESSION['user_id'];

// 1. Lấy mã trường của giáo viên hiện tại
$sql_truong = "SELECT ma_truong FROM can_bo WHERE ma_nguoi_dung = ?";
$stmt_truong = mysqli_prepare($conn, $sql_truong);
mysqli_stmt_bind_param($stmt_truong, "s", $ma_gv);
mysqli_stmt_execute($stmt_truong);
$truong_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_truong));
$ma_truong = $truong_info['ma_truong'];

// 2. Lấy danh sách thông báo (Gửi đích danh HOẶC Gửi chung cho toàn trường/giáo viên)
$sql_noti = "
    SELECT tb.ma_thong_bao, tb.tieu_de, tb.noi_dung, tb.ngay_gui,
           nd.ho, nd.ten, vt.ten_vai_tro,
           COALESCE(ntb.da_doc, 0) AS da_doc
    FROM thong_bao tb
    LEFT JOIN nguoi_dung nd ON tb.ma_nguoi_gui = nd.ma_nguoi_dung
    LEFT JOIN vai_tro vt ON nd.ma_vai_tro = vt.ma_vai_tro
    LEFT JOIN nhan_thong_bao ntb ON tb.ma_thong_bao = ntb.ma_thong_bao AND ntb.ma_nguoi_nhan = ?
    WHERE (ntb.ma_nguoi_nhan = ?) 
       OR (tb.ma_truong = ? AND tb.ma_doi_tuong IN ('all', 'gv'))
    ORDER BY tb.ngay_gui DESC
";
$stmt_noti = mysqli_prepare($conn, $sql_noti);
mysqli_stmt_bind_param($stmt_noti, "sss", $ma_gv, $ma_gv, $ma_truong);
mysqli_stmt_execute($stmt_noti);
$notifications = mysqli_fetch_all(mysqli_stmt_get_result($stmt_noti), MYSQLI_ASSOC);

// Hàm tính thời gian trôi qua cho thân thiện
function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->d == 0) {
        if ($diff->h == 0) {
            if ($diff->i == 0) return "Vừa xong";
            return $diff->i . " phút trước";
        }
        return $diff->h . " giờ trước";
    } elseif ($diff->d == 1) {
        return "Hôm qua lúc " . $ago->format('H:i');
    } else {
        return $ago->format('d/m/Y H:i');
    }
}

$unread_count = 0;
foreach($notifications as $n) {
    if ($n['da_doc'] == 0) $unread_count++;
}
?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="../assets/css/notifications.css">

<div class="container-fluid py-4 bg-slate-50">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0 text-navy-dark">Trung tâm Thông báo</h4>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden main-card">
        <div class="card-header bg-light border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <ul class="nav nav-pills custom-pills" id="notiTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold" id="all-tab" data-bs-toggle="pill" data-bs-target="#all" type="button" role="tab" aria-selected="true">Tất cả</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold position-relative" id="unread-tab" data-bs-toggle="pill" data-bs-target="#unread" type="button" role="tab" aria-selected="false">
                        Chưa đọc 
                        <?php if($unread_count > 0): ?>
                            <span class="badge bg-danger ms-1 rounded-pill" id="badge-unread-count"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </button>
                </li>
            </ul>
            <button class="btn btn-outline-success fw-bold btn-sm shadow-sm px-3" id="btnMarkAllRead">
                <i class="fa-solid fa-check-double me-1"></i> Đánh dấu tất cả đã đọc
            </button>
        </div>
        
        <div class="card-body p-0">
            <div class="tab-content" id="notiTabContent">
                
                <!-- Tab Tất cả -->
                <div class="tab-pane fade show active" id="all" role="tabpanel" tabindex="0">
                    <div class="noti-list overflow-y-auto custom-scrollbar" style="max-height: calc(100vh - 250px);">
                        <?php if(empty($notifications)): ?>
                            <div class="text-center text-muted p-5">
                                <i class="fa-regular fa-bell-slash fs-1 mb-3 opacity-50"></i>
                                <h5>Không có thông báo nào</h5>
                            </div>
                        <?php else: ?>
                            <?php foreach($notifications as $noti): 
                                $is_unread = ($noti['da_doc'] == 0);
                            ?>
                            <div class="noti-item border-bottom p-4 position-relative <?= $is_unread ? 'unread bg-primary bg-opacity-10' : '' ?>" data-id="<?= $noti['ma_thong_bao'] ?>" data-status="<?= $is_unread ? '0' : '1' ?>">
                                <?php if($is_unread): ?>
                                    <div class="position-absolute top-0 start-0 h-100 bg-primary" style="width: 4px;"></div>
                                <?php endif; ?>
                                
                                <div class="d-flex gap-3">
                                    <div class="flex-shrink-0">
                                        <div class="noti-icon rounded-circle d-flex align-items-center justify-content-center text-white <?= $is_unread ? 'bg-navy-bright shadow-sm' : 'bg-secondary' ?>" style="width: 45px; height: 45px; font-size: 1.1rem;">
                                            <i class="fa-solid fa-bullhorn"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="fw-bold mb-0 <?= $is_unread ? 'text-navy-dark' : 'text-body' ?>">
                                                <?= htmlspecialchars($noti['tieu_de']) ?>
                                            </h6>
                                            <small class="text-muted text-nowrap ms-3 fw-medium" style="font-size: 0.75rem;">
                                                <?= time_elapsed_string($noti['ngay_gui']) ?>
                                            </small>
                                        </div>
                                        <p class="small text-muted mb-2">
                                            <strong>Người gửi:</strong> <?= htmlspecialchars($noti['ten_vai_tro'] . ' ' . $noti['ho'] . ' ' . $noti['ten']) ?>
                                        </p>
                                        <div class="noti-content text-secondary" style="font-size: 0.9rem; line-height: 1.5;">
                                            <?= nl2br(htmlspecialchars($noti['noi_dung'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab Chưa đọc -->
                <div class="tab-pane fade" id="unread" role="tabpanel" tabindex="0">
                    <div class="noti-list overflow-y-auto custom-scrollbar" style="max-height: calc(100vh - 250px);">
                        <?php if($unread_count == 0): ?>
                            <div class="text-center text-muted p-5">
                                <i class="fa-solid fa-check-circle fs-1 mb-3 opacity-50 text-success"></i>
                                <h5>Bạn đã đọc tất cả thông báo</h5>
                            </div>
                        <?php else: ?>
                            <?php foreach($notifications as $noti): 
                                if ($noti['da_doc'] == 1) continue;
                            ?>
                            <div class="noti-item border-bottom p-4 position-relative unread bg-primary bg-opacity-10" data-id="<?= $noti['ma_thong_bao'] ?>" data-status="0">
                                <div class="position-absolute top-0 start-0 h-100 bg-primary" style="width: 4px;"></div>
                                <div class="d-flex gap-3">
                                    <div class="flex-shrink-0">
                                        <div class="noti-icon rounded-circle d-flex align-items-center justify-content-center text-white bg-navy-bright shadow-sm" style="width: 45px; height: 45px; font-size: 1.1rem;">
                                            <i class="fa-solid fa-bullhorn"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="fw-bold mb-0 text-navy-dark">
                                                <?= htmlspecialchars($noti['tieu_de']) ?>
                                            </h6>
                                            <small class="text-muted text-nowrap ms-3 fw-medium" style="font-size: 0.75rem;">
                                                <?= time_elapsed_string($noti['ngay_gui']) ?>
                                            </small>
                                        </div>
                                        <p class="small text-muted mb-2">
                                            <strong>Người gửi:</strong> <?= htmlspecialchars($noti['ten_vai_tro'] . ' ' . $noti['ho'] . ' ' . $noti['ten']) ?>
                                        </p>
                                        <div class="noti-content text-secondary" style="font-size: 0.9rem; line-height: 1.5;">
                                            <?= nl2br(htmlspecialchars($noti['noi_dung'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="../assets/js/notifications.js"></script>