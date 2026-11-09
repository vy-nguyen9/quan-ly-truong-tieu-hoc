<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lopData']) && isset($_POST['namHoc'])) {
    
    $namHoc = $_POST['namHoc'];
    $lopData = explode('_', $_POST['lopData']); // Format: khoi_manhomlop_stt
    
    if (count($lopData) == 3) {
        $khoi = $lopData[0];
        $ma_nhom_lop = $lopData[1];
        $stt = $lopData[2];

        // Lấy danh sách học sinh của lớp
        $sql = "
            SELECT nd.ho, nd.ten, nd.gioi_tinh, DATE_FORMAT(nd.ngay_sinh, '%d/%m/%Y') as ngay_sinh 
            FROM hoc_sinh_lop hsl
            JOIN nguoi_dung nd ON hsl.ma_hoc_sinh = nd.ma_nguoi_dung
            WHERE hsl.ma_nam = ? 
              AND hsl.khoi = ? 
              AND hsl.ma_nhom_lop = ? 
              AND hsl.stt = ?
            ORDER BY nd.ten ASC, nd.ho ASC
        ";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssss", $namHoc, $khoi, $ma_nhom_lop, $stt);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $students = mysqli_fetch_all($result, MYSQLI_ASSOC);

        // LẤY THÊM THÔNG TIN TRƯỜNG VÀ GVCN
        $sql_info = "
            SELECT tr.ten_truong, CONCAT(nd.ho, ' ', nd.ten) AS ten_gvcn
            FROM lop_hoc lh
            JOIN truong tr ON lh.ma_truong = tr.ma_truong
            LEFT JOIN nguoi_dung nd ON lh.chu_nhiem = nd.ma_nguoi_dung
            WHERE lh.ma_nam = ? AND lh.khoi = ? AND lh.ma_nhom_lop = ? AND lh.stt = ?
        ";
        $stmt_info = mysqli_prepare($conn, $sql_info);
        mysqli_stmt_bind_param($stmt_info, "ssss", $namHoc, $khoi, $ma_nhom_lop, $stt);
        mysqli_stmt_execute($stmt_info);
        $info_result = mysqli_stmt_get_result($stmt_info);
        $class_info = mysqli_fetch_assoc($info_result);
        
        echo json_encode([
            'status' => 'success',
            'data' => $students,
            'school_name' => $class_info['ten_truong'] ?? 'TRƯỜNG TIỂU HỌC',
            'teacher_name' => $class_info['ten_gvcn'] ?? 'Chưa phân công'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Dữ liệu đầu vào không hợp lệ']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ']);
}
?>