<?php
session_start();
if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])) {
    header("Location: ../auth/login.php");
    exit();
}
require_once '../config/database.php';

$ma_gv = $_SESSION['user_id'];
$ma_hs = $_GET['id'] ?? '';
if (empty($ma_hs)) die("Không có mã học sinh.");

$sql_nam = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
$result_nam = mysqli_query($conn, $sql_nam);
$nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam)['ma_nam'] ?? '2026-2027';

// BẢO MẬT: Kiểm tra học sinh này có thuộc lớp giáo viên đang chủ nhiệm không
$sql_check = "SELECT hsl.ma_nam 
              FROM hoc_sinh_lop hsl 
              JOIN lop_hoc lh ON hsl.ma_nam=lh.ma_nam AND hsl.khoi=lh.khoi AND hsl.ma_nhom_lop=lh.ma_nhom_lop AND hsl.stt=lh.stt AND hsl.ma_truong=lh.ma_truong 
              WHERE hsl.ma_hoc_sinh = ? AND lh.chu_nhiem = ? AND lh.ma_nam = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "sss", $ma_hs, $ma_gv, $nam_hoc_hien_tai);
mysqli_stmt_execute($stmt_check);
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check))) {
    die("Lỗi bảo mật: Bạn không có quyền chỉnh sửa hồ sơ học sinh này.");
}

$msg = ''; $msgType = '';

// Lấy danh mục
$dt_list = mysqli_query($conn, "SELECT * FROM dan_toc");
$qt_list = mysqli_query($conn, "SELECT * FROM quoc_tich");
$tinh_list = mysqli_query($conn, "SELECT * FROM tinh_thanh");
$xa_list = mysqli_query($conn, "SELECT * FROM xa_phuong");
$diem_truong_list = mysqli_query($conn, "SELECT * FROM diem_truong");

// Xử lý Cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ho = trim($_POST['ho'] ?? '');
    $ten = trim($_POST['ten'] ?? '');
    $ngay_sinh = !empty($_POST['ngay_sinh']) ? $_POST['ngay_sinh'] : null;
    $gioi_tinh = trim($_POST['gioi_tinh'] ?? '');
    $so_cccd = trim($_POST['so_cccd'] ?? '');
    $sdt = trim($_POST['sdt'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ma_quoc_tich = !empty($_POST['ma_quoc_tich']) ? $_POST['ma_quoc_tich'] : null;
    $ma_dan_toc = !empty($_POST['ma_dan_toc']) ? $_POST['ma_dan_toc'] : null;

    $ma_diem_truong = !empty($_POST['ma_diem_truong']) ? $_POST['ma_diem_truong'] : null;
    $ngay_vao_lop = !empty($_POST['ngay_vao_lop']) ? $_POST['ngay_vao_lop'] : null;

    $ma_tinh_thanh = !empty($_POST['ma_tinh_thanh']) ? $_POST['ma_tinh_thanh'] : null;
    $ma_xa_phuong = !empty($_POST['ma_xa_phuong']) ? $_POST['ma_xa_phuong'] : null;
    $so_nha = trim($_POST['so_nha'] ?? '');

    $errors = [];
    $anh_dai_dien_path = null;

    // Ảnh đại diện
    if (isset($_FILES['AnhDaiDien']) && $_FILES['AnhDaiDien']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['AnhDaiDien']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $uploadDir = '../uploads/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $newFileName = 'avatar_' . $ma_hs . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['AnhDaiDien']['tmp_name'], $uploadDir . $newFileName)) {
                $anh_dai_dien_path = 'uploads/avatars/' . $newFileName;
            }
        } else $errors[] = "Ảnh đại diện chỉ nhận JPG, PNG, WEBP.";
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            // Update Nguoi dung
            if ($anh_dai_dien_path) {
                $sql_nd = "UPDATE nguoi_dung SET ho=?, ten=?, ngay_sinh=?, gioi_tinh=?, so_cccd=?, sdt=?, email=?, ma_quoc_tich=?, ma_dan_toc=?, anh_dai_dien=? WHERE ma_nguoi_dung=?";
                $stmt_nd = mysqli_prepare($conn, $sql_nd);
                mysqli_stmt_bind_param($stmt_nd, "sssssssssss", $ho, $ten, $ngay_sinh, $gioi_tinh, $so_cccd, $sdt, $email, $ma_quoc_tich, $ma_dan_toc, $anh_dai_dien_path, $ma_hs);
            } else {
                $sql_nd = "UPDATE nguoi_dung SET ho=?, ten=?, ngay_sinh=?, gioi_tinh=?, so_cccd=?, sdt=?, email=?, ma_quoc_tich=?, ma_dan_toc=? WHERE ma_nguoi_dung=?";
                $stmt_nd = mysqli_prepare($conn, $sql_nd);
                mysqli_stmt_bind_param($stmt_nd, "ssssssssss", $ho, $ten, $ngay_sinh, $gioi_tinh, $so_cccd, $sdt, $email, $ma_quoc_tich, $ma_dan_toc, $ma_hs);
            }
            mysqli_stmt_execute($stmt_nd);

            // Update Hoc Sinh
            $sql_hs_up = "UPDATE hoc_sinh SET ma_diem_truong=? WHERE ma_nguoi_dung=?";
            $stmt_hs_up = mysqli_prepare($conn, $sql_hs_up);
            mysqli_stmt_bind_param($stmt_hs_up, "ss", $ma_diem_truong, $ma_hs);
            mysqli_stmt_execute($stmt_hs_up);

            // Update Hoc sinh lop
            $sql_hsl = "UPDATE hoc_sinh_lop SET ngay_vao_lop=? WHERE ma_hoc_sinh=? AND ma_nam=?";
            $stmt_hsl = mysqli_prepare($conn, $sql_hsl);
            mysqli_stmt_bind_param($stmt_hsl, "sss", $ngay_vao_lop, $ma_hs, $nam_hoc_hien_tai);
            mysqli_stmt_execute($stmt_hsl);

            // Xóa địa chỉ cũ và Insert mới (DC01 - Thường trú)
            $sql_del_dc = "DELETE FROM dia_chi_chi_tiet WHERE ma_nguoi_dung = ? AND ma_loai_dia_chi = 'DC01'";
            $stmt_del_dc = mysqli_prepare($conn, $sql_del_dc);
            mysqli_stmt_bind_param($stmt_del_dc, "s", $ma_hs);
            mysqli_stmt_execute($stmt_del_dc);

            if ($ma_tinh_thanh && $ma_xa_phuong) {
                $sql_ins_dc = "INSERT INTO dia_chi_chi_tiet (ma_nguoi_dung, ma_loai_dia_chi, ma_tinh_thanh, ma_xa_phuong, so_nha_ten_duong) VALUES (?, 'DC01', ?, ?, ?)";
                $stmt_ins_dc = mysqli_prepare($conn, $sql_ins_dc);
                mysqli_stmt_bind_param($stmt_ins_dc, "ssss", $ma_hs, $ma_tinh_thanh, $ma_xa_phuong, $so_nha);
                mysqli_stmt_execute($stmt_ins_dc);
            }

            mysqli_commit($conn);
            $msg = "Cập nhật hồ sơ học sinh thành công!";
            $msgType = "success";
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

// Lấy dữ liệu hiện tại
$sql = "SELECT nd.*, hs.ma_diem_truong, hsl.ngay_vao_lop 
        FROM nguoi_dung nd 
        JOIN hoc_sinh hs ON nd.ma_nguoi_dung = hs.ma_nguoi_dung 
        JOIN hoc_sinh_lop hsl ON hs.ma_nguoi_dung = hsl.ma_hoc_sinh AND hsl.ma_nam = ?
        WHERE nd.ma_nguoi_dung = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $nam_hoc_hien_tai, $ma_hs);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) die("Lỗi: Không tìm thấy học sinh.");

