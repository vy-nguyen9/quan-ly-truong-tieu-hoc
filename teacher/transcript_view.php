<?php
session_start();
if (!isset($_SESSION['role']) || !in_array(strtoupper($_SESSION['role']), ['GV', 'BGH'])) {
    header("Location: ../auth/login.php");
    exit();
}
require_once '../config/database.php';

$ma_gv = $_SESSION['user_id'];
$ma_hs = $_GET['hs'] ?? '';
$get_lop = $_GET['lop'] ?? '';

if (empty($ma_hs) || empty($get_lop)) {
    exit('Thiếu thông tin học sinh hoặc lớp.');
}

$lopData = explode('_', $get_lop);
if (count($lopData) < 4) exit('Dữ liệu lớp không hợp lệ.');
$khoi = $lopData[0];
$ma_nhom_lop = $lopData[1];
$stt = $lopData[2];
$ma_truong = $lopData[3];

$sql_nam = "SELECT ma_nam FROM nam_hoc WHERE CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1";
$result_nam = mysqli_query($conn, $sql_nam);
$namHoc = mysqli_fetch_assoc($result_nam)['ma_nam'] ?? '2026-2027';

// 1. Kiểm tra quyền và lấy thông tin GVCN
$sql_gvcn = "SELECT nd.ho, nd.ten, cb.chu_ky 
             FROM lop_hoc lh 
             JOIN nguoi_dung nd ON lh.chu_nhiem = nd.ma_nguoi_dung 
             LEFT JOIN can_bo cb ON lh.chu_nhiem = cb.ma_nguoi_dung 
             WHERE lh.ma_nam = ? AND lh.khoi = ? AND lh.ma_nhom_lop = ? AND lh.stt = ? AND lh.ma_truong = ?";
$stmt_gvcn = mysqli_prepare($conn, $sql_gvcn);
mysqli_stmt_bind_param($stmt_gvcn, "sssss", $namHoc, $khoi, $ma_nhom_lop, $stt, $ma_truong);
mysqli_stmt_execute($stmt_gvcn);
$res_gvcn = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_gvcn));
$ten_gvcn = $res_gvcn ? mb_strtoupper($res_gvcn['ho'] . ' ' . $res_gvcn['ten'], 'UTF-8') : 'GIÁO VIÊN CHỦ NHIỆM';
$chu_ky_gvcn = $res_gvcn['chu_ky'] ?? '';

// 2. Lấy thông tin học sinh
$sql_hs = "SELECT ho, ten, gioi_tinh, ngay_sinh, ma_dan_toc FROM nguoi_dung WHERE ma_nguoi_dung = ?";
$stmt_hs = mysqli_prepare($conn, $sql_hs);
mysqli_stmt_bind_param($stmt_hs, "s", $ma_hs);
mysqli_stmt_execute($stmt_hs);
$hs_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_hs));
if (!$hs_info) exit('Không tìm thấy học sinh.');

$ten_hs = mb_strtoupper($hs_info['ho'] . ' ' . $hs_info['ten'], 'UTF-8');
$ngay_sinh = $hs_info['ngay_sinh'] ? date('d/m/Y', strtotime($hs_info['ngay_sinh'])) : '';
$gioi_tinh = $hs_info['gioi_tinh'] ?? '';

$dan_toc = '';
$dia_chi = '';
if($hs_info['ma_dan_toc']) {
    $sql_dt = "SELECT ten_dan_toc FROM dan_toc WHERE ma_dan_toc = ?";
    $stmt_dt = mysqli_prepare($conn, $sql_dt);
    mysqli_stmt_bind_param($stmt_dt, "s", $hs_info['ma_dan_toc']);
    mysqli_stmt_execute($stmt_dt);
    $res_dt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dt));
    $dan_toc = $res_dt['ten_dan_toc'] ?? '';
}

$sql_dc = "SELECT thon_xom, so_nha_ten_duong FROM dia_chi_chi_tiet WHERE ma_nguoi_dung = ? LIMIT 1";
$stmt_dc = mysqli_prepare($conn, $sql_dc);
mysqli_stmt_bind_param($stmt_dc, "s", $ma_hs);
mysqli_stmt_execute($stmt_dc);
$res_dc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dc));
if($res_dc) $dia_chi = trim(($res_dc['so_nha_ten_duong'] ?? '') . ' ' . ($res_dc['thon_xom'] ?? ''));

$sql_truong = "SELECT ten_truong FROM truong WHERE ma_truong = ?";
$stmt_tr = mysqli_prepare($conn, $sql_truong);
mysqli_stmt_bind_param($stmt_tr, "s", $ma_truong);
mysqli_stmt_execute($stmt_tr);
$ten_truong = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tr))['ten_truong'] ?? '';

