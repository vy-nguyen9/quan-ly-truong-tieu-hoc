<?php
session_start();
// Kiểm tra quyền: Chỉ cho phép học sinh (HS) truy cập
if (!isset($_SESSION['role']) || strtoupper($_SESSION['role']) !== 'HS') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';

$ma_hs = $_SESSION['user_id'];
$msg = '';
$msgType = '';

// Lấy danh mục cho hiển thị (vì disabled nên chỉ cần hiển thị tên trực tiếp)
$dt_list = mysqli_query($conn, "SELECT * FROM dan_toc");
$qt_list = mysqli_query($conn, "SELECT * FROM quoc_tich");
$tinh_list = mysqli_query($conn, "SELECT * FROM tinh_thanh");
$xa_list = mysqli_query($conn, "SELECT * FROM xa_phuong");
$diem_truong_list = mysqli_query($conn, "SELECT * FROM diem_truong");

// Lấy năm học hiện tại
$sql_nam = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
$result_nam = mysqli_query($conn, $sql_nam);
$nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam)['ma_nam'] ?? '2026-2027';

// XỬ LÝ KHI SUBMIT FORM (Chỉ xử lý Avatar và Mật khẩu)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matKhauMoi = $_POST['MatKhauMoi'] ?? '';
    $nhapLaiMatKhau = $_POST['NhapLaiMatKhau'] ?? '';
    $errors = [];

    // 1. Xử lý Mật khẩu
    if (!empty($matKhauMoi)) {
        if ($matKhauMoi !== $nhapLaiMatKhau) {
            $errors[] = "Mật khẩu nhập lại không khớp.";
        }
    }

    // 2. Xử lý Upload Ảnh đại diện
    $anh_dai_dien_path = null;
    if (isset($_FILES['AnhDaiDien']) && $_FILES['AnhDaiDien']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['AnhDaiDien']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $uploadDir = '../uploads/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $newFileName = 'avatar_' . $ma_hs . '_' . time() . '.' . $ext;
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['AnhDaiDien']['tmp_name'], $destPath)) {
                $anh_dai_dien_path = 'uploads/avatars/' . $newFileName;
            } else {
                $errors[] = "Không thể lưu tệp ảnh. Vui lòng thử lại.";
            }
        } else {
            $errors[] = "Ảnh đại diện chỉ chấp nhận định dạng JPG, PNG, WEBP.";
        }
    }

    // Cập nhật CSDL
    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            // Cập nhật Avatar (Nguoi_dung)
            if ($anh_dai_dien_path) {
                $sql_avatar = "UPDATE nguoi_dung SET anh_dai_dien = ? WHERE ma_nguoi_dung = ?";
                $stmt_avatar = mysqli_prepare($conn, $sql_avatar);
                mysqli_stmt_bind_param($stmt_avatar, "ss", $anh_dai_dien_path, $ma_hs);
                mysqli_stmt_execute($stmt_avatar);
                
                // Ghi đè Session để Header load lại ảnh tức thì
                $_SESSION['user_avatar'] = $anh_dai_dien_path;
            }

            // Cập nhật Mật khẩu (Tai_khoan)
            if (!empty($matKhauMoi)) {
                $hash = password_hash($matKhauMoi, PASSWORD_DEFAULT);
                $sql_pw = "UPDATE tai_khoan SET mat_khau = ? WHERE ma_nguoi_dung = ?";
                $stmt_pw = mysqli_prepare($conn, $sql_pw);
                mysqli_stmt_bind_param($stmt_pw, "ss", $hash, $ma_hs);
                mysqli_stmt_execute($stmt_pw);
            }

            mysqli_commit($conn);
            if ($anh_dai_dien_path || !empty($matKhauMoi)) {
                $msg = "Cập nhật tài khoản thành công!";
                $msgType = "success";
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $msg = "Lỗi hệ thống: " . $e->getMessage();
            $msgType = "danger";
        }
    } else {
        $msg = implode("<br>", $errors);
        $msgType = "danger";
    }
}

