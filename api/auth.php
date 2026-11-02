<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail_helper.php';

$action = $_GET['action'] ?? '';
$input  = readJsonInput();
$pdo    = getPDO();

if ($action === 'register') {
    $email           = trim((string)($input['email'] ?? ''));
    $displayName     = trim((string)($input['display_name'] ?? ''));
    $password        = (string)($input['password'] ?? '');
    $confirmPassword = (string)($input['confirm_password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['ok' => false, 'error' => 'Địa chỉ email không hợp lệ.'], 400);
    }
    if ($displayName === '' || mb_strlen($displayName) > 100) {
        jsonResponse(['ok' => false, 'error' => 'Vui lòng nhập tên hiển thị hợp lệ (tối đa 100 ký tự).'], 400);
    }
    if (strlen($password) < 6) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu phải có ít nhất 6 ký tự.'], 400);
    }
    if ($password !== $confirmPassword) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu xác nhận không khớp.'], 400);
    }

    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Email này đã được đăng ký.'], 400);
    }

    $passwordHash    = password_hash($password, PASSWORD_BCRYPT);
    $activationToken = bin2hex(random_bytes(16));

    $insertStmt = $pdo->prepare(
        'INSERT INTO users (email, display_name, password_hash, is_activated, activation_token)
         VALUES (?, ?, ?, 0, ?)'
    );
    $insertStmt->execute([$email, $displayName, $passwordHash, $activationToken]);
    $userId = (int)$pdo->lastInsertId();

    // Auto-login immediately after registration (Mandatory Red-Highlighted Requirement)
    if (session_status() === PHP_SESSION_ACTIVE && getenv('APP_TEST_MODE') !== '1') {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $userId;

    $activationLink = 'activate.php?token=' . urlencode($activationToken);
    $mailHtml = '<p>Xin chào <strong>' . htmlspecialchars($displayName) . '</strong>,</p>'
        . '<p>Cảm ơn bạn đã đăng ký tài khoản Quản lý Ghi chú. Vui lòng nhấn vào liên kết bên dưới để kích hoạt tài khoản của bạn:</p>'
        . '<p><a class="btn" href="../../' . htmlspecialchars($activationLink) . '">Kích hoạt tài khoản ngay</a></p>'
        . '<p>Hoặc truy cập đường dẫn: <code>' . htmlspecialchars($activationLink) . '</code></p>';

    $mailPreview = sendAppMail($email, 'Kích hoạt tài khoản NoteApp của bạn', $mailHtml);

    jsonResponse([
        'ok'                      => true,
        'message'                 => 'Đăng ký thành công! Hệ thống đã tự động đăng nhập và gửi email kích hoạt.',
        'activation_link'         => $activationLink,
        'activation_mail_preview' => $mailPreview,
        'user'                    => [
            'id'           => $userId,
            'email'        => $email,
            'display_name' => $displayName,
            'is_activated' => 0,
        ],
    ]);
}

if ($action === 'login') {
    $email    = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($email === '' || $password === '') {
        jsonResponse(['ok' => false, 'error' => 'Vui lòng nhập đầy đủ email và mật khẩu.'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['ok' => false, 'error' => 'Email hoặc mật khẩu không chính xác.'], 401);
    }

    if (session_status() === PHP_SESSION_ACTIVE && getenv('APP_TEST_MODE') !== '1') {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['unlocked_notes'] = [];

    jsonResponse([
        'ok'   => true,
        'user' => [
            'id'           => (int)$user['id'],
            'email'        => $user['email'],
            'display_name' => $user['display_name'],
            'is_activated' => (int)$user['is_activated'],
        ],
    ]);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    jsonResponse(['ok' => true]);
}

if ($action === 'forgot_password') {
    $email = trim((string)($input['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['ok' => false, 'error' => 'Vui lòng nhập địa chỉ email hợp lệ.'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy tài khoản với địa chỉ email này.'], 404);
    }

    $otpToken  = (string)random_int(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 minutes

    $upd = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?');
    $upd->execute([$otpToken, $expiresAt, (int)$user['id']]);

    $resetLink = 'forgot_password.php?email=' . urlencode($email) . '&token=' . urlencode($otpToken);
    $mailHtml  = '<p>Xin chào <strong>' . htmlspecialchars($user['display_name']) . '</strong>,</p>'
        . '<p>Mã OTP khôi phục mật khẩu của bạn là: <strong style="font-size:20px;color:#2563eb;letter-spacing:2px">' . $otpToken . '</strong> (có hiệu lực trong 15 phút).</p>'
        . '<p>Hoặc nhấn trực tiếp vào liên kết: <a class="btn" href="../../' . htmlspecialchars($resetLink) . '">Đặt lại mật khẩu</a></p>';

    $mailPreview = sendAppMail($email, 'Khôi phục mật khẩu NoteApp (Mã OTP: ' . $otpToken . ')', $mailHtml);

    jsonResponse([
        'ok'           => true,
        'message'      => 'Đã gửi mã OTP và liên kết đặt lại mật khẩu tới email của bạn.',
        'reset_link'   => $resetLink,
        'mail_preview' => $mailPreview,
    ]);
}

if ($action === 'reset_password') {
    $email           = trim((string)($input['email'] ?? ''));
    $token           = trim((string)($input['token'] ?? ''));
    $newPassword     = (string)($input['new_password'] ?? '');
    $confirmPassword = (string)($input['confirm_password'] ?? '');

    if ($email === '' || $token === '') {
        jsonResponse(['ok' => false, 'error' => 'Thiếu thông tin email hoặc mã xác thực OTP/Token.'], 400);
    }
    if (strlen($newPassword) < 6) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu mới phải có ít nhất 6 ký tự.'], 400);
    }
    if ($newPassword !== $confirmPassword) {
        jsonResponse(['ok' => false, 'error' => 'Xác nhận mật khẩu mới không khớp.'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || empty($user['reset_token']) || !hash_equals((string)$user['reset_token'], $token)) {
        jsonResponse(['ok' => false, 'error' => 'Mã OTP hoặc liên kết khôi phục không hợp lệ.'], 400);
    }
    if (!empty($user['reset_expires_at']) && strtotime((string)$user['reset_expires_at']) < time()) {
        jsonResponse(['ok' => false, 'error' => 'Mã khôi phục đã hết hạn. Vui lòng yêu cầu mã mới.'], 400);
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $upd = $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?');
    $upd->execute([$newHash, (int)$user['id']]);

    // Following a password reset, users are required to log in manually
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    jsonResponse([
        'ok'      => true,
        'message' => 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập lại bằng mật khẩu mới.',
    ]);
}

jsonResponse(['ok' => false, 'error' => 'Hành động không hợp lệ.'], 400);
