<?php
session_start();
if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$msg = '';
$msgType = '';

// Lấy danh mục cho các Dropdown
$dt_list = mysqli_query($conn, "SELECT * FROM dan_toc");
$qt_list = mysqli_query($conn, "SELECT * FROM quoc_tich");
$mh_list = mysqli_query($conn, "SELECT * FROM mon_hoc");
$vt_list = mysqli_query($conn, "SELECT * FROM vi_tri_lam_viec");
$ncv_list = mysqli_query($conn, "SELECT * FROM nhom_chuc_vu");
$ht_list = mysqli_query($conn, "SELECT * FROM hinh_thuc_hop_dong");
$tr_list = mysqli_query($conn, "SELECT * FROM truong");
$tinh_list = mysqli_query($conn, "SELECT * FROM tinh_thanh");
$xa_list = mysqli_query($conn, "SELECT * FROM xa_phuong");

// Xử lý Cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Thông tin người dùng
    $ho = trim($_POST['ho'] ?? '');
    $ten = trim($_POST['ten'] ?? '');
    $ngay_sinh = !empty($_POST['ngay_sinh']) ? $_POST['ngay_sinh'] : null;
    $gioi_tinh = trim($_POST['gioi_tinh'] ?? '');
    $so_cccd = trim($_POST['so_cccd'] ?? '');
    $sdt = trim($_POST['sdt'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ma_quoc_tich = !empty($_POST['ma_quoc_tich']) ? $_POST['ma_quoc_tich'] : null;
    $ma_dan_toc = !empty($_POST['ma_dan_toc']) ? $_POST['ma_dan_toc'] : null;

    // Địa chỉ chi tiết
    $ma_tinh_thanh = !empty($_POST['ma_tinh_thanh']) ? $_POST['ma_tinh_thanh'] : null;
    $ma_xa_phuong = !empty($_POST['ma_xa_phuong']) ? $_POST['ma_xa_phuong'] : null;
    $so_nha = trim($_POST['so_nha'] ?? '');

    // Thông tin cán bộ
    $ngay_vao_truong = !empty($_POST['ngay_vao_truong']) ? $_POST['ngay_vao_truong'] : null;
    $mon_day = !empty($_POST['mon_day']) ? $_POST['mon_day'] : null;
    $ma_vi_tri_lam = !empty($_POST['ma_vi_tri_lam']) ? $_POST['ma_vi_tri_lam'] : null;
    $ma_nhom_chuc_vu = !empty($_POST['ma_nhom_chuc_vu']) ? $_POST['ma_nhom_chuc_vu'] : null;
    $ma_hinh_thuc = !empty($_POST['ma_hinh_thuc']) ? $_POST['ma_hinh_thuc'] : null;
    $ma_truong = !empty($_POST['ma_truong']) ? $_POST['ma_truong'] : null;
    
    $matKhauMoi = $_POST['MatKhauMoi'] ?? '';
    $nhapLaiMatKhau = $_POST['NhapLaiMatKhau'] ?? '';
    $errors = [];

    if (!empty($matKhauMoi) && $matKhauMoi !== $nhapLaiMatKhau) {
        $errors[] = "Mật khẩu nhập lại không khớp.";
    }

    // Xử lý Upload Ảnh đại diện
    $anh_dai_dien_path = null;
    if (isset($_FILES['AnhDaiDien']) && $_FILES['AnhDaiDien']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['AnhDaiDien']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $uploadDir = '../uploads/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $newFileName = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['AnhDaiDien']['tmp_name'], $uploadDir . $newFileName)) {
                $anh_dai_dien_path = 'uploads/avatars/' . $newFileName;
            }
        } else $errors[] = "Ảnh đại diện chỉ nhận JPG, PNG, WEBP.";
    }

    // Xử lý Upload Chữ ký
    $chu_ky_path = null;
    if (isset($_FILES['ChuKySo']) && $_FILES['ChuKySo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['ChuKySo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $uploadDir = '../uploads/signatures/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $newFileName = 'sig_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['ChuKySo']['tmp_name'], $uploadDir . $newFileName)) {
                $chu_ky_path = 'uploads/signatures/' . $newFileName;
            }
        } else $errors[] = "Chữ ký chỉ nhận JPG, PNG, WEBP.";
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            // Update Nguoi Dung
            if ($anh_dai_dien_path) {
                $sql_nd = "UPDATE nguoi_dung SET ho=?, ten=?, ngay_sinh=?, gioi_tinh=?, so_cccd=?, sdt=?, email=?, ma_quoc_tich=?, ma_dan_toc=?, anh_dai_dien=? WHERE ma_nguoi_dung=?";
                $stmt_nd = mysqli_prepare($conn, $sql_nd);
                mysqli_stmt_bind_param($stmt_nd, "sssssssssss", $ho, $ten, $ngay_sinh, $gioi_tinh, $so_cccd, $sdt, $email, $ma_quoc_tich, $ma_dan_toc, $anh_dai_dien_path, $user_id);
            } else {
                $sql_nd = "UPDATE nguoi_dung SET ho=?, ten=?, ngay_sinh=?, gioi_tinh=?, so_cccd=?, sdt=?, email=?, ma_quoc_tich=?, ma_dan_toc=? WHERE ma_nguoi_dung=?";
                $stmt_nd = mysqli_prepare($conn, $sql_nd);
                mysqli_stmt_bind_param($stmt_nd, "ssssssssss", $ho, $ten, $ngay_sinh, $gioi_tinh, $so_cccd, $sdt, $email, $ma_quoc_tich, $ma_dan_toc, $user_id);
            }
            mysqli_stmt_execute($stmt_nd);

            // Xử lý Địa chỉ chi tiết (DC01 - Thường trú)
            // Vì tỉnh và xã là khoá chính, cách an toàn nhất là xóa cái cũ và insert cái mới
            $sql_del_dc = "DELETE FROM dia_chi_chi_tiet WHERE ma_nguoi_dung = ? AND ma_loai_dia_chi = 'DC01'";
            $stmt_del_dc = mysqli_prepare($conn, $sql_del_dc);
            mysqli_stmt_bind_param($stmt_del_dc, "s", $user_id);
            mysqli_stmt_execute($stmt_del_dc);

            if ($ma_tinh_thanh && $ma_xa_phuong) {
                $sql_ins_dc = "INSERT INTO dia_chi_chi_tiet (ma_nguoi_dung, ma_loai_dia_chi, ma_tinh_thanh, ma_xa_phuong, so_nha_ten_duong) VALUES (?, 'DC01', ?, ?, ?)";
                $stmt_ins_dc = mysqli_prepare($conn, $sql_ins_dc);
                mysqli_stmt_bind_param($stmt_ins_dc, "ssss", $user_id, $ma_tinh_thanh, $ma_xa_phuong, $so_nha);
                mysqli_stmt_execute($stmt_ins_dc);
            }

            // Update Can Bo
            if ($chu_ky_path) {
                $sql_cb = "UPDATE can_bo SET ngay_vao_truong=?, mon_day=?, ma_vi_tri_lam=?, ma_nhom_chuc_vu=?, ma_hinh_thuc=?, ma_truong=?, chu_ky=? WHERE ma_nguoi_dung=?";
                $stmt_cb = mysqli_prepare($conn, $sql_cb);
                mysqli_stmt_bind_param($stmt_cb, "ssssssss", $ngay_vao_truong, $mon_day, $ma_vi_tri_lam, $ma_nhom_chuc_vu, $ma_hinh_thuc, $ma_truong, $chu_ky_path, $user_id);
            } else {
                $sql_cb = "UPDATE can_bo SET ngay_vao_truong=?, mon_day=?, ma_vi_tri_lam=?, ma_nhom_chuc_vu=?, ma_hinh_thuc=?, ma_truong=? WHERE ma_nguoi_dung=?";
                $stmt_cb = mysqli_prepare($conn, $sql_cb);
                mysqli_stmt_bind_param($stmt_cb, "sssssss", $ngay_vao_truong, $mon_day, $ma_vi_tri_lam, $ma_nhom_chuc_vu, $ma_hinh_thuc, $ma_truong, $user_id);
            }
            mysqli_stmt_execute($stmt_cb);

            // Update Password
            if (!empty($matKhauMoi)) {
                $hash = password_hash($matKhauMoi, PASSWORD_DEFAULT);
                $sql_pw = "UPDATE tai_khoan SET mat_khau=? WHERE ma_nguoi_dung=?";
                $stmt_pw = mysqli_prepare($conn, $sql_pw);
                mysqli_stmt_bind_param($stmt_pw, "ss", $hash, $user_id);
                mysqli_stmt_execute($stmt_pw);
            }

            // GHI ĐÈ SESSION ẢNH ĐẠI DIỆN NẾU CÓ UPLOAD ẢNH MỚI
            if ($anh_dai_dien_path) {
                $_SESSION['user_avatar'] = $anh_dai_dien_path;
            }

            mysqli_commit($conn);
            $msg = "Cập nhật hồ sơ cá nhân thành công!";
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

