<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getUserId(): ?string
{
    return isset($_SESSION['user_id'])
        ? (string) $_SESSION['user_id']
        : null;
}

function getMaNguoiDung(): ?string
{
    return isset($_SESSION['ma_nguoi_dung'])
        ? (string) $_SESSION['ma_nguoi_dung']
        : null;
}

function getMaVaiTro(): ?string
{
    return isset($_SESSION['ma_vai_tro'])
        ? (string) $_SESSION['ma_vai_tro']
        : null;
}

function getMaTruong(): ?string
{
    return isset($_SESSION['ma_truong'])
        ? (string) $_SESSION['ma_truong']
        : null;
}

function getHoTen(): string
{
    return (string) ($_SESSION['ho_ten'] ?? '');
}

function isLoggedIn(): bool
{
    // Đã đăng nhập khi có đủ user_id và ma_nguoi_dung
    return !empty($_SESSION['user_id']) && !empty($_SESSION['ma_nguoi_dung']);
}

function logoutUser(): void
{
    session_unset();
    session_destroy();
}