$sql_dc = "SELECT * FROM dia_chi_chi_tiet WHERE ma_nguoi_dung = ? AND ma_loai_dia_chi = 'DC01' LIMIT 1";
$stmt_dc = mysqli_prepare($conn, $sql_dc);
mysqli_stmt_bind_param($stmt_dc, "s", $ma_hs);
mysqli_stmt_execute($stmt_dc);
$dia_chi_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dc));
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="<?= $base_url ?>assets/css/profile.css">

<div class="container-fluid py-4 bg-slate-50">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-navy-dark mb-0"><i class="fa-solid fa-user-graduate me-2"></i>Chỉnh sửa hồ sơ: <?= htmlspecialchars($user['ho'] . ' ' . $user['ten']) ?></h4>
        <a href="students.php" class="btn btn-outline-secondary bg-white fw-bold"><i class="fa-solid fa-arrow-left me-1"></i> Trở về</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?> border-0 shadow-sm mb-4 fw-bold">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="row g-4" id="profileForm">
        <!-- KHỐI TRÁI -->
        <div class="col-xl-8 col-lg-7">
            <!-- 1. THÔNG TIN LÝ LỊCH -->
            <div class="card p-4 shadow-sm border-0 mb-4 main-card">
                <h6 class="fw-bold text-navy-bright mb-3 section-title">1. Thông tin cơ bản</h6>
                <div class="row g-3 mb-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Họ đệm</label>
                        <input type="text" name="ho" class="form-control custom-input fw-bold" value="<?= htmlspecialchars($user['ho']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Tên</label>
                        <input type="text" name="ten" class="form-control custom-input fw-bold" value="<?= htmlspecialchars($user['ten']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Ngày sinh</label>
                        <input type="date" name="ngay_sinh" class="form-control custom-input" value="<?= $user['ngay_sinh'] ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Giới tính</label>
                        <select name="gioi_tinh" class="form-select custom-input">
                            <option value="">-- Chọn --</option>
                            <option value="Nam" <?= $user['gioi_tinh'] == 'Nam' ? 'selected' : '' ?>>Nam</option>
                            <option value="Nữ" <?= $user['gioi_tinh'] == 'Nữ' ? 'selected' : '' ?>>Nữ</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Số định danh (CCCD)</label>
                        <input type="text" name="so_cccd" class="form-control custom-input" value="<?= htmlspecialchars($user['so_cccd'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Dân tộc</label>
                        <select name="ma_dan_toc" class="form-select custom-input">
                            <option value="">-- Chọn --</option>
                            <?php foreach ($dt_list as $dt): ?>
                                <option value="<?= $dt['ma_dan_toc'] ?>" <?= $user['ma_dan_toc'] == $dt['ma_dan_toc'] ? 'selected' : '' ?>><?= $dt['ten_dan_toc'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Quốc tịch</label>
                        <select name="ma_quoc_tich" class="form-select custom-input">
                            <option value="">-- Chọn --</option>
                            <?php foreach ($qt_list as $qt): ?>
                                <option value="<?= $qt['ma_quoc_tich'] ?>" <?= $user['ma_quoc_tich'] == $qt['ma_quoc_tich'] ? 'selected' : '' ?>><?= $qt['ten_quoc_tich'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 2. ĐỊA CHỈ & LIÊN HỆ PHỤ HUYNH -->
            <div class="card p-4 shadow-sm border-0 mb-4 main-card">
                <h6 class="fw-bold text-navy-bright mb-3 section-title">2. Thông tin liên hệ Phụ huynh & Địa chỉ</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Số điện thoại phụ huynh</label>
                        <input type="text" name="sdt" class="form-control custom-input" value="<?= htmlspecialchars($user['sdt'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email phụ huynh (nếu có)</label>
                        <input type="email" name="email" class="form-control custom-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Tỉnh / Thành phố</label>
                        <select name="ma_tinh_thanh" class="form-select custom-input">
                            <option value="">-- Chọn Tỉnh/Thành --</option>
                            <?php foreach ($tinh_list as $t): ?>
                                <option value="<?= $t['ma_tinh_thanh'] ?>" <?= ($dia_chi_info['ma_tinh_thanh'] ?? '') == $t['ma_tinh_thanh'] ? 'selected' : '' ?>><?= $t['ten_tinh_thanh'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Quận / Huyện / Xã / Phường</label>
                        <select name="ma_xa_phuong" class="form-select custom-input">
                            <option value="">-- Chọn Xã/Phường --</option>
                            <?php foreach ($xa_list as $x): ?>
                                <option value="<?= $x['ma_xa_phuong'] ?>" <?= ($dia_chi_info['ma_xa_phuong'] ?? '') == $x['ma_xa_phuong'] ? 'selected' : '' ?>><?= $x['ten_phuong_xa'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Số nhà, Tên đường, Thôn xóm</label>
                        <input type="text" name="so_nha" class="form-control custom-input" placeholder="Nhập địa chỉ chi tiết..." value="<?= htmlspecialchars($dia_chi_info['so_nha_ten_duong'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- 3. THÔNG TIN NHẬP HỌC -->
            <div class="card p-4 shadow-sm border-0 main-card">
                <h6 class="fw-bold text-navy-bright mb-3 section-title">3. Thông tin học tập</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Mã học sinh</label>
                        <input type="text" class="form-control readonly-input fw-bold" value="<?= htmlspecialchars($ma_hs) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Ngày vào lớp</label>
                        <input type="date" name="ngay_vao_lop" class="form-control custom-input" value="<?= $user['ngay_vao_lop'] ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Cơ sở / Điểm trường</label>
                        <select name="ma_diem_truong" class="form-select custom-input">
                            <option value="">-- Chọn cơ sở --</option>
                            <?php foreach ($diem_truong_list as $dt): ?>
                                <option value="<?= $dt['ma_diem_truong'] ?>" <?= $user['ma_diem_truong'] == $dt['ma_diem_truong'] ? 'selected' : '' ?>><?= $dt['ten_diem'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- KHỐI PHẢI -->
        <div class="col-xl-4 col-lg-5">
            <!-- ẢNH ĐẠI DIỆN -->
            <div class="card p-4 shadow-sm border-0 mb-4 text-center main-card">
                <h6 class="fw-bold text-navy-dark mb-3 border-bottom pb-2 text-start">Ảnh hồ sơ học sinh</h6>
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
                    <input type="file" name="AnhDaiDien" class="form-control custom-input form-control-sm mb-4" accept=".jpg, .jpeg, .png, .webp" id="avatarInput">
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-navy-bright fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Lưu hồ sơ</button>
                    <a href="students.php" class="btn btn-outline-secondary fw-bold bg-white">Hủy bỏ</a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
<script src="<?= $base_url ?>assets/js/profile.js"></script>