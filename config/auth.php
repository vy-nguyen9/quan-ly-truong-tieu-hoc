<?php

require_once __DIR__ . '/session.php';

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function requireRole($roles): void
{
    requireLogin();

    $currentRole = getMaVaiTro();

    // Nếu truyền vào mảng nhiều vai trò, kiểm tra xem vai trò hiện tại có nằm trong mảng không
    if (is_array($roles)) {
        if (!in_array($currentRole, $roles, true)) {
            http_response_code(403);
            exit('Bạn không có quyền truy cập chức năng này.');
        }
    } 
    // Nếu chỉ truyền 1 chuỗi vai trò
    else {
        if ($currentRole !== $roles) {
            http_response_code(403);
            exit('Bạn không có quyền truy cập chức năng này.');
        }
    }
}

function requireAdmin(): void
{
    requireRole('admin');
}

function requireBGH(): void
{
    requireRole('BGH');
}


function requireTeacher(): void
{
    requireRole('GV');
}

function requireStudent(): void
{
    requireRole('HS');
}

function requireStaff(): void
{
    requireRole(['BGH', 'GV', 'NV']);
}