<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action =$_POST['action'] ?? '';

    // ==========================================
    // 1. LẤY THỜI KHÓA BIỂU CỦA LỚP
    // ==========================================
    if ($action === 'get_student_schedule') {
        $ma_hs =$_SESSION['user_id'];
        
        // Lấy năm học hiện tại
        $sql_nam = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
        $result_nam = mysqli_query($conn,$sql_nam);
        $namHoc = mysqli_fetch_assoc($result_nam)['ma_nam'] ?? '2026-2027';

        // Lấy học kỳ hiện tại (dựa vào tháng hiện tại)
        $currentMonth = (int)date('m');$hocKy = ($currentMonth >= 8 || $currentMonth <= 1) ? 'HK1' : 'HK2';

        // Bước 1: Tìm lớp học hiện tại của học sinh
        $sql_lop = "SELECT khoi, ma_nhom_lop, stt, ma_truong FROM hoc_sinh_lop WHERE ma_hoc_sinh = ? AND ma_nam = ? LIMIT 1";
        $stmt_lop = mysqli_prepare($conn,$sql_lop);
        mysqli_stmt_bind_param($stmt_lop, "ss", $ma_hs,$namHoc);
        mysqli_stmt_execute($stmt_lop);
        $lop_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_lop));

        if (!$lop_info) {
            echo json_encode(['status' => 'error', 'message' => 'Bạn chưa được xếp vào lớp nào trong năm học này.']);
            exit;
        }

        $khoi =$lop_info['khoi'];
        $ma_nhom_lop =$lop_info['ma_nhom_lop'];
        $stt =$lop_info['stt'];
        $ma_truong =$lop_info['ma_truong'];

        // Bước 2: Lấy chi tiết Thời khóa biểu của lớp đó
        $sql_tkb = "
            SELECT 
                ct.thu, ct.tiet, mh.ten_mon, nd.ho, nd.ten, cb.ma_nguoi_dung AS ma_gv
            FROM chi_tiet_tkb ct
            JOIN thoi_khoa_bieu tkb ON ct.ma_tkb = tkb.ma_tkb
            JOIN mon_hoc mh ON tkb.ma_mon_hoc = mh.ma_mon
            JOIN can_bo cb ON tkb.ma_can_bo = cb.ma_nguoi_dung
            JOIN nguoi_dung nd ON cb.ma_nguoi_dung = nd.ma_nguoi_dung
            WHERE tkb.ma_nam = ? AND tkb.khoi = ? AND tkb.ma_nhom_lop = ? AND tkb.stt = ? AND tkb.ma_truong = ? AND tkb.hoc_ky = ?
            ORDER BY ct.tiet ASC, ct.thu ASC
        ";
        
        $stmt_tkb = mysqli_prepare($conn,$sql_tkb);
        mysqli_stmt_bind_param($stmt_tkb, "ssssss", $namHoc,$khoi, $ma_nhom_lop,$stt, $ma_truong,$hocKy);
        mysqli_stmt_execute($stmt_tkb);
        $result = mysqli_fetch_all(mysqli_stmt_get_result($stmt_tkb), MYSQLI_ASSOC);

        // Tạo mảng matrix để dễ đổ ra bảng HTML: matrix[tiet][thu] = thông tin môn
        $scheduleMatrix = [];
        // Giả sử có 10 tiết/ngày (Sáng 1-5, Chiều 6-10)
        for ($t = 1; $t <= 10; $t++) {
            $scheduleMatrix[$t] = [
                2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null
            ];
        }

        foreach ($result as$row) {
            $tiet = (int)$row['tiet'];
            $thu = (int)$row['thu'];
            if (isset($scheduleMatrix[$tiet][$thu])) {$scheduleMatrix[$tiet][$thu] = [
                    'mon' => $row['ten_mon'],
                    'gv'  => $row['ho'] . ' ' .$row['ten']
                ];
            }
        }

        $tenLop = "Lớp {$khoi}/{$stt}";
        
        echo json_encode([
            'status' => 'success', 
            'data' => $scheduleMatrix,
            'thongTinLop' => $tenLop,
            'namHoc' => $namHoc,
            'hocKy' => $hocKy
        ]);
        exit;
    }
}
?>