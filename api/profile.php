<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$userId = requireAuthApi();
$pdo    = getPDO();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$input  = readJsonInput();

function formatUserProfile(array $user): array {
    return [
        'id'              => (int)$user['id'],
        'email'           => (string)$user['email'],
        'display_name'    => (string)$user['display_name'],
        'avatar_url'      => !empty($user['avatar']) ? (string)$user['avatar'] : 'assets/img/default-avatar.svg',
        'is_activated'    => (int)$user['is_activated'],
        'pref_font_size'  => (string)($user['pref_font_size'] ?: 'medium'),
        'pref_note_color' => (string)($user['pref_note_color'] ?: '#ffffff'),
        'pref_theme'      => (string)($user['pref_theme'] ?: 'light'),
    ];
}

function fetchCurrentUserRow(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy người dùng.'], 404);
    }
    return $row;
}

if ($method === 'GET') {
    $user = fetchCurrentUserRow($pdo, $userId);
    jsonResponse(['ok' => true, 'profile' => formatUserProfile($user)]);
}

if ($method === 'POST' && $action === 'update_profile') {
    $user        = fetchCurrentUserRow($pdo, $userId);
    $displayName = trim((string)($_POST['display_name'] ?? ($input['display_name'] ?? $user['display_name'])));

    if ($displayName === '' || mb_strlen($displayName) > 100) {
        jsonResponse(['ok' => false, 'error' => 'Tên hiển thị không hợp lệ.'], 400);
    }

    $avatarPath = $user['avatar'];

    if (!empty($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar'];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            jsonResponse(['ok' => false, 'error' => 'Lỗi tải lên tệp ảnh đại diện.'], 400);
        }

        $maxSize = 2 * 1024 * 1024; // 2MB
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
            jsonResponse(['ok' => false, 'error' => 'Kích thước ảnh đại diện tối đa là 2MB.'], 400);
        }

        $origName = (string)($file['name'] ?? '');
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExtensions, true)) {
            jsonResponse(['ok' => false, 'error' => 'Định dạng ảnh đại diện không hợp lệ. Chỉ chấp nhận JPG, PNG, GIF, WEBP.'], 400);
        }

        $tmpPath = (string)$file['tmp_name'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($tmpPath);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowedMimes, true)) {
            jsonResponse(['ok' => false, 'error' => 'Nội dung tệp không phải là hình ảnh hợp lệ.'], 400);
        }

        $uploadDir = dirname(__DIR__) . '/uploads/avatars';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $newFileName = 'avatar_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destFull    = $uploadDir . '/' . $newFileName;
        if (!move_uploaded_file($tmpPath, $destFull)) {
            // In CLI test mode move_uploaded_file returns false for local temp files; fall back to copy() only in APP_TEST_MODE
            if (getenv('APP_TEST_MODE') === '1') {
                copy($tmpPath, $destFull);
            } else {
                jsonResponse(['ok' => false, 'error' => 'Không thể lưu ảnh đại diện.'], 500);
            }
        }
        $avatarPath = 'uploads/avatars/' . $newFileName;
    }

    $upd = $pdo->prepare('UPDATE users SET display_name = ?, avatar = ? WHERE id = ?');
    $upd->execute([$displayName, $avatarPath, $userId]);

    $updatedUser = fetchCurrentUserRow($pdo, $userId);
    jsonResponse([
        'ok'      => true,
        'message' => 'Đã cập nhật hồ sơ thành công.',
        'profile' => formatUserProfile($updatedUser),
    ]);
}

if ($method === 'POST' && $action === 'change_password') {
    $user            = fetchCurrentUserRow($pdo, $userId);
    $currentPassword = (string)($input['current_password'] ?? '');
    $newPassword     = (string)($input['new_password'] ?? '');
    $confirmPassword = (string)($input['confirm_password'] ?? '');

    if (!password_verify($currentPassword, (string)$user['password_hash'])) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu hiện tại không chính xác.'], 400);
    }
    if (strlen($newPassword) < 6) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu mới phải có ít nhất 6 ký tự.'], 400);
    }
    if ($newPassword !== $confirmPassword) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu mới nhập 2 lần không khớp.'], 400);
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $upd     = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $upd->execute([$newHash, $userId]);

    // Securely regenerate authentication session ID
    if (session_status() === PHP_SESSION_ACTIVE && getenv('APP_TEST_MODE') !== '1') {
        session_regenerate_id(true);
    }

    jsonResponse([
        'ok'      => true,
        'message' => 'Đã đổi mật khẩu thành công và làm mới phiên bảo mật.',
    ]);
}

if ($method === 'POST' && $action === 'update_preferences') {
    $fontSize  = trim((string)($input['pref_font_size'] ?? 'medium'));
    $noteColor = trim((string)($input['pref_note_color'] ?? '#ffffff'));
    $theme     = trim((string)($input['pref_theme'] ?? 'light'));

    if (!in_array($fontSize, ['small', 'medium', 'large'], true)) {
        jsonResponse(['ok' => false, 'error' => 'Cỡ chữ không hợp lệ.'], 400);
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $noteColor)) {
        jsonResponse(['ok' => false, 'error' => 'Mã màu ghi chú không hợp lệ.'], 400);
    }
    if (!in_array($theme, ['light', 'dark'], true)) {
        jsonResponse(['ok' => false, 'error' => 'Giao diện sáng/tối không hợp lệ.'], 400);
    }

    $upd = $pdo->prepare('UPDATE users SET pref_font_size = ?, pref_note_color = ?, pref_theme = ? WHERE id = ?');
    $upd->execute([$fontSize, $noteColor, $theme, $userId]);

    $updatedUser = fetchCurrentUserRow($pdo, $userId);
    jsonResponse([
        'ok'      => true,
        'message' => 'Đã lưu tùy chọn người dùng.',
        'profile' => formatUserProfile($updatedUser),
    ]);
}

jsonResponse(['ok' => false, 'error' => 'Yêu cầu không hợp lệ.'], 400);
