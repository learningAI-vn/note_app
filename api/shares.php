<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail_helper.php';

$userId = requireAuthApi();
$pdo    = getPDO();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input  = readJsonInput();

// GET list of recipients for a note owned by current user (Better Approach: Detailed Share Overview)
if ($method === 'GET') {
    $noteId = (int)($_GET['note_id'] ?? 0);
    $nStmt  = $pdo->prepare('SELECT * FROM notes WHERE id = ? AND user_id = ?');
    $nStmt->execute([$noteId, $userId]);
    if (!$nStmt->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy ghi chú hoặc bạn không phải chủ sở hữu.'], 403);
    }

    $stmt = $pdo->prepare(
        'SELECT ns.id, ns.note_id, ns.recipient_id, ns.permission, ns.shared_at,
                u.email, u.display_name
         FROM note_shares ns
         INNER JOIN users u ON u.id = ns.recipient_id
         WHERE ns.note_id = ?
         ORDER BY ns.shared_at DESC'
    );
    $stmt->execute([$noteId]);

    jsonResponse(['ok' => true, 'shares' => $stmt->fetchAll()]);
}

// POST share a note with one or multiple registered emails
if ($method === 'POST') {
    $noteId     = (int)($input['note_id'] ?? 0);
    $permission = trim((string)($input['permission'] ?? 'read'));
    $rawEmails  = $input['emails'] ?? [];

    if (is_string($rawEmails)) {
        $rawEmails = array_filter(array_map('trim', preg_split('/[,;\s]+/', $rawEmails)));
    }
    if (!is_array($rawEmails) || empty($rawEmails)) {
        jsonResponse(['ok' => false, 'error' => 'Vui lòng nhập ít nhất một địa chỉ email người nhận.'], 400);
    }
    if (!in_array($permission, ['read', 'edit'], true)) {
        jsonResponse(['ok' => false, 'error' => 'Quyền chia sẻ không hợp lệ.'], 400);
    }

    $nStmt = $pdo->prepare('SELECT * FROM notes WHERE id = ? AND user_id = ?');
    $nStmt->execute([$noteId, $userId]);
    $note = $nStmt->fetch();
    if (!$note) {
        jsonResponse(['ok' => false, 'error' => 'Chỉ chủ sở hữu mới có quyền chia sẻ ghi chú này.'], 403);
    }

    // Validate all emails belong to registered users first (Better Approach Requirement)
    $uStmt      = $pdo->prepare('SELECT id, email, display_name FROM users WHERE email = ?');
    $recipients = [];
    foreach ($rawEmails as $email) {
        $email = trim((string)$email);
        if ($email === '') {
            continue;
        }
        $uStmt->execute([$email]);
        $targetUser = $uStmt->fetch();
        if (!$targetUser) {
            jsonResponse([
                'ok'    => false,
                'error' => "Email '{$email}' chưa đăng ký tài khoản trong hệ thống.",
            ], 400);
        }
        if ((int)$targetUser['id'] === $userId) {
            jsonResponse([
                'ok'    => false,
                'error' => 'Bạn không thể tự chia sẻ ghi chú cho chính mình.',
            ], 400);
        }
        $recipients[] = $targetUser;
    }

    $ownerStmt = $pdo->prepare('SELECT display_name, email FROM users WHERE id = ?');
    $ownerStmt->execute([$userId]);
    $owner = $ownerStmt->fetch();

    $now = date('Y-m-d H:i:s');
    foreach ($recipients as $recip) {
        $recipId = (int)$recip['id'];
        $chk     = $pdo->prepare('SELECT id FROM note_shares WHERE note_id = ? AND recipient_id = ?');
        $chk->execute([$noteId, $recipId]);
        $existingId = $chk->fetchColumn();

        if ($existingId) {
            $upd = $pdo->prepare('UPDATE note_shares SET permission = ?, shared_at = ?, is_notified = 0 WHERE id = ?');
            $upd->execute([$permission, $now, (int)$existingId]);
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO note_shares (note_id, owner_id, recipient_id, permission, is_notified, shared_at)
                 VALUES (?, ?, ?, ?, 0, ?)'
            );
            $ins->execute([$noteId, $userId, $recipId, $permission, $now]);
        }

        // Send notification email to recipient (Better Approach)
        $permText = $permission === 'edit' ? 'Chỉnh sửa (Edit)' : 'Chỉ đọc (Read-only)';
        $mailHtml = '<p>Xin chào <strong>' . htmlspecialchars($recip['display_name']) . '</strong>,</p>'
            . '<p><strong>' . htmlspecialchars($owner['display_name']) . ' (' . htmlspecialchars($owner['email']) . ')</strong> vừa chia sẻ ghi chú <strong>"' . htmlspecialchars($note['title'] ?: 'Không tiêu đề') . '"</strong> với bạn.</p>'
            . '<p>Quyền truy cập của bạn: <strong>' . $permText . '</strong></p>'
            . '<p><a class="btn" href="../../index.php">Mở mục "Được chia sẻ với tôi"</a></p>';
        sendAppMail($recip['email'], 'Ghi chú mới được chia sẻ với bạn trên CraftNotes', $mailHtml);
    }

    jsonResponse([
        'ok'      => true,
        'message' => 'Đã chia sẻ ghi chú và gửi email thông báo cho người nhận.',
    ]);
}

// PUT modify sharing permission for a recipient
if ($method === 'PUT') {
    $shareId    = (int)($input['share_id'] ?? 0);
    $permission = trim((string)($input['permission'] ?? 'read'));

    if (!in_array($permission, ['read', 'edit'], true)) {
        jsonResponse(['ok' => false, 'error' => 'Quyền chia sẻ không hợp lệ.'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM note_shares WHERE id = ? AND owner_id = ?');
    $stmt->execute([$shareId, $userId]);
    if (!$stmt->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy bản ghi chia sẻ hoặc bạn không có quyền.'], 403);
    }

    $upd = $pdo->prepare('UPDATE note_shares SET permission = ? WHERE id = ?');
    $upd->execute([$permission, $shareId]);

    jsonResponse(['ok' => true, 'message' => 'Đã cập nhật quyền người nhận.']);
}

// DELETE revoke sharing access
if ($method === 'DELETE') {
    $shareId = (int)($input['share_id'] ?? ($_GET['share_id'] ?? 0));
    $stmt    = $pdo->prepare('SELECT * FROM note_shares WHERE id = ? AND owner_id = ?');
    $stmt->execute([$shareId, $userId]);
    if (!$stmt->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy bản ghi chia sẻ hoặc bạn không có quyền.'], 403);
    }

    $del = $pdo->prepare('DELETE FROM note_shares WHERE id = ?');
    $del->execute([$shareId]);

    jsonResponse(['ok' => true, 'message' => 'Đã thu hồi quyền truy cập ghi chú.']);
}

jsonResponse(['ok' => false, 'error' => 'Phương thức không hợp lệ.'], 405);