// -------------------------------------------------------------
// TRUY VẤN LẤY DỮ LIỆU HIỂN THỊ LÊN GIAO DIỆN
// -------------------------------------------------------------
$sql = "SELECT nd.*, hs.ma_diem_truong, hsl.ngay_vao_lop, hsl.khoi, hsl.stt, dt.ten_diem
        FROM nguoi_dung nd 
        JOIN hoc_sinh hs ON nd.ma_nguoi_dung = hs.ma_nguoi_dung 
        LEFT JOIN hoc_sinh_lop hsl ON hs.ma_nguoi_dung = hsl.ma_hoc_sinh AND hsl.ma_nam = ?
        LEFT JOIN diem_truong dt ON hs.ma_diem_truong = dt.ma_diem_truong
        WHERE nd.ma_nguoi_dung = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $nam_hoc_hien_tai, $ma_hs);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) die("Lỗi: Không tìm thấy hồ sơ học sinh.");

// Lấy thông tin địa chỉ thường trú
$sql_dc = "SELECT dc.*, t.ten_tinh_thanh, x.ten_phuong_xa 
           FROM dia_chi_chi_tiet dc
           LEFT JOIN tinh_thanh t ON dc.ma_tinh_thanh = t.ma_tinh_thanh
           LEFT JOIN xa_phuong x ON dc.ma_xa_phuong = x.ma_xa_phuong
           WHERE ma_nguoi_dung = ? AND ma_loai_dia_chi = 'DC01' LIMIT 1";
$stmt_dc = mysqli_prepare($conn, $sql_dc);
mysqli_stmt_bind_param($stmt_dc, "s", $ma_hs);
mysqli_stmt_execute($stmt_dc);
$dia_chi_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dc));

// Tìm chuỗi hiển thị cho Dân tộc và Quốc tịch
$ten_dan_toc = '';
foreach ($dt_list as $dt) {
    if ($dt['ma_dan_toc'] === $user['ma_dan_toc']) $ten_dan_toc = $dt['ten_dan_toc'];
}
$ten_quoc_tich = '';
foreach ($qt_list as $qt) {
    if ($qt['ma_quoc_tich'] === $user['ma_quoc_tich']) $ten_quoc_tich = $qt['ten_quoc_tich'];
}
?>

<?php include '../includes/header.php'; ?>

<!-- Gọi lại file CSS tái sử dụng từ module giáo viên -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/profile.css">

