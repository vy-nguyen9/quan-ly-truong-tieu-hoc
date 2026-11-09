<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ==========================================
    // 1. TẢI DỮ LIỆU HỌC BẠ (DANH SÁCH)
    // ==========================================
    if ($action === 'get_danh_sach_hoc_ba') {
        $namHoc = $_POST['namHoc'];
        $lopData = explode('_', $_POST['lopData']); 
        $searchKeyword = trim($_POST['searchKeyword'] ?? '');
        
        if (count($lopData) < 4) {
            echo json_encode(['status' => 'error', 'message' => 'Dữ liệu lớp không hợp lệ.']);
            exit;
        }

        // Lấy danh sách HS
        $sql_hs = "SELECT nd.ma_nguoi_dung as ma_hs, nd.ho, nd.ten, nd.ngay_sinh 
                   FROM hoc_sinh_lop hsl 
                   JOIN nguoi_dung nd ON hsl.ma_hoc_sinh = nd.ma_nguoi_dung 
                   WHERE hsl.ma_nam = ? AND hsl.khoi = ? AND hsl.ma_nhom_lop = ? AND hsl.stt = ? AND hsl.ma_truong = ?";
        
        $params = [$namHoc, $lopData[0], $lopData[1], $lopData[2], $lopData[3]];
        $types = "sssss";

        if ($searchKeyword !== '') {
            $sql_hs .= " AND (nd.ma_nguoi_dung LIKE ? OR nd.ho LIKE ? OR nd.ten LIKE ?)";
            $kw = "%" . $searchKeyword . "%";
            array_push($params, $kw, $kw, $kw);
            $types .= "sss";
        }
        $sql_hs .= " ORDER BY nd.ten ASC, nd.ho ASC";

        $stmt = mysqli_prepare($conn, $sql_hs);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $students = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        // Lấy trạng thái chốt 
        $da_chot_lop = 0;
        if(count($students) > 0) {
            $ma_hs_test = $students[0]['ma_hs'];
            $sql_check = "SELECT da_chot FROM hoc_ba WHERE ma_hoc_sinh = ? AND ma_nam = ? LIMIT 1";
            $stmt_check = mysqli_prepare($conn, $sql_check);
            mysqli_stmt_bind_param($stmt_check, "ss", $ma_hs_test, $namHoc);
            mysqli_stmt_execute($stmt_check);
            $res_check = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
            if($res_check) $da_chot_lop = $res_check['da_chot'];
        }

        // Lấy trạng thái chốt riêng lẻ
        $sql_hb = "SELECT ma_hoc_sinh, da_chot, duoc_len_lop FROM hoc_ba WHERE ma_nam = ?";
        $stmt_hb = mysqli_prepare($conn, $sql_hb);
        mysqli_stmt_bind_param($stmt_hb, "s", $namHoc);
        mysqli_stmt_execute($stmt_hb);
        $hb_data = mysqli_fetch_all(mysqli_stmt_get_result($stmt_hb), MYSQLI_ASSOC);
        $hb_map = [];
        foreach ($hb_data as $h) { 
            $hb_map[$h['ma_hoc_sinh']] = [
                'da_chot' => $h['da_chot'], 
                'duoc_len_lop' => $h['duoc_len_lop']
            ]; 
        }

        foreach ($students as &$hs) {
            $hs['da_chot'] = $hb_map[$hs['ma_hs']]['da_chot'] ?? 0;
            $hs['duoc_len_lop'] = $hb_map[$hs['ma_hs']]['duoc_len_lop'] ?? 0;
        }
        
        echo json_encode(['status' => 'success', 'data' => $students, 'da_chot' => $da_chot_lop]);
        exit;
    }

    // ==========================================
    // 2. KÝ CHỐT HỌC BẠ (CẢ LỚP HOẶC CÁ NHÂN)
    // ==========================================
    if ($action === 'chot_hoc_ba_toan_lop' || $action === 'chot_hoc_ba_ca_nhan') {
        $ma_nguoi_dung = $_SESSION['user_id'];
        $namHoc = $_POST['namHoc'];
        $lopData = explode('_', $_POST['lopData']); 
        $ma_hs_req = $_POST['ma_hs'] ?? '';
        
        $khoi = $lopData[0];
        $ma_nhom_lop = $lopData[1];
        $stt = $lopData[2];
        $ma_truong = $lopData[3];

        $sql_gvcn = "SELECT chu_nhiem FROM lop_hoc WHERE ma_nam = ? AND khoi = ? AND ma_nhom_lop = ? AND stt = ? AND ma_truong = ?";
        $stmt_gvcn = mysqli_prepare($conn, $sql_gvcn);
        mysqli_stmt_bind_param($stmt_gvcn, "sssss", $namHoc, $khoi, $ma_nhom_lop, $stt, $ma_truong);
        mysqli_stmt_execute($stmt_gvcn);
        $res_gvcn = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_gvcn));

        if (!$res_gvcn || $res_gvcn['chu_nhiem'] !== $ma_nguoi_dung) {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi: Không có quyền chủ nhiệm.']);
            exit;
        }

        if ($action === 'chot_hoc_ba_toan_lop') {
            $sql_hs = "SELECT ma_hoc_sinh FROM hoc_sinh_lop WHERE ma_nam = ? AND khoi = ? AND ma_nhom_lop = ? AND stt = ? AND ma_truong = ?";
            $stmt_hs = mysqli_prepare($conn, $sql_hs);
            mysqli_stmt_bind_param($stmt_hs, "sssss", $namHoc, $khoi, $ma_nhom_lop, $stt, $ma_truong);
            mysqli_stmt_execute($stmt_hs);
            $ds_hoc_sinh = mysqli_fetch_all(mysqli_stmt_get_result($stmt_hs), MYSQLI_ASSOC);
        } else {
            $ds_hoc_sinh = [['ma_hoc_sinh' => $ma_hs_req]];
        }

        if (count($ds_hoc_sinh) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy học sinh.']);
            exit;
        }

        mysqli_begin_transaction($conn);
        try {
            foreach ($ds_hoc_sinh as $hs) {
                $ma_hs = $hs['ma_hoc_sinh'];
                
                // Thuật toán xét lên lớp
                $sql_dmon = "SELECT ma_mon_hoc, diem_cuoi_ky, muc_dat_cuoi_ky FROM danh_gia_mon_hoc WHERE ma_hoc_sinh = ? AND ma_nam = ? AND hoc_ky = 'HK2'";
                $stmt_dm = mysqli_prepare($conn, $sql_dmon);
                mysqli_stmt_bind_param($stmt_dm, "ss", $ma_hs, $namHoc);
                mysqli_stmt_execute($stmt_dm);
                $diem_mon = mysqli_fetch_all(mysqli_stmt_get_result($stmt_dm), MYSQLI_ASSOC);

                $diem_toan = null; $diem_tv = null;
                $so_mon_c = 0; $so_mon_t = 0; $tong_mon = 0;
                foreach($diem_mon as $dm) {
                    $tong_mon++;
                    if($dm['ma_mon_hoc'] == 'TOAN') $diem_toan = $dm['diem_cuoi_ky'];
                    if($dm['ma_mon_hoc'] == 'TV') $diem_tv = $dm['diem_cuoi_ky'];
                    if($dm['muc_dat_cuoi_ky'] == 'C') $so_mon_c++;
                    if($dm['muc_dat_cuoi_ky'] == 'T') $so_mon_t++;
                }

                $sql_dpcnl = "SELECT muc_dat FROM danh_gia_pcnl WHERE ma_hoc_sinh = ? AND ma_nam = ? AND hoc_ky = 'HK2'";
                $stmt_dp = mysqli_prepare($conn, $sql_dpcnl);
                mysqli_stmt_bind_param($stmt_dp, "ss", $ma_hs, $namHoc);
                mysqli_stmt_execute($stmt_dp);
                $diem_pcnl = mysqli_fetch_all(mysqli_stmt_get_result($stmt_dp), MYSQLI_ASSOC);

                $so_pcnl_c = 0; $so_pcnl_t = 0; $tong_pcnl = 0;
                foreach($diem_pcnl as $dp) {
                    $tong_pcnl++;
                    if($dp['muc_dat'] == 'C') $so_pcnl_c++;
                    if($dp['muc_dat'] == 'T') $so_pcnl_t++;
                }

                // Check nhập đủ
                if ($diem_toan === null || $diem_tv === null || $tong_mon < 2 || $tong_pcnl < 8) {
                    throw new Exception("Học sinh [{$ma_hs}] chưa nhập đủ điểm HK2. Không thể chốt.");
                }

                $len_lop = 0;
                $danh_hieu = '';
                // Logic thông tư 27 (Cơ bản)
                if ($diem_toan >= 5 && $diem_tv >= 5 && $so_mon_c == 0 && $so_pcnl_c == 0) {
                    $len_lop = 1;
                    if ($diem_toan >= 9 && $diem_tv >= 9 && $so_mon_t == $tong_mon && $so_pcnl_t == $tong_pcnl) {
                        $danh_hieu = 'Hoàn thành Xuất sắc';
                    } elseif ($diem_toan >= 7 && $diem_tv >= 7) {
                        $danh_hieu = 'Học sinh Tiêu biểu';
                    }
                }
                
                $ma_hb = "HB_" . $ma_hs . "_" . str_replace('-', '', $namHoc); 
                
                $sql_up_hb = "
                    INSERT INTO hoc_ba (ma_hoc_ba, ma_hoc_sinh, ma_nam, khoi, ma_nhom_lop, stt, danh_hieu, duoc_len_lop, da_chot, ngay_chot, ma_truong)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, ?)
                    ON DUPLICATE KEY UPDATE 
                    danh_hieu = VALUES(danh_hieu),
                    duoc_len_lop = VALUES(duoc_len_lop),
                    da_chot = 1,
                    ngay_chot = CURRENT_TIMESTAMP
                ";
                $stmt_up_hb = mysqli_prepare($conn, $sql_up_hb);
                mysqli_stmt_bind_param($stmt_up_hb, "sssssssss", $ma_hb, $ma_hs, $namHoc, $khoi, $ma_nhom_lop, $stt, $danh_hieu, $len_lop, $ma_truong);
                mysqli_stmt_execute($stmt_up_hb);
            }
            mysqli_commit($conn);
            echo json_encode(['status' => 'success', 'message' => 'Đã thực hiện Ký chốt thành công!']);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}
?>