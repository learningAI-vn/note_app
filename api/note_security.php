<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$userId = requireAuthApi();
$pdo    = getPDO();
$action = $_GET['action'] ?? '';
$input  = readJsonInput();

$noteId = (int)($input['note_id'] ?? ($_GET['note_id'] ?? 0));
$stmt   = $pdo->prepare('SELECT * FROM notes WHERE id = ?');
$stmt->execute([$noteId]);
$note = $stmt->fetch();

if (!$note) {
    jsonResponse(['ok' => false, 'error' => 'Không tìm thấy ghi chú.'], 404);
}

$isOwner = ((int)$note['user_id'] === $userId);

// 1. Set a password on a standard note (Better Approach: requires entering password twice)
if ($action === 'set_password') {
    if (!$isOwner) {
        jsonResponse(['ok' => false, 'error' => 'Chỉ chủ sở hữu mới được đặt mật khẩu cho ghi chú.'], 403);
    }
    if (!empty($note['password_hash'])) {
        jsonResponse(['ok' => false, 'error' => 'Ghi chú này đã có mật khẩu. Vui lòng dùng chức năng Đổi mật khẩu.'], 400);
    }

    $password        = (string)($input['password'] ?? '');
    $confirmPassword = (string)($input['confirm_password'] ?? '');

    if (strlen($password) < 4) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu ghi chú phải có ít nhất 4 ký tự.'], 400);
    }
    if ($password !== $confirmPassword) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu xác nhận không khớp.'], 400);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $upd  = $pdo->prepare('UPDATE notes SET password_hash = ? WHERE id = ?');
    $upd->execute([$hash, $noteId]);

    $_SESSION['unlocked_notes'][$noteId] = true;

    jsonResponse([
        'ok'      => true,
        'message' => 'Đã bật bảo vệ mật khẩu cho ghi chú.',
    ]);
}

// 2. Verify password to unlock note for viewing / editing / deleting
if ($action === 'verify_password') {
    if (!$isOwner) {
        $shStmt = $pdo->prepare('SELECT id FROM note_shares WHERE note_id = ? AND recipient_id = ?');
        $shStmt->execute([$noteId, $userId]);
        if (!$shStmt->fetchColumn()) {
            jsonResponse(['ok' => false, 'error' => 'Bạn không có quyền truy cập ghi chú này.'], 403);
        }
    }

    $password = (string)($input['password'] ?? '');
    if (empty($note['password_hash'])) {
        jsonResponse(['ok' => false, 'error' => 'Ghi chú này không có mật khẩu.'], 400);
    }
    if (!password_verify($password, (string)$note['password_hash'])) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu ghi chú không chính xác.'], 401);
    }

    $_SESSION['unlocked_notes'][$noteId] = true;

    // Return full unlocked note content and attachments
    $lblStmt = $pdo->prepare(
        'SELECT l.id, l.name FROM labels l
         INNER JOIN note_labels nl ON nl.label_id = l.id
         WHERE nl.note_id = ? ORDER BY l.name ASC'
    );
    $lblStmt->execute([$noteId]);

    $attStmt = $pdo->prepare('SELECT * FROM note_attachments WHERE note_id = ? ORDER BY id ASC');
    $attStmt->execute([$noteId]);

    jsonResponse([
        'ok'   => true,
        'note' => [
            'id'          => $noteId,
            'user_id'     => (int)$note['user_id'],
            'is_owner'    => $isOwner,
            'title'       => (string)$note['title'],
            'content'     => (string)$note['content'],
            'color'       => (string)($note['color'] ?: '#ffffff'),
            'is_pinned'   => (int)$note['is_pinned'],
            'is_locked'   => true,
            'is_unlocked' => true,
            'labels'      => $lblStmt->fetchAll(),
            'attachments' => $attStmt->fetchAll(),
            'updated_at'  => $note['updated_at'],
        ],
    ]);
}

// 3. Change note password (Better Approach: requires current password + new password twice)
if ($action === 'change_password') {
    if (!$isOwner) {
        jsonResponse(['ok' => false, 'error' => 'Chỉ chủ sở hữu mới được đổi mật khẩu ghi chú.'], 403);
    }
    if (empty($note['password_hash'])) {
        jsonResponse(['ok' => false, 'error' => 'Ghi chú chưa bật mật khẩu.'], 400);
    }

    $currentPassword = (string)($input['current_password'] ?? '');
    $newPassword     = (string)($input['new_password'] ?? '');
    $confirmPassword = (string)($input['confirm_password'] ?? '');

    if (!password_verify($currentPassword, (string)$note['password_hash'])) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu hiện tại của ghi chú không đúng.'], 400);
    }
    if (strlen($newPassword) < 4) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu mới phải có ít nhất 4 ký tự.'], 400);
    }
    if ($newPassword !== $confirmPassword) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu mới nhập 2 lần không khớp.'], 400);
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $upd     = $pdo->prepare('UPDATE notes SET password_hash = ? WHERE id = ?');
    $upd->execute([$newHash, $noteId]);

    $_SESSION['unlocked_notes'][$noteId] = true;

    jsonResponse([
        'ok'      => true,
        'message' => 'Đã đổi mật khẩu bảo vệ ghi chú thành công.',
    ]);
}

// 4. Disable password protection (Better Approach: requires re-entering current password)
if ($action === 'disable_password') {
    if (!$isOwner) {
        jsonResponse(['ok' => false, 'error' => 'Chỉ chủ sở hữu mới được tắt mật khẩu ghi chú.'], 403);
    }
    if (empty($note['password_hash'])) {
        jsonResponse(['ok' => false, 'error' => 'Ghi chú hiện không có mật khẩu.'], 400);
    }

    $currentPassword = (string)($input['current_password'] ?? '');
    if (!password_verify($currentPassword, (string)$note['password_hash'])) {
        jsonResponse(['ok' => false, 'error' => 'Mật khẩu xác nhận không đúng. Không thể tắt bảo vệ.'], 400);
    }

    $upd = $pdo->prepare('UPDATE notes SET password_hash = NULL WHERE id = ?');
    $upd->execute([$noteId]);

    unset($_SESSION['unlocked_notes'][$noteId]);

    jsonResponse([
        'ok'      => true,
        'message' => 'Đã tắt bảo vệ mật khẩu cho ghi chú.',
    ]);
}

jsonResponse(['ok' => false, 'error' => 'Hành động bảo mật không hợp lệ.'], 400);
