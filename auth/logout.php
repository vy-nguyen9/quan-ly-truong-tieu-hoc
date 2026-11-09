<?php
// Bắt đầu session để có thể thao tác xóa nó
session_start();

// 1. Xóa toàn bộ dữ liệu (biến) đã lưu trong session
$_SESSION = array();

// 2. Xóa luôn cookie chứa ID của session (Tăng cường bảo mật, tránh bị đánh cắp phiên)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Phá hủy hoàn toàn session trên server
session_destroy();

// 4. Chuyển hướng người dùng về trang đăng nhập
header("Location: login.php");
exit();
?>