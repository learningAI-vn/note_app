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
    <title>Đăng ký tài khoản — CraftNotes</title>
    <link rel="manifest" href="manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="craft-auth-bg d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="craft-auth-card">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="craft-icon-tile craft-tint-purple">
                <i class="bi bi-person-plus fs-4"></i>
            </div>
            <div>
                <h1 class="h4 fw-bold mb-0 text-dark">Tạo tài khoản mới</h1>
                <p class="text-secondary small mb-0">Đăng ký xong tự động đăng nhập ngay</p>
            </div>
        </div>

        <div id="regAlert" class="alert alert-danger d-none rounded-3 py-2 small" role="alert"></div>

        <!-- Strictly ONLY Email, Display Name, and Password x2 as required by red-highlighted specification -->
        <form id="registerForm" novalidate>
            <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-secondary">Địa chỉ Email</label>
                <input type="email" id="email" name="email" class="form-control craft-input" placeholder="you@tdtu.edu.vn" required autofocus>
            </div>
            <div class="mb-3">
                <label for="display_name" class="form-label small fw-semibold text-secondary">Tên hiển thị (Display Name)</label>
                <input type="text" id="display_name" name="display_name" class="form-control craft-input" placeholder="Nguyễn Văn A" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold text-secondary">Mật khẩu</label>
                <input type="password" id="password" name="password" class="form-control craft-input" placeholder="Tối thiểu 6 ký tự" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label small fw-semibold text-secondary">Xác nhận mật khẩu</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control craft-input" placeholder="Nhập lại mật khẩu" required>
            </div>
            <button type="submit" id="regBtn" class="btn btn-primary w-100 craft-btn-primary py-2 mt-2">
                <span>Đăng ký & Bắt đầu sử dụng</span>
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <span class="text-secondary small">Đã có tài khoản?</span>
            <a href="login.php" class="small fw-semibold text-decoration-none ms-1">Đăng nhập</a>
        </div>
    </div>

    <script>
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const alertBox = document.getElementById('regAlert');
        const btn = document.getElementById('regBtn');
        alertBox.classList.add('d-none');
        btn.disabled = true;

        try {
            const res = await fetch('api/auth.php?action=register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: document.getElementById('email').value.trim(),
                    display_name: document.getElementById('display_name').value.trim(),
                    password: document.getElementById('password').value,
                    confirm_password: document.getElementById('confirm_password').value
                })
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                alertBox.textContent = data.error || 'Đăng ký thất bại.';
                alertBox.classList.remove('d-none');
                btn.disabled = false;
                return;
            }
            // Automatically logged in -> redirect straight to personalized homepage
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
