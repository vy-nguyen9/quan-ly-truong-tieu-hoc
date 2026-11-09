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

// Lấy thông tin lớp chủ nhiệm
$sql_lop = "SELECT khoi, ma_nhom_lop, stt, ma_truong FROM lop_hoc WHERE chu_nhiem = ? AND ma_nam = ? LIMIT 1";
$stmt_lop = mysqli_prepare($conn, $sql_lop);
mysqli_stmt_bind_param($stmt_lop, "ss", $ma_gv, $nam_hoc_hien_tai);
mysqli_stmt_execute($stmt_lop);
$lop_cn = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_lop));

$students = [];
if ($lop_cn) {
    $searchKeyword = trim($_GET['search'] ?? '');
    
    $sql_hs = "SELECT nd.ma_nguoi_dung as ma_hs, nd.ho, nd.ten, nd.ngay_sinh, nd.gioi_tinh, hsl.ngay_vao_lop 
               FROM hoc_sinh_lop hsl 
               JOIN nguoi_dung nd ON hsl.ma_hoc_sinh = nd.ma_nguoi_dung 
               WHERE hsl.ma_nam = ? AND hsl.khoi = ? AND hsl.ma_nhom_lop = ? AND hsl.stt = ? AND hsl.ma_truong = ?";
    
    $params = [$nam_hoc_hien_tai, $lop_cn['khoi'], $lop_cn['ma_nhom_lop'], $lop_cn['stt'], $lop_cn['ma_truong']];
    $types = "sssss";

    if ($searchKeyword !== '') {
        $sql_hs .= " AND (nd.ma_nguoi_dung LIKE ? OR nd.ho LIKE ? OR nd.ten LIKE ?)";
        $kw = "%" . $searchKeyword . "%";
        array_push($params, $kw, $kw, $kw);
        $types .= "sss";
    }
    
    $sql_hs .= " ORDER BY nd.ten ASC, nd.ho ASC";
    $stmt = mysqli_prepare($conn, $sql_hs);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $students = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="<?= $base_url ?>assets/css/grade.css">

<div class="container-fluid py-4 bg-slate-50">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0 text-navy-dark"><i class="fa-solid fa-users me-2"></i>Hồ sơ học sinh</h4>
    </div>

    <?php if (!$lop_cn): ?>
        <div class="alert alert-warning border-0 shadow-sm fw-bold">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> Bạn không được phân công chủ nhiệm lớp nào trong năm học này.
        </div>
    <?php else: ?>
        <!-- BỘ LỌC -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 main-card">
            <div class="card-body p-3">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Lớp chủ nhiệm</label>
                        <input type="text" class="form-control bg-light fw-bold text-navy-dark" value="Lớp <?= $lop_cn['khoi'].'/'.$lop_cn['stt'] ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Tìm kiếm học sinh</label>
                        <input type="text" name="search" class="form-control custom-input" placeholder="Nhập mã hoặc tên học sinh..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-navy-bright w-100 fw-bold">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Tìm kiếm
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- BẢNG DANH SÁCH HỌC SINH -->
        <div class="card border-0 shadow-sm rounded-4 main-card overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-center custom-grade-table">
                        <thead class="table-navy-custom align-middle">
                            <tr>
                                <th width="5%">STT</th>
                                <th width="15%">Mã học sinh</th>
                                <th width="25%">Họ và tên</th>
                                <th width="15%">Ngày sinh</th>
                                <th width="10%">Giới tính</th>
                                <th width="15%">Ngày vào lớp</th>
                                <th width="15%">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <?php if (empty($students)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">Không tìm thấy học sinh nào.</td></tr>
                            <?php else: ?>
                                <?php foreach ($students as $idx => $hs): ?>
                                    <tr>
                                        <td class="fw-bold text-muted"><?= $idx + 1 ?></td>
                                        <td><span class="badge bg-light text-dark border border-secondary-subtle"><?= $hs['ma_hs'] ?></span></td>
                                        <td class="fw-semibold text-start"><?= htmlspecialchars($hs['ho'] . ' ' . $hs['ten']) ?></td>
                                        <td class="small"><?= $hs['ngay_sinh'] ? date('d/m/Y', strtotime($hs['ngay_sinh'])) : '---' ?></td>
                                        <td><?= htmlspecialchars($hs['gioi_tinh'] ?? '---') ?></td>
                                        <td class="small text-muted"><?= $hs['ngay_vao_lop'] ? date('d/m/Y', strtotime($hs['ngay_vao_lop'])) : '---' ?></td>
                                        <td>
                                            <a href="student_edit.php?id=<?= $hs['ma_hs'] ?>" class="btn btn-sm btn-navy-bright fw-bold px-3">
                                                <i class="fa-solid fa-pen-to-square me-1"></i> Sửa hồ sơ
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>