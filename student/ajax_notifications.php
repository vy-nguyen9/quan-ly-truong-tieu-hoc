<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || strtoupper($_SESSION['role']) !== 'HS') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$ma_hs = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. Đánh dấu Đã đọc 1 thông báo
    if ($action === 'mark_read') {
        $noti_id = $_POST['noti_id'] ?? '';
        if (empty($noti_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
            exit;
        }

        // Dùng INSERT ON DUPLICATE KEY UPDATE: Nếu đã có dữ liệu thì update thành đã đọc (1), chưa có thì tạo mới (1)
        $sql = "INSERT INTO nhan_thong_bao (ma_thong_bao, ma_nguoi_nhan, da_doc) 
                VALUES (?, ?, 1) 
                ON DUPLICATE KEY UPDATE da_doc = 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $noti_id, $ma_hs);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'DB Error']);
        }
        exit;
    }
    
    // 2. Đánh dấu Tất cả Đã đọc
    if ($action === 'mark_all_read') {
        // Cập nhật những cái đã có trong bảng nhận
        $sql_update = "UPDATE nhan_thong_bao SET da_doc = 1 WHERE ma_nguoi_nhan = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "s", $ma_hs);
        mysqli_stmt_execute($stmt_update);

        // Chèn vào bảng nhận những thông báo chung (all, hs) mà người này chưa từng click vào
        $sql_truong = "SELECT dt.ma_truong FROM hoc_sinh hs JOIN diem_truong dt ON hs.ma_diem_truong = dt.ma_diem_truong WHERE hs.ma_nguoi_dung = ?";
        $stmt_truong = mysqli_prepare($conn, $sql_truong);
        mysqli_stmt_bind_param($stmt_truong, "s", $ma_hs);
        mysqli_stmt_execute($stmt_truong);
        $ma_truong = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_truong))['ma_truong'] ?? '';

        $sql_insert_all = "
            INSERT IGNORE INTO nhan_thong_bao (ma_thong_bao, ma_nguoi_nhan, da_doc)
            SELECT ma_thong_bao, ?, 1 
            FROM thong_bao 
            WHERE ma_truong = ? AND ma_doi_tuong IN ('all', 'hs')
        ";
        $stmt_ins = mysqli_prepare($conn, $sql_insert_all);
        mysqli_stmt_bind_param($stmt_ins, "ss", $ma_hs, $ma_truong);
        mysqli_stmt_execute($stmt_ins);

        echo json_encode(['status' => 'success']);
        exit;
    }
}

echo json_encode(['status' => 'invalid_request']);
?>