<?php
session_start();
// Kiểm tra quyền
if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php'; // Chứa kết nối CSDL $conn

$ma_gv = $_SESSION['user_id']; 

// Lấy năm học hiện tại (năm học mà ngày hôm nay nằm trong khoảng bắt đầu - kết thúc)
$sql_nam = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
$result_nam = mysqli_query($conn, $sql_nam);

// Nếu không tìm thấy năm hiện tại (đang nghỉ hè), lùi về lấy năm học gần nhất
if (mysqli_num_rows($result_nam) > 0) {
    $nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam)['ma_nam'];
} else {
    $sql_nam_backup = "SELECT ma_nam FROM nam_hoc WHERE ngay_ket_thuc < CURDATE() ORDER BY ngay_ket_thuc DESC LIMIT 1";
    $result_nam_backup = mysqli_query($conn, $sql_nam_backup);
    $nam_hoc_hien_tai = mysqli_fetch_assoc($result_nam_backup)['ma_nam'] ?? '2026-2027';
}

// 1. Lấy danh sách Môn học (để đưa vào Combobox 1)
$sql_mon = "
    SELECT DISTINCT mh.ma_mon, mh.ten_mon 
    FROM phan_cong_day pcd
    JOIN mon_hoc mh ON pcd.ma_mon = mh.ma_mon
    WHERE pcd.ma_nguoi_dung = ? AND pcd.ma_nam = ?
";
$stmt_mon = mysqli_prepare($conn, $sql_mon);
mysqli_stmt_bind_param($stmt_mon, "ss", $ma_gv, $nam_hoc_hien_tai);
mysqli_stmt_execute($stmt_mon);
$danh_sach_mon = mysqli_fetch_all(mysqli_stmt_get_result($stmt_mon), MYSQLI_ASSOC);

// 2. Lấy danh sách Phân công Lớp (để truyền qua JS xử lý Combobox 2)
$sql_lop = "
    SELECT ma_mon, khoi, ma_nhom_lop, stt 
    FROM phan_cong_day 
    WHERE ma_nguoi_dung = ? AND ma_nam = ?
    ORDER BY khoi, stt
";
$stmt_lop = mysqli_prepare($conn, $sql_lop);
mysqli_stmt_bind_param($stmt_lop, "ss", $ma_gv, $nam_hoc_hien_tai);
mysqli_stmt_execute($stmt_lop);
$danh_sach_lop = mysqli_fetch_all(mysqli_stmt_get_result($stmt_lop), MYSQLI_ASSOC);

?>
<!-- Load Header -->
<?php include '../includes/header.php'; ?>

<!-- Bổ sung CSS riêng cho trang này -->
<link rel="stylesheet" href="../assets/css/class_list.css">

<!-- Thư viện xuất PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="../assets/js/vuTimes-normal.js"></script>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-navy-dark header-title">Danh sách lớp giảng dạy</h4>
        </div>
    </div>

    <!-- Bộ lọc -->
    <div class="card border-0 shadow-sm mb-4 filter-card">
        <div class="card-body">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Năm học</label>
                    <select class="form-select shadow-none" id="namHoc" name="namHoc" disabled>
                        <option value="<?= $nam_hoc_hien_tai ?>"><?= $nam_hoc_hien_tai ?></option>
                    </select>
                </div>
                
                <!-- Combobox Môn Học -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Môn học</label>
                    <select class="form-select shadow-none" id="monHoc" name="monHoc">
                        <option value="">-- Chọn môn --</option>
                        <?php foreach ($danh_sach_mon as $mon): ?>
                            <option value="<?= $mon['ma_mon'] ?>"><?= htmlspecialchars($mon['ten_mon']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Combobox Lớp (Sẽ được JS đổ dữ liệu dựa vào môn) -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Lớp giảng dạy</label>
                    <select class="form-select shadow-none" id="lopHoc" name="lopHoc" disabled>
                        <option value="">-- Chọn lớp --</option>
                    </select>
                </div>

                <div class="col-md-4 text-md-end">
                    <button type="button" class="btn btn-navy-bright text-white shadow-none px-3" id="btnExportPDF" disabled>
                        <i class="fa-solid fa-file-excel me-2"></i>Xuất Excel
                    </button>
                    <button type="button" class="btn btn-outline-secondary shadow-none px-3 ms-2" id="btnPrint" disabled>
                        <i class="fa-solid fa-print"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bảng danh sách học sinh -->
    <div class="card border-0 shadow-sm list-card">
        <div class="card-header bg-transparent border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold" id="tableTitle"><i class="fa-solid fa-list me-2"></i>Danh sách học sinh</h6>
            <span class="badge bg-navy-medium rounded-pill px-3 py-2" id="studentCount">Sĩ số: 0</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="studentTable">
                    <thead class="table-navy-custom">
                        <tr>
                            <th class="text-center" width="5%">STT</th>
                            <th width="35%">Họ và tên học sinh</th>
                            <th class="text-center" width="15%">Giới tính</th>
                            <th class="text-center" width="20%">Ngày sinh</th>
                            <th width="25%">Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody id="studentListBody">
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Vui lòng chọn môn và lớp để xem danh sách.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Load Footer -->
<?php include '../includes/footer.php'; ?>

<!-- Thư viện xuất Excel có hỗ trợ Style -->
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
<script>
    // Nhúng mảng Lớp từ PHP sang Javascript để làm hiệu ứng chọn Môn hiện Lớp
    window.dsLopPhanCong = <?= json_encode($danh_sach_lop) ?>;
</script>
<script src="../assets/js/class_list.js"></script>