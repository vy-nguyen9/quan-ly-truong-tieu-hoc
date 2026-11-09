<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Phiên đăng nhập hết hạn.']);
        exit;
    }

    $ma_gv = $_SESSION['user_id'];
    $tuNgay = $_POST['tuNgay'] ?? '';
    $denNgay = $_POST['denNgay'] ?? '';
    $lyDo = $_POST['lyDo'] ?? '';
    
    // Ghi chú thêm người dạy thay vào lý do (Vì db không có cột gv_day_thay_du_kien)
    if (!empty($_POST['gvDayThay'])) {
        $lyDo .= " | Đề xuất GV dạy thay: " . $_POST['gvDayThay'];
    }

    if (empty($tuNgay) || empty($denNgay) || empty($lyDo)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đầy đủ thông tin.']);
        exit;
    }

    if (strtotime($tuNgay) > strtotime($denNgay)) {
        echo json_encode(['status' => 'error', 'message' => 'Ngày bắt đầu không được lớn hơn ngày kết thúc.']);
        exit;
    }

    // Kiểm tra xem có đơn nào đang "Chờ duyệt" bị trùng thời gian không
    $sql_check = "
        SELECT ma_don FROM don_xin_nghi 
        WHERE ma_can_bo = ? 
          AND trang_thai = 'cho duyet' 
          AND (tu_ngay <= ? AND den_ngay >= ?)
    ";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "sss", $ma_gv, $denNgay, $tuNgay);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);

    if (mysqli_num_rows($result_check) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn đã có đơn xin nghỉ phép đang "Chờ duyệt" trùng với khoảng thời gian này!']);
        exit;
    }

    // Tạo mã đơn tự động (Format: DON + Mã GV + Timestamp)
    $ma_don = "DON" . substr($ma_gv, -3) . date('ymdHis');
    $trang_thai = 'cho duyet';

    // Thêm vào cơ sở dữ liệu
    $sql_insert = "INSERT INTO don_xin_nghi (ma_don, ma_can_bo, tu_ngay, den_ngay, ly_do, trang_thai, ngay_tao) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, "ssssss", $ma_don, $ma_gv, $tuNgay, $denNgay, $lyDo, $trang_thai);
    
    if (mysqli_stmt_execute($stmt_insert)) {
        echo json_encode(['status' => 'success', 'message' => 'Gửi đơn xin nghỉ phép thành công. Ban giám hiệu sẽ phản hồi sớm nhất!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu dữ liệu: ' . mysqli_error($conn)]);
    }
}
?>