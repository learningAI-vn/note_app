<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập — CraftNotes</title>
    <link rel="manifest" href="manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="craft-auth-bg d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="craft-auth-card">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="craft-icon-tile craft-tint-blue">
                <i class="bi bi-journal-richtext fs-4"></i>
            </div>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">CraftNotes</h1>
                <p class="text-secondary small mb-0">Không gian ghi chú cá nhân của bạn</p>
            </div>
        </div>

        <div id="authAlert" class="alert alert-danger d-none rounded-3 py-2 small" role="alert"></div>

        <form id="loginForm" novalidate>
            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-secondary">Địa chỉ Email</label>
                <input type="email" id="email" name="email" class="form-control craft-input" placeholder="alice@tdtu.edu.vn" required autofocus>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-label small fw-semibold text-secondary">Mật khẩu</label>
                    <a href="forgot_password.php" class="small text-decoration-none fw-medium">Quên mật khẩu?</a>
                </div>
                <input type="password" id="password" name="password" class="form-control craft-input" placeholder="••••••••" required>
            </div>
            <button type="submit" id="submitBtn" class="btn btn-primary w-100 craft-btn-primary py-2 mt-2">
                <span>Đăng nhập</span>
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <span class="text-secondary small">Chưa có tài khoản?</span>
            <a href="register.php" class="small fw-semibold text-decoration-none ms-1">Đăng ký ngay</a>
        </div>
    </div>

    <script>
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const alertBox = document.getElementById('authAlert');
        const btn = document.getElementById('submitBtn');
        alertBox.classList.add('d-none');
        btn.disabled = true;

        try {
            const res = await fetch('api/auth.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: document.getElementById('email').value.trim(),
                    password: document.getElementById('password').value
                })
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                alertBox.textContent = data.error || 'Đăng nhập thất bại.';
                alertBox.classList.remove('d-none');
                btn.disabled = false;
                return;
            }
            window.location.href = 'index.php';
        } catch (err) {
            alertBox.textContent = 'Không thể kết nối máy chủ.';
            alertBox.classList.remove('d-none');
            btn.disabled = false;
        }
    });
    </script>
</body>
</html>
