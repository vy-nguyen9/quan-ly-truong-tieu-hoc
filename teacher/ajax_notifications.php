<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Phiên đăng nhập hết hạn.']);
        exit;
    }

    $ma_nguoi_nhan = $_SESSION['user_id'];
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $noti_id = $_POST['noti_id'];
        
        // Sử dụng REPLACE INTO hoặc chèn nếu chưa tồn tại
        $sql = "INSERT INTO nhan_thong_bao (ma_thong_bao, ma_nguoi_nhan, da_doc) 
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE da_doc = 1";
                
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $noti_id, $ma_nguoi_nhan);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    if ($action === 'mark_all_read') {
        // Lấy tất cả thông báo thuộc về người này (cá nhân + toàn trường) chưa đọc
        $sql_truong = "SELECT ma_truong FROM can_bo WHERE ma_nguoi_dung = ?";
        $stmt_tr = mysqli_prepare($conn, $sql_truong);
        mysqli_stmt_bind_param($stmt_tr, "s", $ma_nguoi_nhan);
        mysqli_stmt_execute($stmt_tr);
        $ma_truong = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tr))['ma_truong'];

        $sql_all = "
            SELECT tb.ma_thong_bao 
            FROM thong_bao tb
            LEFT JOIN nhan_thong_bao ntb ON tb.ma_thong_bao = ntb.ma_thong_bao AND ntb.ma_nguoi_nhan = ?
            WHERE ((ntb.ma_nguoi_nhan = ? AND ntb.da_doc = 0) OR (tb.ma_truong = ? AND tb.ma_doi_tuong IN ('all', 'gv')))
              AND (ntb.da_doc IS NULL OR ntb.da_doc = 0)
        ";
        
        $stmt_all = mysqli_prepare($conn, $sql_all);
        mysqli_stmt_bind_param($stmt_all, "sss", $ma_nguoi_nhan, $ma_nguoi_nhan, $ma_truong);
        mysqli_stmt_execute($stmt_all);
        $result = mysqli_stmt_get_result($stmt_all);
        
        // Update hàng loạt
        $sql_update = "INSERT INTO nhan_thong_bao (ma_thong_bao, ma_nguoi_nhan, da_doc) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE da_doc = 1";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        
        while ($row = mysqli_fetch_assoc($result)) {
            mysqli_stmt_bind_param($stmt_update, "ss", $row['ma_thong_bao'], $ma_nguoi_nhan);
            mysqli_stmt_execute($stmt_update);
        }

        echo json_encode(['status' => 'success']);
        exit;
    }
}
?>