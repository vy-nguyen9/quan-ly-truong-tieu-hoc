<?php
// Tự động tính toán số lượng thư mục cần lùi (../) để quay về thư mục gốc của dự án
$script_path = $_SERVER['PHP_SELF'];
$dirs = explode('/', trim($script_path, '/'));
// Tìm vị trí của thư mục dự án (ví dụ 'ql_truong_tieuhoc'). Nếu bạn đổi tên thư mục gốc, hãy đổi tên ở đây.
$base_index = array_search('ql_truong_tieuhoc', $dirs); 
$depth = count($dirs) - $base_index - 2; 

$base_url = '';
for ($i = 0; $i < $depth; $i++) {
    $base_url .= '../';
}
// Nếu không tìm thấy hoặc depth <= 0 thì mặc định lùi 1 cấp (cho các file ở /teacher)
if ($depth <= 0) $base_url = '../';
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống Quản lý Trường Tiểu học - EduPrimary</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS (Sử dụng đường dẫn tự động tính) -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/style.css">
    <style>
        /* Tùy chỉnh Dropdown Thông báo */
        .notification-dropdown {
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
            padding: 0;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: background 0.2s;
        }

        .notification-item:hover {
            background-color: rgba(44, 116, 179, 0.05);
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-unread {
            background-color: rgba(44, 116, 179, 0.05);
            border-left: 3px solid var(--navy-bright);
        }

        /* Custom scrollbar cho dropdown */
        .notification-dropdown::-webkit-scrollbar {
            width: 6px;
        }

        .notification-dropdown::-webkit-scrollbar-track {
            background: transparent;
        }

        .notification-dropdown::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .notification-dropdown::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body>

    <?php
    // --- LẤY DỮ LIỆU THÔNG BÁO TỪ CSDL CHO DROPDOWN (Tối đa 5 thông báo mới nhất) ---
    if (isset($_SESSION['user_id']) && isset($conn)) {
        $header_user_id = $_SESSION['user_id'];
        
        $header_truong = '';
        $role_check = strtoupper($_SESSION['role']);
        
        if (in_array($role_check, ['GV', 'BGH', 'NV'])) {
            $sql_htr = "SELECT ma_truong FROM can_bo WHERE ma_nguoi_dung = ?";
            $stmt_htr = mysqli_prepare($conn, $sql_htr);
            mysqli_stmt_bind_param($stmt_htr, "s", $header_user_id);
            mysqli_stmt_execute($stmt_htr);
            $res_htr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_htr));
            if ($res_htr) $header_truong = $res_htr['ma_truong'];
        } elseif ($role_check === 'HS') {
            $sql_htr = "SELECT dt.ma_truong FROM hoc_sinh hs JOIN diem_truong dt ON hs.ma_diem_truong = dt.ma_diem_truong WHERE hs.ma_nguoi_dung = ?";
            $stmt_htr = mysqli_prepare($conn, $sql_htr);
            mysqli_stmt_bind_param($stmt_htr, "s", $header_user_id);
            mysqli_stmt_execute($stmt_htr);
            $res_htr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_htr));
            if ($res_htr) $header_truong = $res_htr['ma_truong'];
        }

        $sql_header_noti = "
            SELECT tb.ma_thong_bao, tb.tieu_de, tb.noi_dung, tb.ngay_gui,
                   COALESCE(ntb.da_doc, 0) AS da_doc
            FROM thong_bao tb
            LEFT JOIN nhan_thong_bao ntb ON tb.ma_thong_bao = ntb.ma_thong_bao AND ntb.ma_nguoi_nhan = ?
            WHERE (ntb.ma_nguoi_nhan = ?) 
               OR (tb.ma_truong = ? AND tb.ma_doi_tuong IN ('all', ?))
            ORDER BY tb.ngay_gui DESC LIMIT 5
        ";
        
        $doi_tuong_param = strtolower($role_check);
        $stmt_header_noti = mysqli_prepare($conn, $sql_header_noti);
        mysqli_stmt_bind_param($stmt_header_noti, "ssss", $header_user_id, $header_user_id, $header_truong, $doi_tuong_param);
        mysqli_stmt_execute($stmt_header_noti);
        $header_notifications = mysqli_fetch_all(mysqli_stmt_get_result($stmt_header_noti), MYSQLI_ASSOC);

        $sql_count_unread = "
            SELECT COUNT(*) AS total_unread
            FROM thong_bao tb
            LEFT JOIN nhan_thong_bao ntb ON tb.ma_thong_bao = ntb.ma_thong_bao AND ntb.ma_nguoi_nhan = ?
            WHERE ((ntb.ma_nguoi_nhan = ?) OR (tb.ma_truong = ? AND tb.ma_doi_tuong IN ('all', ?)))
              AND (ntb.da_doc IS NULL OR ntb.da_doc = 0)
        ";
        $stmt_count = mysqli_prepare($conn, $sql_count_unread);
        mysqli_stmt_bind_param($stmt_count, "ssss", $header_user_id, $header_user_id, $header_truong, $doi_tuong_param);
        mysqli_stmt_execute($stmt_count);
        $unread_total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total_unread'];

        if (!function_exists('time_elapsed_string_header')) {
            function time_elapsed_string_header($datetime) {
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
                    return "Hôm qua";
                } else {
                    return $ago->format('d/m/Y');
                }
            }
        }
    } else {
        $header_notifications = [];
        $unread_total = 0;
    }
    ?>

    <div class="wrapper">
        <!-- Gọi Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <div class="main-content">
            <!-- Header -->
            <header class="navbar navbar-expand-lg border-bottom px-4 shadow-sm bg-body-tertiary" style="height: 70px;">
                <div class="d-flex w-100 align-items-center justify-content-between">

                    <!-- Nút menu mobile & Tìm kiếm -->
                    <div class="d-flex align-items-center gap-3">
                        <!-- <button class="btn btn-light d-lg-none border-0 bg-transparent text-secondary" id="sidebarToggle">
                            <i class="fa-solid fa-bars fs-5"></i>
                        </button>
                        <form class="d-none d-sm-block position-relative" style="width: 320px;">
                            <i class="fa-solid fa-magnifying-glass position-absolute text-muted" style="top: 50%; left: 15px; transform: translateY(-50%);"></i>
                            <input type="text" class="form-control rounded-pill ps-5 border-0 shadow-none search-box-header" style="background-color: var(--bs-body-bg);" placeholder="Tìm kiếm học sinh, giáo viên, lớp...">
                        </form> -->
                    </div>

                    <!-- Công cụ bên phải -->
                    <div class="d-flex align-items-center gap-3">
                        <!-- Dark Mode Toggle -->
                        <button class="btn border-0 p-2" id="darkModeToggle" title="Đổi giao diện">
                            <i class="fa-solid fa-moon text-secondary fs-5" id="themeIcon"></i>
                        </button>

                        <!-- Thông báo (Dropdown) -->
                        <div class="dropdown position-relative d-flex align-items-center">
                            <button class="btn border-0 p-2 position-relative" title="Thông báo" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                                <i class="fa-regular fa-bell text-secondary fs-5"></i>
                                <?php if ($unread_total > 0): ?>
                                    <span class="position-absolute bg-danger border border-light rounded-circle" style="width: 10px; height: 10px; top: 6px; right: 8px;"></span>
                                <?php endif; ?>
                            </button>

                            <div class="dropdown-menu dropdown-menu-end notification-dropdown mt-2 p-0 border-0">
                                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center rounded-top">
                                    <h6 class="mb-0 fw-bold" style="color: var(--navy-dark);">Thông báo mới</h6>
                                    <?php if ($unread_total > 0): ?>
                                        <span class="badge bg-danger rounded-pill"><?= $unread_total ?> chưa đọc</span>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <?php if(empty($header_notifications)): ?>
                                        <div class="p-4 text-center text-muted small">Chưa có thông báo nào.</div>
                                    <?php else: ?>
                                        <?php foreach($header_notifications as $hn): 
                                            $is_unread = ($hn['da_doc'] == 0);
                                            $icon_class = "fa-bullhorn";
                                            $icon_color = "bg-primary";
                                            if (stripos($hn['tieu_de'], 'duyệt') !== false) {
                                                $icon_class = "fa-clipboard-check";
                                                $icon_color = "bg-success";
                                            } elseif (stripos($hn['tieu_de'], 'từ chối') !== false) {
                                                $icon_class = "fa-ban";
                                                $icon_color = "bg-danger";
                                            }
                                        ?>
                                            <a href="<?= $base_url ?>teacher/notifications.php" class="text-decoration-none text-dark d-block notification-item <?= $is_unread ? 'notification-unread' : '' ?>">
                                                <div class="d-flex gap-3">
                                                    <div class="flex-shrink-0 mt-1">
                                                        <div class="rounded-circle <?= $is_unread ? $icon_color.' bg-opacity-10 text-'.str_replace('bg-','', $icon_color) : 'bg-secondary bg-opacity-10 text-secondary' ?> d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                            <i class="fa-solid <?= $icon_class ?> small"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 overflow-hidden <?= $is_unread ? '' : 'opacity-75' ?>">
                                                        <h6 class="mb-1 text-truncate fw-<?= $is_unread ? 'bold' : 'semibold' ?>" style="font-size: 14px; <?= $is_unread ? 'color: var(--navy-dark);' : '' ?>"><?= htmlspecialchars($hn['tieu_de']) ?></h6>
                                                        <p class="mb-1 text-muted text-truncate" style="font-size: 12px;"><?= htmlspecialchars($hn['noi_dung']) ?></p>
                                                        <small class="<?= $is_unread ? 'text-navy-bright fw-semibold' : 'text-muted' ?>" style="font-size: 11px;"><?= time_elapsed_string_header($hn['ngay_gui']) ?></small>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="p-2 text-center bg-light border-top rounded-bottom">
                                    <a href="<?= $base_url ?>teacher/notifications.php" class="text-decoration-none fw-bold" style="font-size: 12px; color: var(--navy-bright);">Xem tất cả thông báo <i class="fa-solid fa-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </div>

                        <div class="vr mx-2 text-secondary opacity-25"></div>

                        <!-- Dropdown Profile -->
                        <div class="dropdown">
                            <div class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                                <?php if (!empty($_SESSION['user_avatar'])): ?>
                                    <img src="<?= $base_url . htmlspecialchars($_SESSION['user_avatar']) ?>" class="rounded-3 border shadow-sm" width="40" height="40" alt="Avatar" style="object-fit: cover;">
                                <?php else: ?>
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=2C74B3&color=fff" class="rounded-3 shadow-sm" width="40" height="40" alt="Avatar">
                                <?php endif; ?>
                                <div class="d-none d-md-block lh-1 text-start">
                                    <strong class="d-block text-body-emphasis mb-1"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Tên Người Dùng'); ?></strong>
                                    <small class="text-navy-bright fw-bold">
                                        <?php
                                        $role_label = 'Người dùng';
                                        if (isset($_SESSION['role'])) {
                                            switch (strtoupper($_SESSION['role'])) {
                                                case 'BGH': $role_label = 'Ban giám hiệu'; break;
                                                case 'GV': $role_label = 'Giáo viên'; break;
                                                case 'HS': $role_label = 'Học sinh'; break;
                                                case 'NV': $role_label = 'Nhân viên'; break;
                                                case 'PH': $role_label = 'Phụ huynh'; break;
                                            }
                                        }
                                        echo $role_label;
                                        ?>
                                    </small>
                                </div>
                            </div>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2">
                                <?php 
                                    $profile_link = "teacher/profile.php";
                                    if (isset($_SESSION['role']) && strtoupper($_SESSION['role']) === 'HS') {
                                        $profile_link = "student/profile.php";
                                    }
                                ?>
                                <li><a class="dropdown-item py-2" href="<?= $base_url . $profile_link ?>"><i class="fa-regular fa-id-badge me-2 text-muted"></i>Thông tin cá nhân</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger" href="<?= $base_url ?>auth/logout.php"><i class="fa-solid fa-power-off me-2"></i>Đăng xuất</a></li>
                            </ul>
                        </div>
                    </div>

                </div>
            </header>

            <!-- Phần nội dung chính -->
            <main class="flex-grow-1 overflow-y-auto p-4 position-relative">