// 3. Lấy điểm Môn học (Kỳ 2)
$sql_mon = "SELECT mh.ten_mon, d.muc_dat_cuoi_ky, d.diem_cuoi_ky, d.nhan_xet_cuoi_ky 
            FROM mon_hoc mh 
            LEFT JOIN danh_gia_mon_hoc d ON mh.ma_mon = d.ma_mon_hoc AND d.ma_hoc_sinh = ? AND d.ma_nam = ? AND d.hoc_ky = 'HK2'
            ORDER BY mh.ten_mon";
$stmt_mon = mysqli_prepare($conn, $sql_mon);
mysqli_stmt_bind_param($stmt_mon, "ss", $ma_hs, $namHoc);
mysqli_stmt_execute($stmt_mon);
$list_mon = mysqli_fetch_all(mysqli_stmt_get_result($stmt_mon), MYSQLI_ASSOC);

// 4. Lấy Đánh giá PCNL (Kỳ 2)
$sql_pcnl = "SELECT p.ten_pcnl, p.ma_pcnl, d.muc_dat, d.nhan_xet 
             FROM pham_chat_nang_luc p 
             LEFT JOIN danh_gia_pcnl d ON p.ma_pcnl = d.ma_pcnl AND d.ma_hoc_sinh = ? AND d.ma_nam = ? AND d.hoc_ky = 'HK2'
             ORDER BY p.ma_pcnl";
$stmt_pcnl = mysqli_prepare($conn, $sql_pcnl);
mysqli_stmt_bind_param($stmt_pcnl, "ss", $ma_hs, $namHoc);
mysqli_stmt_execute($stmt_pcnl);
$list_pcnl = mysqli_fetch_all(mysqli_stmt_get_result($stmt_pcnl), MYSQLI_ASSOC);

$ds_pc = []; $ds_nl = [];
foreach($list_pcnl as $p) {
    if(in_array($p['ma_pcnl'], ['PCNL01', 'PCNL02', 'PCNL03', 'PCNL04', 'PCNL05'])) $ds_pc[] = $p;
    elseif(in_array($p['ma_pcnl'], ['PCNL06', 'PCNL07', 'PCNL08'])) $ds_nl[] = $p;
}

// 5. Lấy trạng thái Học bạ
$sql_hb = "SELECT danh_hieu, duoc_len_lop, da_chot FROM hoc_ba WHERE ma_hoc_sinh = ? AND ma_nam = ?";
$stmt_hb = mysqli_prepare($conn, $sql_hb);
mysqli_stmt_bind_param($stmt_hb, "ss", $ma_hs, $namHoc);
mysqli_stmt_execute($stmt_hb);
$hb_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_hb));

$da_chot = $hb_info['da_chot'] ?? 0;
$danh_hieu = $hb_info['danh_hieu'] ?? '';

// Bắt lỗi null an toàn cho PHP 8+
$duoc_len_lop = $hb_info['duoc_len_lop'] ?? null;
$hoan_thanh_ct = ($duoc_len_lop == 1) ? 'Hoàn thành chương trình lớp học' : (($duoc_len_lop === 0 || $duoc_len_lop === '0') ? 'Chưa hoàn thành chương trình lớp học' : '');
$ket_qua_gd = $da_chot ? ($hoan_thanh_ct ? 'Hoàn thành' : 'Chưa hoàn thành') : '';

