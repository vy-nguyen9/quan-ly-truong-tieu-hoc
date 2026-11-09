<?php
// Lấy tên file và tên thư mục hiện tại
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Tự động tính toán đường dẫn tương đối (Thoát ra 1 cấp nếu đang ở trong thư mục con)
$path_prefix = ($current_dir === 'grade_subject') ? '../' : '';
$grade_path = ($current_dir === 'grade_subject') ? '' : 'grade_subject/';
$base_url = (isset($depth) && $depth > 0) ? str_repeat('../', $depth) : '../';

// Lấy thông tin cơ bản: Năm học hiện tại & Trường công tác
$nam_hoc_sb = date('Y') . '-' . (date('Y') + 1);
$ten_truong_sb = 'Trường Tiểu học';
$is_chu_nhiem = false;

if (isset($_SESSION['user_id']) && isset($conn)) {
    // 1. Lấy năm học hiện tại
    $sql_nam_sidebar = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
    $result_nam_sb = mysqli_query($conn, $sql_nam_sidebar);
    if ($row_nam = mysqli_fetch_assoc($result_nam_sb)) {
        $nam_hoc_sb = $row_nam['ma_nam'];
    }

    $user_role = strtoupper($_SESSION['role']);
    
    // 2. Kiểm tra GV chủ nhiệm & Lấy tên trường
    if (in_array($user_role, ['GV', 'BGH', 'NV'])) {
        // Kiểm tra chủ nhiệm
        $sql_check_cn = "SELECT 1 FROM lop_hoc WHERE chu_nhiem = ? AND ma_nam = ? LIMIT 1";
        $stmt_cn = mysqli_prepare($conn, $sql_check_cn);
        mysqli_stmt_bind_param($stmt_cn, "ss", $_SESSION['user_id'], $nam_hoc_sb);
        mysqli_stmt_execute($stmt_cn);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt_cn)) > 0) {
            $is_chu_nhiem = true;
        }

        // Lấy tên trường của Giáo viên/Cán bộ
        $sql_truong = "SELECT t.ten_truong FROM can_bo cb JOIN truong t ON cb.ma_truong = t.ma_truong WHERE cb.ma_nguoi_dung = ?";
        $stmt_truong = mysqli_prepare($conn, $sql_truong);
        mysqli_stmt_bind_param($stmt_truong, "s", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt_truong);
        $res_truong = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_truong));
        if ($res_truong) $ten_truong_sb = $res_truong['ten_truong'];

    } elseif ($user_role === 'HS') {
        // Lấy tên trường của Học sinh thông qua điểm trường
        $sql_truong = "SELECT t.ten_truong FROM hoc_sinh hs JOIN diem_truong dt ON hs.ma_diem_truong = dt.ma_diem_truong JOIN truong t ON dt.ma_truong = t.ma_truong WHERE hs.ma_nguoi_dung = ?";
        $stmt_truong = mysqli_prepare($conn, $sql_truong);
        mysqli_stmt_bind_param($stmt_truong, "s", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt_truong);
        $res_truong = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_truong));
        if ($res_truong) $ten_truong_sb = $res_truong['ten_truong'];
    }
}
?>
<aside class="sidebar" id="sidebar">
    <!-- Tên trường / Logo -->
    <div class="d-flex align-items-center gap-3 px-4" style="height: 70px; border-bottom: 1px solid var(--navy-deep);">
        <!-- Thay Icon nón bằng Logo SVG -->
        <div class="d-flex align-items-center justify-content-center" >
            <img src="<?= $base_url ?>assets/images/logo-aves.png" alt="AVES Logo" style="width: 110%; height: 100%; object-fit: contain;">
        </div>
        <!-- <h5 class="text-white mb-0 fw-bold tracking-wide">AVES</h5> -->
    </div>

    <!-- Menu -->
    <div class="flex-grow-1 overflow-y-auto px-3 py-4 no-scrollbar">
        <nav class="nav flex-column">

            <?php if (isset($_SESSION['role']) && in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])): ?>
                <a class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>" href="<?= $path_prefix ?>index.php">
                    Tổng quan
                </a>
                <!-- Menu Giáo Viên -->
                <a class="nav-link <?= ($current_page == 'class_list.php') ? 'active' : '' ?>" href="<?= $path_prefix ?>class_list.php">
                   Danh sách lớp
                </a>

                <?php if ($is_chu_nhiem): ?>
                    <a class="nav-link <?= ($current_page == 'students.php') ? 'active' : '' ?>" href="<?= $path_prefix ?>students.php">
                        Hồ sơ học sinh
                    </a>
                <?php endif; ?>

                <a class="nav-link <?= ($current_page == 'attendance.php') ? 'active' : '' ?>" href="<?= $path_prefix ?>attendance.php">
                    Điểm danh
                </a>
                <a class="nav-link <?= ($current_page == 'schedule.php') ? 'active' : '' ?>" href="<?= $path_prefix ?>schedule.php">
                   Thời khóa biểu
                </a>

                <!-- Sổ đánh giá HS -->
                <?php
                $is_grade_active = in_array($current_page, ['grade_subject.php', 'grade_quality.php', 'grade_ability.php', 'grade_reward.php']);
                ?>
                <a class="nav-link <?= $is_grade_active ? 'active' : '' ?>" href="<?= $grade_path ?>grade_subject.php">
                   Sổ đánh giá HS
                </a>

                <?php if ($is_chu_nhiem): ?>
                    <a class="nav-link <?= ($current_page == 'transcript.php') ? 'active' : '' ?>" href="<?= $path_prefix ?>transcript.php">
                        Học bạ
                    </a>
                <?php endif; ?>
                <a class="nav-link <?= ($current_page == 'leave_request.php') ? 'active' : '' ?>" href="<?= $path_prefix ?>leave_request.php">
                   Xin nghỉ phép
                </a>

            <?php elseif (isset($_SESSION['role']) && strtoupper($_SESSION['role']) === 'HS'): ?>
                <!-- Menu Học Sinh -->
                <a class="nav-link <?= ($current_page == 'schedule_student.php') ? 'active' : '' ?>" href="<?= $path_prefix ?>schedule_student.php">
                   Thời khóa biểu
                </a>
                <a class="nav-link <?= ($current_page == 'results.php') ? 'active' : '' ?>" href="<?= $path_prefix ?>results.php">
                    Kết quả học tập
                </a>
            <?php endif; ?>

            <a class="nav-link <?= ($current_page == 'notifications.php') ? 'active' : '' ?> mt-1" href="<?= $path_prefix ?>notifications.php">
               Thông báo
            </a>
        </nav>
    </div>

    <!-- Footer Sidebar -->
    <div class="p-3" style="border-top: 1px solid var(--navy-deep);">
        <div class="d-flex align-items-center gap-2 p-2 rounded" style="background: rgba(20, 66, 114, 0.8);">
            <!-- Nội dung Footer đã bỏ Icon, hiển thị Tên trường và Năm học từ CSDL -->
            <div class="text-white overflow-hidden w-100 text-center">
                <div class="fw-bold text-truncate" style="font-size: 0.85rem;" title="<?= htmlspecialchars($ten_truong_sb) ?>">
                    <?= htmlspecialchars($ten_truong_sb) ?>
                </div>
                <div style="font-size: 0.75rem; color: #cbd5e1; margin-top: 2px;">
                    Năm học <?= htmlspecialchars($nam_hoc_sb) ?>
                </div>
            </div>
        </div>
    </div>
</aside>