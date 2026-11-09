<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $sql = "SELECT tk.ma_nguoi_dung, tk.mat_khau, tk.trang_thai,
                       nd.ho, nd.ten, nd.ma_vai_tro, nd.anh_dai_dien
                FROM tai_khoan tk
                JOIN nguoi_dung nd ON tk.ma_nguoi_dung = nd.ma_nguoi_dung
                WHERE tk.user_id = ? OR tk.ma_nguoi_dung = ? LIMIT 1";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                if ($user['trang_thai'] == 0) {
                    $error = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên!';
                } else {
                    if (password_verify($password, $user['mat_khau'])) {
                        $_SESSION['user_id'] = $user['ma_nguoi_dung'];
                        $_SESSION['user_name'] = trim($user['ho'] . ' ' . $user['ten']);
                        $_SESSION['role'] = $user['ma_vai_tro'];
                        $_SESSION['user_avatar'] = $user['anh_dai_dien'];
                        
                        // Xử lý chuyển trang theo Role
                        // Xử lý chuyển trang theo Role
                        $role = strtoupper($user['ma_vai_tro']);
                        if ($role === 'HS') {
                            // Học sinh đăng nhập thành công sẽ được chuyển thẳng đến trang Thời khóa biểu
                            header("Location: ../student/schedule_student.php");
                        } elseif (in_array($role, ['GV', 'BGH'])) {
                            header("Location: ../teacher/index.php");
                        } else {
                            header("Location: ../index.php"); // Fallback cho các role khác (như admin, nhân viên)
                        }
                        exit();
                    } else {
                        $error = 'Mật khẩu không chính xác!';
                    }
                }
            } else {
                $error = 'Tài khoản không tồn tại trên hệ thống!';
            }
            $stmt->close();
        } else {
            $error = 'Lỗi truy vấn cơ sở dữ liệu!';
        }
    } else {
        $error = 'Vui lòng nhập đầy đủ thông tin đăng nhập!';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=<?php echo time(); ?>">
</head>

<body class="d-flex align-items-center justify-content-center min-vh-100 p-3">

    <!-- NỀN NAVY VÀ HOA VĂN TRỐNG ĐỒNG SVG -->
    <div class="login-bg-art" aria-hidden="true">
        <div class="navy-glow glow-one"></div>
        <div class="navy-glow glow-two"></div>

        <div class="dong-son-wrapper">
            <?php 
                $svg_path = __DIR__ . '/../assets/images/trong-dong.svg';
                if (file_exists($svg_path)) {
                    echo file_get_contents($svg_path);
                } else {
                    echo '<!-- Lỗi: Không tìm thấy file tại ' . htmlspecialchars($svg_path) . ' -->';
                }
            ?>
        </div>

        <div class="login-bg-vignette"></div>
    </div>

    <!-- KHUNG ĐĂNG NHẬP (KÍNH MỜ) -->
    <div class="login-wrapper">
        <div class="card login-card p-4 p-md-5">
            <div class="position-relative" style="z-index: 1;">
                <div class="text-center mb-4">
                    <div class="mb-3 d-flex justify-content-center">
                        <img src="../assets/images/logo2.png" alt="Avenir Education System Logo" style="width: 115px; height: auto;">
                    </div>
                    <h3 class="fw-bolder mb-2" style="color: #0A2647;">Đăng nhập hệ thống</h3>
                    
                    <div class="mt-3">
                        <h6 class="fw-bold mb-1" style="color: #0A2647; font-size: 1rem;">
                            Avenir Education System
                        </h6>
                        <p class="small fst-italic text-muted mb-0">
                            Vun đắp hôm nay - Kiến tạo ngày mai
                        </p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger rounded-3 d-flex align-items-center py-2 px-3 small border-0 bg-danger bg-opacity-10 text-danger mb-4" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        <span class="fw-medium"><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <!-- Sử dụng mã màu #1E293B như yêu cầu -->
                        <label class="form-label small fw-bold" style="color: #1E293B;">Tên đăng nhập</label>
                        <div class="custom-input-wrap">
                            <i class="fa-solid fa-user left-icon"></i>
                            <input type="text" name="username" class="custom-form-control" placeholder="Nhập tài khoản" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold" style="color: #1E293B;">Mật khẩu</label>
                        <div class="custom-input-wrap">
                            <i class="fa-solid fa-lock left-icon"></i>
                            <input type="password" id="password" name="password" class="custom-form-control" placeholder="••••••••" style="padding-right: 42px;" required>
                            <button type="button" class="right-icon" onclick="togglePassword()" aria-label="Hiện hoặc ẩn mật khẩu">
                                <i id="eyeIcon" class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-4 mt-2">
                        <div class="form-check">
                            <input class="form-check-input shadow-sm cursor-pointer" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label small fw-bold cursor-pointer" style="color: #1E293B;" for="remember">Ghi nhớ mật khẩu</label>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit w-100 d-flex align-items-center justify-content-center gap-2">
                        Đăng nhập
                    </button>
                </form>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/auth.js"></script>

</body>
</html>