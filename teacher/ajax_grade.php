<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ==========================================
    // LẤY MÔN HỌC
    // ==========================================
    if ($action === 'get_subjects') {
        $namHoc = $_POST['namHoc'];
        $ma_gv = $_SESSION['user_id'];
        $lopData = explode('_', $_POST['lopData']); 
        if (count($lopData) < 4) { echo json_encode(['status' => 'error', 'message' => 'Dữ liệu lớp không hợp lệ.']); exit; }

        $sql = "SELECT pcd.ma_mon, mh.ten_mon FROM phan_cong_day pcd JOIN mon_hoc mh ON pcd.ma_mon = mh.ma_mon WHERE pcd.ma_nguoi_dung = ? AND pcd.ma_nam = ? AND pcd.khoi = ? AND pcd.ma_nhom_lop = ? AND pcd.stt = ? AND pcd.ma_truong = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssss", $ma_gv, $namHoc, $lopData[0], $lopData[1], $lopData[2], $lopData[3]);
        mysqli_stmt_execute($stmt);
        echo json_encode(['status' => 'success', 'data' => mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC)]);
        exit;
    }

    // ==========================================
    // LẤY DANH SÁCH HỌC SINH + ĐIỂM
    // ==========================================
    if ($action === 'get_students_grade') {
        $namHoc = $_POST['namHoc'];
        $hocKy = $_POST['hocKy'];
        $pageType = $_POST['pageType'];
        $lopData = explode('_', $_POST['lopData']);
        $maMon = $_POST['maMon'] ?? '';

        if (count($lopData) < 4) { echo json_encode(['status' => 'error', 'message' => 'Dữ liệu lớp không hợp lệ.']); exit; }

        $sql_hs = "SELECT nd.ma_nguoi_dung as ma_hs, nd.ho, nd.ten FROM hoc_sinh_lop hsl JOIN nguoi_dung nd ON hsl.ma_hoc_sinh = nd.ma_nguoi_dung WHERE hsl.ma_nam = ? AND hsl.khoi = ? AND hsl.ma_nhom_lop = ? AND hsl.stt = ? AND hsl.ma_truong = ? ORDER BY nd.ten ASC, nd.ho ASC";
        $stmt = mysqli_prepare($conn, $sql_hs);
        mysqli_stmt_bind_param($stmt, "sssss", $namHoc, $lopData[0], $lopData[1], $lopData[2], $lopData[3]);
        mysqli_stmt_execute($stmt);
        $students = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        if ($pageType === 'subject' && !empty($maMon)) {
            $sql_diem = "SELECT ma_hoc_sinh, diem_giua_ky as muc_dat_gk, muc_dat_cuoi_ky as muc_dat_ck, diem_cuoi_ky as diem_ck, nhan_xet_cuoi_ky as nx_ck FROM danh_gia_mon_hoc WHERE ma_mon_hoc = ? AND ma_nam = ? AND hoc_ky = ?";
            $stmt_diem = mysqli_prepare($conn, $sql_diem);
            mysqli_stmt_bind_param($stmt_diem, "sss", $maMon, $namHoc, $hocKy);
            mysqli_stmt_execute($stmt_diem);
            $diem_data = mysqli_fetch_all(mysqli_stmt_get_result($stmt_diem), MYSQLI_ASSOC);
            
            $diem_map = [];
            foreach ($diem_data as $d) $diem_map[$d['ma_hoc_sinh']] = $d;

            foreach ($students as &$hs) {
                if (isset($diem_map[$hs['ma_hs']])) {
                    $d = $diem_map[$hs['ma_hs']];
                    // Loại bỏ số thập phân khi gửi về Client
                    if ($d['diem_ck'] !== null && $d['diem_ck'] !== '') {
                        $d['diem_ck'] = (int) round((float)$d['diem_ck']); 
                    }
                    $hs = array_merge($hs, $d);
                }
            }
        }
        
        echo json_encode(['status' => 'success', 'data' => $students]);
        exit;
    }

    // ==========================================
    // GIẢI THUẬT 3 & 2: LƯU & IMPORT VÀ KIỂM TRA RÀNG BUỘC 
    // ==========================================
    if ($action === 'save_grades' || $action === 'import_excel') {
        $namHoc = $_POST['namHoc'];
        $hocKy = $_POST['hocKy'];
        $pageType = $_POST['pageType'];
        $lopData = explode('_', $_POST['lopData']);
        $maMon = $_POST['maMon'] ?? '';
        
        $dataKey = ($action === 'import_excel') ? 'importData' : 'saveData';
        $dataList = json_decode($_POST[$dataKey], true);
        
        if (count($lopData) < 4 || !is_array($dataList)) {
            echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }

        // 1. Kiểm tra khóa điểm (Giải thuật 3)
        $sql_khoa = "SELECT han_khoa_diem FROM khoa_diem WHERE ma_truong = ? AND ma_nam = ? AND hoc_ky = ?";
        $stmt_khoa = mysqli_prepare($conn, $sql_khoa);
        mysqli_stmt_bind_param($stmt_khoa, "sss", $lopData[3], $namHoc, $hocKy);
        mysqli_stmt_execute($stmt_khoa);
        $res_khoa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_khoa));

        if (!$res_khoa) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông tin khóa điểm của học kỳ này.']);
            exit;
        }
        if (strtotime(date('Y-m-d')) > strtotime($res_khoa['han_khoa_diem'])) {
            echo json_encode(['status' => 'error', 'message' => 'Đã hết hạn nhập/sửa điểm cho học kỳ này (Hạn chót: '.date('d/m/Y', strtotime($res_khoa['han_khoa_diem'])).').']);
            exit;
        }

        mysqli_begin_transaction($conn);
        try {
            $so_dong_thanh_cong = 0;
            $danh_sach_loi = [];

            foreach ($dataList as $row) {
                $ma_hs = $row['ma_hs'];

                // 2. Kiểm tra khớp dữ liệu gốc khi import (Giải thuật 2)
                if ($action === 'import_excel') {
                    $sql_check_hs = "SELECT ho, ten FROM nguoi_dung WHERE ma_nguoi_dung = ?";
                    $stmt_chk = mysqli_prepare($conn, $sql_check_hs);
                    mysqli_stmt_bind_param($stmt_chk, "s", $ma_hs);
                    mysqli_stmt_execute($stmt_chk);
                    $hs_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_chk));

                    if (!$hs_info) {
                        $danh_sach_loi[] = "Mã học sinh {$ma_hs} không tồn tại.";
                        continue;
                    }
                    if ($hs_info['ho'] != trim($row['ho']) || $hs_info['ten'] != trim($row['ten'])) {
                        $danh_sach_loi[] = "Học sinh {$ma_hs}: Thông tin gốc (Họ tên) đã bị thay đổi sai lệch.";
                        continue;
                    }
                }

                if ($pageType === 'subject') {
                    // CHUYỂN ĐỔI: 1 -> T, 2 -> H, 3 -> C
                    $muc_dat_gk = strtoupper(trim($row['muc_dat_gk'] ?? ''));
                    if ($muc_dat_gk === '1') $muc_dat_gk = 'T';
                    elseif ($muc_dat_gk === '2') $muc_dat_gk = 'H';
                    elseif ($muc_dat_gk === '3') $muc_dat_gk = 'C';

                    $muc_dat_ck = strtoupper(trim($row['muc_dat_ck'] ?? ''));
                    if ($muc_dat_ck === '1') $muc_dat_ck = 'T';
                    elseif ($muc_dat_ck === '2') $muc_dat_ck = 'H';
                    elseif ($muc_dat_ck === '3') $muc_dat_ck = 'C';

                    // Ép kiểu điểm số về INT như giải thuật yêu cầu
                    $diem_ck = trim($row['diem_ck'] ?? '');
                    if ($diem_ck !== '') {
                        if (!is_numeric($diem_ck)) {
                            $danh_sach_loi[] = "Học sinh {$ma_hs}: Điểm số chứa ký tự không hợp lệ.";
                            continue;
                        }
                        $diem_ck = (int) round((float)$diem_ck); // Làm tròn triệt để
                        if ($diem_ck < 0 || $diem_ck > 10) {
                            $danh_sach_loi[] = "Học sinh {$ma_hs}: Điểm số phải nằm trong khoảng 0 đến 10.";
                            continue;
                        }
                    } else {
                        $diem_ck = null;
                    }

                    // Kiểm tra điểm chữ Mức đạt GK và CK
                    if ($muc_dat_gk !== '' && !in_array($muc_dat_gk, ['T', 'H', 'C'])) {
                        $danh_sach_loi[] = "Học sinh {$ma_hs}: Mức đạt GK không hợp lệ (Chỉ nhận T, H, C hoặc 1, 2, 3).";
                        continue;
                    }
                    if ($muc_dat_ck !== '' && !in_array($muc_dat_ck, ['T', 'H', 'C'])) {
                        $danh_sach_loi[] = "Học sinh {$ma_hs}: Mức đạt CK không hợp lệ (Chỉ nhận T, H, C hoặc 1, 2, 3).";
                        continue;
                    }

                    $nx_ck = trim($row['nx_ck'] ?? '');

                    // Cập nhật CSDL
                    $sql_update = "
                        INSERT INTO danh_gia_mon_hoc (ma_hoc_sinh, ma_mon_hoc, hoc_ky, ma_nam, diem_giua_ky, muc_dat_cuoi_ky, diem_cuoi_ky, nhan_xet_cuoi_ky, ngay_cap_nhat)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                        ON DUPLICATE KEY UPDATE 
                        diem_giua_ky = VALUES(diem_giua_ky),
                        muc_dat_cuoi_ky = VALUES(muc_dat_cuoi_ky),
                        diem_cuoi_ky = VALUES(diem_cuoi_ky),
                        nhan_xet_cuoi_ky = VALUES(nhan_xet_cuoi_ky),
                        ngay_cap_nhat = CURRENT_TIMESTAMP
                    ";
                    $stmt_up = mysqli_prepare($conn, $sql_update);
                    mysqli_stmt_bind_param($stmt_up, "ssssssss", $ma_hs, $maMon, $hocKy, $namHoc, $muc_dat_gk, $muc_dat_ck, $diem_ck, $nx_ck);
                    mysqli_stmt_execute($stmt_up);
                    $so_dong_thanh_cong++;
                } 
                else { // PC hoặc NL
                    $valid_letters = ['T', 'Đ', 'C'];
                    $colCount = ($pageType === 'quality') ? 5 : 3;
                    $prefix = ($pageType === 'quality') ? 'pc' : 'nl';
                    $has_error = false;
                    
                    // CHUYỂN ĐỔI: 1 -> T, 2 -> Đ, 3 -> C
                    for ($i = 1; $i <= $colCount; $i++) {
                        if (isset($row["gk_{$prefix}{$i}"])) {
                            $val = strtoupper(trim($row["gk_{$prefix}{$i}"]));
                            if ($val === '1') $row["gk_{$prefix}{$i}"] = 'T';
                            elseif ($val === '2' || $val === 'D') $row["gk_{$prefix}{$i}"] = 'Đ';
                            elseif ($val === '3') $row["gk_{$prefix}{$i}"] = 'C';
                        }
                        if (isset($row["ck_{$prefix}{$i}"])) {
                            $val = strtoupper(trim($row["ck_{$prefix}{$i}"]));
                            if ($val === '1') $row["ck_{$prefix}{$i}"] = 'T';
                            elseif ($val === '2' || $val === 'D') $row["ck_{$prefix}{$i}"] = 'Đ';
                            elseif ($val === '3') $row["ck_{$prefix}{$i}"] = 'C';
                        }
                    }

                    // Kiểm tra lỗi
                    for ($i = 1; $i <= $colCount; $i++) {
                        $val_to_save = strtoupper(trim($row["ck_{$prefix}{$i}"] ?? '')); 
                        if ($val_to_save === '') $val_to_save = strtoupper(trim($row["gk_{$prefix}{$i}"] ?? ''));
                        
                        if ($val_to_save !== '' && !in_array($val_to_save, $valid_letters)) {
                            $danh_sach_loi[] = "Học sinh {$ma_hs}: Đánh giá " . strtoupper($prefix) . "{$i} không hợp lệ (Chỉ nhận T, Đ, C hoặc 1, 2, 3).";
                            $has_error = true;
                            break;
                        }
                    }

                    if (!$has_error) {
                        for ($i = 1; $i <= $colCount; $i++) {
                            $val_to_save = strtoupper(trim($row["ck_{$prefix}{$i}"] ?? '')); 
                            if ($val_to_save === '') $val_to_save = strtoupper(trim($row["gk_{$prefix}{$i}"] ?? ''));
                            
                            if ($val_to_save !== '') {
                                $ma_pcnl = strtoupper($prefix) . $i; 
                                $sql_pcnl = "
                                    INSERT INTO danh_gia_pcnl (ma_hoc_sinh, hoc_ky, ma_nam, ma_pcnl, muc_dat, nhan_xet)
                                    VALUES (?, ?, ?, ?, ?, '')
                                    ON DUPLICATE KEY UPDATE 
                                    muc_dat = VALUES(muc_dat)
                                ";
                                $stmt_pcnl = mysqli_prepare($conn, $sql_pcnl);
                                mysqli_stmt_bind_param($stmt_pcnl, "sssss", $ma_hs, $hocKy, $namHoc, $ma_pcnl, $val_to_save);
                                mysqli_stmt_execute($stmt_pcnl);
                            }
                        }
                        $so_dong_thanh_cong++;
                    }
                }
            }

            if (count($danh_sach_loi) > 0) {
                mysqli_rollback($conn);
                $err_msg = ($action === 'import_excel') ? "Import thất bại. Vui lòng sửa các dòng bị lỗi:\n" : "Lưu thất bại do có dữ liệu sai:\n";
                echo json_encode(['status' => 'error', 'message' => $err_msg . implode("\n", $danh_sach_loi)]);
            } else {
                mysqli_commit($conn);
                $msg = ($action === 'import_excel') ? "Import thành công {$so_dong_thanh_cong} dòng." : "Cập nhật dữ liệu thành công.";
                echo json_encode(['status' => 'success', 'message' => $msg]);
            }

        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu dữ liệu: ' . $e->getMessage()]);
        }
        exit;
    }
}
?>