<div class="container-fluid py-4 bg-slate-50">
    <div class="mb-4">
        <h4 class="fw-bold text-navy-dark mb-1"><i class="fa-solid fa-address-card me-2"></i>Hồ sơ cá nhân</h4>
        <p class="text-muted small mb-0">Học sinh chỉ được thay đổi Ảnh đại diện và Mật khẩu. Mọi thông tin khác vui lòng liên hệ Giáo viên chủ nhiệm để cập nhật.</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?> border-0 shadow-sm mb-4 fw-bold">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="row g-4" id="profileForm">
        <!-- KHỐI TRÁI: THÔNG TIN CHI TIẾT (READONLY TOÀN BỘ) -->
        <div class="col-xl-8 col-lg-7">
            
            <!-- 1. THÔNG TIN LÝ LỊCH -->
            <div class="card p-4 shadow-sm border-0 mb-4 main-card">
                <h6 class="fw-bold text-navy-bright mb-3 section-title">1. Thông tin lý lịch cá nhân</h6>
                <div class="row g-3 mb-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Họ đệm</label>
                        <input type="text" class="form-control readonly-input fw-bold" value="<?= htmlspecialchars($user['ho']) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Tên</label>
                        <input type="text" class="form-control readonly-input fw-bold" value="<?= htmlspecialchars($user['ten']) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Ngày sinh</label>
                        <input type="date" class="form-control readonly-input" value="<?= $user['ngay_sinh'] ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Giới tính</label>
                        <input type="text" class="form-control readonly-input" value="<?= htmlspecialchars($user['gioi_tinh'] ?? 'Chưa rõ') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Số định danh (CCCD)</label>
                        <input type="text" class="form-control readonly-input" value="<?= htmlspecialchars($user['so_cccd'] ?? '---') ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Dân tộc</label>
                        <input type="text" class="form-control readonly-input" value="<?= htmlspecialchars($ten_dan_toc) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Quốc tịch</label>
                        <input type="text" class="form-control readonly-input" value="<?= htmlspecialchars($ten_quoc_tich) ?>" readonly>
                    </div>
                </div>
            </div>

            <!-- 2. ĐỊA CHỈ & LIÊN HỆ PHỤ HUYNH -->
            <div class="card p-4 shadow-sm border-0 mb-4 main-card">
                <h6 class="fw-bold text-navy-bright mb-3 section-title">2. Thông tin liên hệ Phụ huynh & Địa chỉ</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Số điện thoại phụ huynh</label>
                        <input type="text" class="form-control readonly-input" value="<?= htmlspecialchars($user['sdt'] ?? '---') ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Email phụ huynh</label>
                        <input type="email" class="form-control readonly-input" value="<?= htmlspecialchars($user['email'] ?? '---') ?>" readonly>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Tỉnh / Thành phố</label>
                        <input type="text" class="form-control readonly-input" value="<?= htmlspecialchars($dia_chi_info['ten_tinh_thanh'] ?? '---') ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Quận / Huyện / Xã / Phường</label>
                        <input type="text" class="form-control readonly-input" value="<?= htmlspecialchars($dia_chi_info['ten_phuong_xa'] ?? '---') ?>" readonly>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold text-muted">Số nhà, Tên đường, Thôn xóm</label>
                        <input type="text" class="form-control readonly-input" value="<?= htmlspecialchars($dia_chi_info['so_nha_ten_duong'] ?? '---') ?>" readonly>
                    </div>
                </div>
            </div>

            <!-- 3. THÔNG TIN HỌC TẬP -->
            <div class="card p-4 shadow-sm border-0 main-card">
                <h6 class="fw-bold text-navy-bright mb-3 section-title">3. Thông tin học tập năm <?= htmlspecialchars($nam_hoc_hien_tai) ?></h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Mã học sinh</label>
                        <input type="text" class="form-control readonly-input fw-bold" value="<?= htmlspecialchars($ma_hs) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Lớp</label>
                        <input type="text" class="form-control readonly-input fw-bold" value="<?= $user['khoi'] ? ($user['khoi'].'/'.$user['stt']) : 'Chưa xếp lớp' ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Điểm trường</label>
                        <input type="text" class="form-control readonly-input" value="<?= htmlspecialchars($user['ten_diem'] ?? 'Chưa xác định') ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- KHỐI PHẢI: ẢNH ĐẠI DIỆN & MẬT KHẨU (CHO PHÉP SỬA) -->
        <div class="col-xl-4 col-lg-5">
            
            <!-- ẢNH ĐẠI DIỆN -->
            <div class="card p-4 shadow-sm border-0 mb-4 text-center main-card">
                <h6 class="fw-bold text-navy-dark mb-3 border-bottom pb-2 text-start">Ảnh đại diện</h6>
                <div class="mb-3 d-flex justify-content-center align-items-center">
                    <div class="avatar-preview-box">
                        <?php if (!empty($user['anh_dai_dien'])): ?>
                            <img src="<?= $base_url . htmlspecialchars($user['anh_dai_dien']) ?>" alt="Avatar" class="avatar-img" id="avatarPreviewImg">
                        <?php else: ?>
                            <div class="avatar-placeholder" id="avatarPreviewImg"><i class="fa-solid fa-user-graduate"></i></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-start">
                    <label class="form-label small fw-semibold">Thay đổi ảnh hồ sơ (JPG/PNG)</label>
                    <input type="file" name="AnhDaiDien" class="form-control custom-input form-control-sm mb-4" accept=".jpg, .jpeg, .png, .webp" id="avatarInput">
                </div>
            </div>

            <!-- MẬT KHẨU -->
            <div class="card p-4 shadow-sm border-0 main-card">
                <h6 class="fw-bold text-danger mb-3 border-bottom pb-2">Thay đổi mật khẩu</h6>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Mật khẩu mới</label>
                    <input type="password" name="MatKhauMoi" class="form-control custom-input" placeholder="Bỏ trống nếu không đổi...">
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold">Nhập lại mật khẩu mới</label>
                    <input type="password" name="NhapLaiMatKhau" class="form-control custom-input" placeholder="Xác nhận mật khẩu...">
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-navy-bright fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Lưu thông tin mới</button>
                    <a href="index.php" class="btn btn-outline-secondary fw-bold bg-white">Hủy bỏ</a>
                </div>
            </div>

        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Gọi lại file JS tái sử dụng từ module giáo viên để preview ảnh -->
<script src="<?= $base_url ?>assets/js/profile.js"></script>