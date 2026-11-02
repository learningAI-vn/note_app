<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

$prefillEmail = trim((string)($_GET['email'] ?? ''));
$prefillToken = trim((string)($_GET['token'] ?? ''));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khôi phục mật khẩu — CraftNotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="craft-auth-bg d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="craft-auth-card">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="craft-icon-tile craft-tint-amber">
                <i class="bi bi-key-fill fs-4"></i>
            </div>
            <div>
                <h1 class="h5 fw-bold mb-0 text-dark">Khôi phục mật khẩu</h1>
                <p class="text-secondary small mb-0">Nhận mã OTP hoặc liên kết qua email</p>
            </div>
        </div>

        <div id="fpAlert" class="alert d-none rounded-3 py-2 small" role="alert"></div>

        <!-- Step 1: Request OTP / Link -->
        <form id="requestOtpForm" class="<?= $prefillToken !== '' ? 'd-none' : '' ?>" novalidate>
            <div class="mb-3">
                <label for="reqEmail" class="form-label small fw-semibold text-secondary">Địa chỉ Email đã đăng ký</label>
                <input type="email" id="reqEmail" class="form-control craft-input" value="<?= htmlspecialchars($prefillEmail) ?>" placeholder="alice@tdtu.edu.vn" required>
            </div>
            <button type="submit" id="reqBtn" class="btn btn-primary w-100 craft-btn-primary py-2">
                Gửi mã OTP / Liên kết khôi phục
            </button>
            <div class="text-center mt-3">
                <button type="button" class="btn btn-link btn-sm text-decoration-none" onclick="showStep2()">Đã có mã OTP? Nhập mã ngay</button>
            </div>
        </form>

        <!-- Step 2: Enter OTP & New Password Twice -->
        <form id="resetPassForm" class="<?= $prefillToken !== '' ? '' : 'd-none' ?>" novalidate>
            <div class="mb-3">
                <label for="resetEmail" class="form-label small fw-semibold text-secondary">Email tài khoản</label>
                <input type="email" id="resetEmail" class="form-control craft-input" value="<?= htmlspecialchars($prefillEmail) ?>" required>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="resetToken" class="form-label small fw-semibold text-secondary">Mã OTP (6 chữ số)</label>
                    <a href="logs/mails/latest.html" target="_blank" class="small text-decoration-none"><i class="bi bi-envelope-open me-1"></i>Xem email OTP (Local)</a>
                </div>
                <input type="text" id="resetToken" class="form-control craft-input" value="<?= htmlspecialchars($prefillToken) ?>" placeholder="Nhập mã 6 số trong email" required>
            </div>
            <div class="mb-3">
                <label for="newPassword" class="form-label small fw-semibold text-secondary">Mật khẩu mới</label>
                <input type="password" id="newPassword" class="form-control craft-input" placeholder="Tối thiểu 6 ký tự" required>
            </div>
            <div class="mb-3">
                <label for="confirmNewPassword" class="form-label small fw-semibold text-secondary">Xác nhận mật khẩu mới</label>
                <input type="password" id="confirmNewPassword" class="form-control craft-input" placeholder="Nhập lại mật khẩu mới" required>
            </div>
            <button type="submit" id="resetBtn" class="btn btn-primary w-100 craft-btn-primary py-2">
                Đặt lại mật khẩu
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <a href="login.php" class="small fw-semibold text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Quay lại màn hình Đăng nhập</a>
        </div>
    </div>

    <script>
    function showAlert(msg, isSuccess = false) {
        const box = document.getElementById('fpAlert');
        box.className = `alert ${isSuccess ? 'alert-success' : 'alert-danger'} rounded-3 py-2 small`;
        box.innerHTML = msg;
        box.classList.remove('d-none');
    }

    function showStep2() {
        const reqEmail = document.getElementById('reqEmail').value.trim();
        if (reqEmail) document.getElementById('resetEmail').value = reqEmail;
        document.getElementById('requestOtpForm').classList.add('d-none');
        document.getElementById('resetPassForm').classList.remove('d-none');
    }

    document.getElementById('requestOtpForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('reqEmail').value.trim();
        const res = await fetch('api/auth.php?action=forgot_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            showAlert(data.error || 'Không thể gửi yêu cầu khôi phục.');
            return;
        }
        showAlert(`${data.message} <a href="${data.mail_preview}" target="_blank" class="fw-bold">Mở xem Email</a>`, true);
        showStep2();
    });

    document.getElementById('resetPassForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await fetch('api/auth.php?action=reset_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: document.getElementById('resetEmail').value.trim(),
                token: document.getElementById('resetToken').value.trim(),
                new_password: document.getElementById('newPassword').value,
                confirm_password: document.getElementById('confirmNewPassword').value
            })
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            showAlert(data.error || 'Không thể đặt lại mật khẩu.');
            return;
        }
        showAlert('Đặt lại mật khẩu thành công! Đang chuyển về trang đăng nhập...', true);
        setTimeout(() => { window.location.href = 'login.php'; }, 1200);
    });
    </script>
</body>
</html>