// Truy vấn lấy dữ liệu hiện tại (Nguoi dung + Can bo)
$sql = "SELECT nd.*, cb.* 
        FROM nguoi_dung nd 
        JOIN can_bo cb ON nd.ma_nguoi_dung = cb.ma_nguoi_dung 
        WHERE nd.ma_nguoi_dung = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) die("Lỗi: Không tìm thấy thông tin giáo viên.");

// Lấy thông tin địa chỉ thường trú
$sql_dc = "SELECT * FROM dia_chi_chi_tiet WHERE ma_nguoi_dung = ? AND ma_loai_dia_chi = 'DC01' LIMIT 1";
$stmt_dc = mysqli_prepare($conn, $sql_dc);
mysqli_stmt_bind_param($stmt_dc, "s", $user_id);
mysqli_stmt_execute($stmt_dc);
$dia_chi_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dc));
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="<?= $base_url ?>assets/css/profile.css">

<div class="container-fluid py-4 bg-slate-50">
    <div class="mb-4">
        <h4 class="fw-bold text-navy-dark mb-1"><i class="fa-solid fa-address-card me-2"></i>Hồ sơ cá nhân</h4>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?> border-0 shadow-sm mb-4 fw-bold">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="row g-4" id="profileForm">
        <!-- KHỐI TRÁI: THÔNG TIN CHI TIẾT -->
        <div class="col-xl-8 col-lg-7">
            
            <div class="card p-4 shadow-sm border-0 mb-4 main-card">
                <h6 class="fw-bold text-navy-bright mb-3 section-title">1. Thông tin lý lịch cá nhân</h6>
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
                        <label class="form-label small fw-semibold">Số CCCD</label>
                        <input type="text" name="so_cccd" class="form-control custom-input" value="<?= htmlspecialchars($user['so_cccd'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Dân tộc</label>
                        <select name="ma_dan_toc" class="form-select custom-input">
                            <option value="">-- Chọn Dân tộc --</option>
                            <?php foreach ($dt_list as $dt): ?>
                                <option value="<?= $dt['ma_dan_toc'] ?>" <?= $user['ma_dan_toc'] == $dt['ma_dan_toc'] ? 'selected' : '' ?>><?= $dt['ten_dan_toc'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Quốc tịch</label>
                        <select name="ma_quoc_tich" class="form-select custom-input">
                            <option value="">-- Chọn Quốc tịch --</option>
                            <?php foreach ($qt_list as $qt): ?>
                                <option value="<?= $qt['ma_quoc_tich'] ?>" <?= $user['ma_quoc_tich'] == $qt['ma_quoc_tich'] ? 'selected' : '' ?>><?= $qt['ten_quoc_tich'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- CARD ĐỊA CHỈ & LIÊN HỆ -->
            <div class="card p-4 shadow-sm border-0 mb-4 main-card">
                <h6 class="fw-bold text-navy-bright mb-3 section-title">2. Thông tin liên lạc & Địa chỉ</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Số điện thoại</label>
                        <input type="text" name="sdt" class="form-control custom-input" value="<?= htmlspecialchars($user['sdt'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email</label>
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

            <div class="card p-4 shadow-sm border-0 mb-4 main-card">
                <h6 class="fw-bold text-navy-bright mb-3 section-title">3. Thông tin công tác</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Mã cán bộ / giáo viên</label>
                        <!-- Khóa chính không được phép chỉnh sửa -->
                        <input type="text" class="form-control readonly-input fw-bold" value="<?= htmlspecialchars($user_id) ?>" readonly title="Mã hệ thống không thể chỉnh sửa">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Trường công tác</label>
                        <select name="ma_truong" class="form-select custom-input">
                            <option value="">-- Chọn trường --</option>
                            <?php foreach ($tr_list as $tr): ?>
                                <option value="<?= $tr['ma_truong'] ?>" <?= $user['ma_truong'] == $tr['ma_truong'] ? 'selected' : '' ?>><?= $tr['ten_truong'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Ngày vào trường</label>
                        <input type="date" name="ngay_vao_truong" class="form-control custom-input" value="<?= $user['ngay_vao_truong'] ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Môn dạy chính</label>
                        <select name="mon_day" class="form-select custom-input">
                            <option value="">-- Trống --</option>
                            <?php foreach ($mh_list as $mh): ?>
                                <option value="<?= $mh['ma_mon'] ?>" <?= $user['mon_day'] == $mh['ma_mon'] ? 'selected' : '' ?>><?= $mh['ten_mon'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Vị trí làm việc</label>
                        <select name="ma_vi_tri_lam" class="form-select custom-input">
                            <option value="">-- Trống --</option>
                            <?php foreach ($vt_list as $vt): ?>
                                <option value="<?= $vt['ma_vi_tri_lam'] ?>" <?= $user['ma_vi_tri_lam'] == $vt['ma_vi_tri_lam'] ? 'selected' : '' ?>><?= $vt['vi_tri_lam_viec'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Nhóm chức vụ</label>
                        <select name="ma_nhom_chuc_vu" class="form-select custom-input">
                            <option value="">-- Trống --</option>
                            <?php foreach ($ncv_list as $ncv): ?>
                                <option value="<?= $ncv['ma_nhom_chuc_vu'] ?>" <?= $user['ma_nhom_chuc_vu'] == $ncv['ma_nhom_chuc_vu'] ? 'selected' : '' ?>><?= $ncv['nhom_chuc_vu'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Hình thức hợp đồng</label>
                        <select name="ma_hinh_thuc" class="form-select custom-input">
                            <option value="">-- Trống --</option>
                            <?php foreach ($ht_list as $ht): ?>
                                <option value="<?= $ht['ma_hinh_thuc'] ?>" <?= $user['ma_hinh_thuc'] == $ht['ma_hinh_thuc'] ? 'selected' : '' ?>><?= $ht['hinh_thuc_hop_dong'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- KHỐI PHẢI: ẢNH ĐẠI DIỆN, CHỮ KÝ, MẬT KHẨU -->
        <div class="col-xl-4 col-lg-5">
            
            <!-- ẢNH ĐẠI DIỆN -->
            <div class="card p-4 shadow-sm border-0 mb-4 text-center main-card">
                <h6 class="fw-bold text-navy-dark mb-3 border-bottom pb-2 text-start">Ảnh đại diện</h6>
                <div class="mb-3 d-flex justify-content-center align-items-center">
                    <div class="avatar-preview-box">
                        <?php if (!empty($user['anh_dai_dien'])): ?>
                            <img src="<?= $base_url . htmlspecialchars($user['anh_dai_dien']) ?>" alt="Avatar" class="avatar-img" id="avatarPreviewImg">
                        <?php else: ?>
                            <div class="avatar-placeholder" id="avatarPreviewImg"><i class="fa-solid fa-user-tie"></i></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-start">
                    <input type="file" name="AnhDaiDien" class="form-control custom-input form-control-sm" accept=".jpg, .jpeg, .png, .webp" id="avatarInput">
                </div>
            </div>

            <!-- CHỮ KÝ ĐIỆN TỬ -->
            <div class="card p-4 shadow-sm border-0 mb-4 text-center main-card">
                <h6 class="fw-bold text-navy-dark mb-3 border-bottom pb-2 text-start">Chữ ký điện tử</h6>
                <div class="mb-3 d-flex justify-content-center align-items-center border rounded p-2 signature-preview-box">
                    <?php if (!empty($user['chu_ky'])): ?>
                        <img src="<?= $base_url . htmlspecialchars($user['chu_ky']) ?>" alt="Chữ ký số" class="signature-img" id="sigPreviewImg">
                    <?php else: ?>
                        <span class="text-muted small" id="sigPlaceholder"><i class="fa-solid fa-pen-nib me-1"></i> Chưa cập nhật chữ ký số</span>
                    <?php endif; ?>
                </div>
                <div class="text-start">
                    <input type="file" name="ChuKySo" class="form-control custom-input form-control-sm" accept=".jpg, .jpeg, .png, .webp" id="sigInput">
                    <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Nên dùng ảnh nét ký trên nền trắng hoặc file PNG không nền.</small>
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
                    <button type="submit" class="btn btn-navy-bright fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Lưu thay đổi</button>
                    <a href="index.php" class="btn btn-outline-secondary fw-bold bg-white">Hủy bỏ</a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
<script src="<?= $base_url ?>assets/js/profile.js"></script>