function getTenMucDat($val) {
    if ($val === 'T') return 'Tốt';
    if ($val === 'Đ' || $val === 'H') return 'Đạt';
    if ($val === 'C') return 'Cần cố gắng';
    return '';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Học Bạ - <?= htmlspecialchars($ten_hs) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/transcript_print.css">
</head>
<body>
    <div class="no-print print-controls">
        <button onclick="window.print()" class="btn-print"><i class="fa-solid fa-print"></i> In Học Bạ</button>
        <button onclick="window.close()" class="btn-close"><i class="fa-solid fa-xmark"></i> Đóng</button>
    </div>

    <!-- TRANG 1: TRANG BÌA -->
    <div class="a4-page cover-page">
        <div class="header">
            <h4>BỘ GIÁO DỤC VÀ ĐÀO TẠO</h4>
        </div>
        <div class="title-container">
            <h1>HỌC BẠ</h1>
        </div>
        <div class="student-info">
            <p><strong>Họ và tên học sinh:</strong> <span class="fw-bold fs-large"><?= htmlspecialchars($ten_hs) ?></span></p>
            <p><strong>Lớp:</strong> <?= htmlspecialchars($khoi . '/' . $stt) ?></p>
            <p><strong>Trường:</strong> <?= htmlspecialchars($ten_truong) ?></p>
            <p><strong>Năm học:</strong> <?= htmlspecialchars($namHoc) ?></p>
        </div>
    </div>

    <!-- TRANG 2: NỘI DUNG -->
    <div class="a4-page content-page">
        <h3 class="page-title">HỌC BẠ</h3>
        
        <div class="info-row">
            <div class="info-col-7"><strong>Họ và tên:</strong> <?= htmlspecialchars($ten_hs) ?></div>
            <div class="info-col-5"><strong>Giới tính:</strong> <?= htmlspecialchars($gioi_tinh) ?></div>
        </div>
        <div class="info-row">
            <div class="info-col-7"><strong>Ngày sinh:</strong> <?= htmlspecialchars($ngay_sinh) ?></div>
            <div class="info-col-5"><strong>Dân tộc:</strong> <?= htmlspecialchars($dan_toc) ?></div>
        </div>
        <div class="info-row"><div class="info-col-12"><strong>Nơi sinh:</strong> </div></div>
        <div class="info-row"><div class="info-col-12"><strong>Địa chỉ:</strong> <?= htmlspecialchars($dia_chi) ?></div></div>

        <!-- 1. BẢNG MÔN HỌC -->
        <div class="section">
            <div class="section-title">1. KẾT QUẢ CÁC MÔN HỌC VÀ HOẠT ĐỘNG GIÁO DỤC</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">Môn học và hoạt động giáo dục</th>
                        <th style="width: 15%;">Mức đạt được</th>
                        <th style="width: 12%;">Điểm cuối kỳ</th>
                        <th style="width: 38%;">Nhận xét</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list_mon as $m): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($m['ten_mon']) ?></td>
                            <td class="text-center"><?= getTenMucDat($m['muc_dat_cuoi_ky']) ?></td>
                            <td class="text-center fw-bold"><?= $m['diem_cuoi_ky'] !== null ? round($m['diem_cuoi_ky']) : '' ?></td>
                            <td class="text-left"><?= htmlspecialchars($m['nhan_xet_cuoi_ky'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. BẢNG PHẨM CHẤT -->
        <div class="section">
            <div class="section-title">2. NHẬN XÉT NHỮNG PHẨM CHẤT CHỦ YẾU</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">Phẩm chất</th>
                        <th style="width: 15%;">Mức đạt được</th>
                        <th style="width: 50%;">Nhận xét</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $first = true; foreach ($ds_pc as $pc): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($pc['ten_pcnl']) ?></td>
                            <td class="text-center"><?= getTenMucDat($pc['muc_dat']) ?></td>
                            <?php if ($first): ?>
                                <td rowspan="<?= count($ds_pc) ?>" class="text-left align-top"><?= htmlspecialchars($pc['nhan_xet'] ?? '') ?></td>
                                <?php $first = false; ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 3. BẢNG NĂNG LỰC -->
        <div class="section">
            <div class="section-title">3. NHỮNG NĂNG LỰC CỐT LÕI</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">Năng lực chung</th>
                        <th style="width: 15%;">Mức đạt được</th>
                        <th style="width: 50%;">Nhận xét</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $first = true; foreach ($ds_nl as $nl): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($nl['ten_pcnl']) ?></td>
                            <td class="text-center"><?= getTenMucDat($nl['muc_dat']) ?></td>
                            <?php if ($first): ?>
                                <td rowspan="<?= count($ds_nl) ?>" class="text-left align-top"><?= htmlspecialchars($nl['nhan_xet'] ?? '') ?></td>
                                <?php $first = false; ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- TỔNG HỢP & CHỮ KÝ -->
        <div class="summary-section">
            <p><strong>4. Đánh giá kết quả giáo dục:</strong> <?= htmlspecialchars($ket_qua_gd) ?></p>
            <p><strong>5. Khen thưởng:</strong> <?= htmlspecialchars($danh_hieu) ?></p>
            <p><strong>6. Hoàn thành chương trình lớp học:</strong> <?= htmlspecialchars($hoan_thanh_ct) ?></p>
        </div>

        <div class="signature-section">
            <div class="signature-box right">
                <p class="date-italic">Ninh Kiều, ngày <?= date('d') ?> tháng <?= date('m') ?> năm <?= date('Y') ?></p>
                <p class="role-title">GIÁO VIÊN CHỦ NHIỆM</p>
                
                <div class="signature-img-wrapper">
                    <?php if ($da_chot && !empty($chu_ky_gvcn)): ?>
                        <img src="../<?= htmlspecialchars($chu_ky_gvcn) ?>" alt="Chữ ký">
                    <?php else: ?>
                        <span class="unsigned-text">(Chưa ký chốt)</span>
                    <?php endif; ?>
                </div>

                <p class="teacher-name"><?= htmlspecialchars($ten_gvcn) ?></p>
            </div>
        </div>
    </div>
</body>
</html>