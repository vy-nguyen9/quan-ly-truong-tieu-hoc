<?php

// $conn = new mysqli(
//     "100.113.218.15",
//     "b2404930",
//     "ct252e_b2404930",
//     "ct252e_quan_ly_truong_tieu_hoc",
//     3306
// );

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "ct252e_htqtth",
    3306
);


if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// else die("Thành công!");

$conn->set_charset("utf8mb4");
?>