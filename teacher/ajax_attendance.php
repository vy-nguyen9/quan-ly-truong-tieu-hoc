<?php
session_start();
require_once '../config/database.php'; // Đảm bảo kết nối $conn dùng mysqli

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'get') {
        $namHoc = $_POST['namHoc'];
        $ngay = $_POST['ngay'];
        $tiet = (int)$_POST['tiet'];
        $lopData = explode('_', $_POST['lopData']); // khoi_manhomlop_stt_matruong
        
        if (count($lopData) == 4) {
            $khoi = $lopData[0];
            $ma_nhom_lop = $lopData[1];
            $stt = $lopData[2];
            $ma_truong = $lopData[3];

            // 1. Lấy danh sách HS của lớp
            $sql_hs = "
                SELECT nd.ma_nguoi_dung as ma_hs, nd.ho, nd.ten 
                FROM hoc_sinh_lop hsl
                JOIN nguoi_dung nd ON hsl.ma_hoc_sinh = nd.ma_nguoi_dung
                WHERE hsl.ma_nam = ? AND hsl.khoi = ? AND hsl.ma_nhom_lop = ? AND hsl.stt = ? AND hsl.ma_truong = ?
                ORDER BY nd.ten ASC, nd.ho ASC
            ";
            $stmt = mysqli_prepare($conn, $sql_hs);
            mysqli_stmt_bind_param($stmt, "sssss", $namHoc, $khoi, $ma_nhom_lop, $stt, $ma_truong);
            mysqli_stmt_execute($stmt);
            $students = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

            // 2. Lấy dữ liệu điểm danh cũ (nếu có)
            $sql_dd = "
                SELECT ma_hoc_sinh, trang_thai, ghi_chu 
                FROM diem_danh 
                WHERE ngay = ? AND tiet_hoc = ? AND ma_nam = ? AND khoi = ? AND ma_nhom_lop = ? AND stt = ? AND ma_truong = ?
            ";
            $stmt_dd = mysqli_prepare($conn, $sql_dd);
            mysqli_stmt_bind_param($stmt_dd, "sisssss", $ngay, $tiet, $namHoc, $khoi, $ma_nhom_lop, $stt, $ma_truong);
            mysqli_stmt_execute($stmt_dd);
            $attendance_data = mysqli_fetch_all(mysqli_stmt_get_result($stmt_dd), MYSQLI_ASSOC);
            
            $dd_map = [];
            foreach ($attendance_data as $row) {
                $dd_map[$row['ma_hoc_sinh']] = [
                    'trang_thai' => $row['trang_thai'],
                    'ghi_chu' => $row['ghi_chu']
                ];
            }

            foreach ($students as &$hs) {
                if (isset($dd_map[$hs['ma_hs']])) {
                    $hs['trang_thai'] = $dd_map[$hs['ma_hs']]['trang_thai'];
                    $hs['ghi_chu'] = $dd_map[$hs['ma_hs']]['ghi_chu'];
                } else {
                    $hs['trang_thai'] = ''; 
                    $hs['ghi_chu'] = '';
                }
            }
            
            // 3. Lấy tên trường
            $sql_truong = "SELECT ten_truong FROM truong WHERE ma_truong = ?";
            $stmt_tr = mysqli_prepare($conn, $sql_truong);
            mysqli_stmt_bind_param($stmt_tr, "s", $ma_truong);
            mysqli_stmt_execute($stmt_tr);
            $truong_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tr));
            $ten_truong = $truong_info ? $truong_info['ten_truong'] : 'TRƯỜNG TIỂU HỌC';

            // TRẢ VỀ JSON MỘT LẦN DUY NHẤT
            echo json_encode([
                'status' => 'success', 
                'data' => $students,
                'school_name' => $ten_truong
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Dữ liệu lớp không hợp lệ']);
        }
        exit;
    }

    if ($action === 'save') {
        $namHoc = $_POST['namHoc'];
        $ngay = $_POST['ngay'];
        $tiet = (int)$_POST['tiet'];
        $ma_gv = $_SESSION['user_id'];
        $lopData = explode('_', $_POST['lopData']);
        $attendanceList = json_decode($_POST['attendanceData'], true);

        if (count($lopData) == 4 && is_array($attendanceList)) {
            $khoi = $lopData[0];
            $ma_nhom_lop = $lopData[1];
            $stt = $lopData[2];
            $ma_truong = $lopData[3];

            mysqli_begin_transaction($conn);
            try {
                $sql_del = "DELETE FROM diem_danh WHERE ngay = ? AND tiet_hoc = ? AND ma_nam = ? AND khoi = ? AND ma_nhom_lop = ? AND stt = ? AND ma_truong = ?";
                $stmt_del = mysqli_prepare($conn, $sql_del);
                mysqli_stmt_bind_param($stmt_del, "sisssss", $ngay, $tiet, $namHoc, $khoi, $ma_nhom_lop, $stt, $ma_truong);
                mysqli_stmt_execute($stmt_del);

                $sql_ins = "INSERT INTO diem_danh (ma_diem_danh, ma_hoc_sinh, ngay, tiet_hoc, trang_thai, ghi_chu, ma_giao_vien, ma_nam, khoi, ma_nhom_lop, stt, ma_truong) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_ins = mysqli_prepare($conn, $sql_ins);

                foreach ($attendanceList as $item) {
                    $ma_hs = $item['ma_hs'];
                    $trang_thai = $item['trang_thai'];
                    $ghi_chu = $item['ghi_chu'];
                    $ma_diem_danh = "DD" . date('md', strtotime($ngay)) . $tiet . substr($ma_hs, -3) . rand(10,99);
                    
                    mysqli_stmt_bind_param($stmt_ins, "sssissssssss", $ma_diem_danh, $ma_hs, $ngay, $tiet, $trang_thai, $ghi_chu, $ma_gv, $namHoc, $khoi, $ma_nhom_lop, $stt, $ma_truong);
                    mysqli_stmt_execute($stmt_ins);
                }

                mysqli_commit($conn);
                echo json_encode(['status' => 'success', 'message' => 'Lưu kết quả điểm danh thành công!']);
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu dữ liệu: ' . $e->getMessage()]);
            }
        }
        exit;
    }
}
?>