<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$userId = requireAuthApi();
$pdo    = getPDO();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$input  = readJsonInput();

/**
 * Helper: Hydrates a note row with its labels, attachments, share count, and lock status,
 * masking content & attachments if the note is password-protected and not unlocked in session.
 */
function hydrateNoteRow(PDO $pdo, array $row, int $currentUserId): array {
    $noteId     = (int)$row['id'];
    $hasPass    = !empty($row['password_hash']);
    $isUnlocked = !$hasPass || !empty($_SESSION['unlocked_notes'][$noteId]);

    // Fetch attached labels
    $lblStmt = $pdo->prepare(
        'SELECT l.id, l.name
         FROM labels l
         INNER JOIN note_labels nl ON nl.label_id = l.id
         WHERE nl.note_id = ?
         ORDER BY l.name ASC'
    );
    $lblStmt->execute([$noteId]);
    $labels = $lblStmt->fetchAll();

    // Fetch attachments
    $attStmt = $pdo->prepare(
        'SELECT id, note_id, file_name, file_path, file_type, mime_type, file_size, created_at
         FROM note_attachments
         WHERE note_id = ?
         ORDER BY id ASC'
    );
    $attStmt->execute([$noteId]);
    $allAttachments = $attStmt->fetchAll();

    // Fetch share info
    $shStmt = $pdo->prepare('SELECT COUNT(*) FROM note_shares WHERE note_id = ?');
    $shStmt->execute([$noteId]);
    $shareCount = (int)$shStmt->fetchColumn();

    return [
        'id'                => $noteId,
        'user_id'           => (int)$row['user_id'],
        'is_owner'          => ((int)$row['user_id'] === $currentUserId),
        'title'             => (string)$row['title'],
        'content'           => $isUnlocked ? (string)$row['content'] : '',
        'color'             => (string)($row['color'] ?: '#ffffff'),
        'is_pinned'         => (int)$row['is_pinned'],
        'pinned_at'         => $row['pinned_at'],
        'is_locked'         => $hasPass,
        'is_unlocked'       => $isUnlocked,
        'is_shared'         => ($shareCount > 0 || isset($row['shared_permission'])),
        'share_count'       => $shareCount,
        'shared_permission' => $row['shared_permission'] ?? (((int)$row['user_id'] === $currentUserId) ? 'owner' : 'read'),
        'owner_name'        => $row['owner_name'] ?? null,
        'owner_email'       => $row['owner_email'] ?? null,
        'shared_at'         => $row['shared_at'] ?? null,
        'labels'            => $labels,
        'attachments'       => $isUnlocked ? $allAttachments : [],
        'attachment_count'  => count($allAttachments),
        'created_at'        => $row['created_at'],
        'updated_at'        => $row['updated_at'],
    ];
}

// POST action=toggle_pin
if ($method === 'POST' && $action === 'toggle_pin') {
    $noteId = (int)($input['id'] ?? 0);
    $stmt   = $pdo->prepare('SELECT * FROM notes WHERE id = ?');
    $stmt->execute([$noteId]);
    $note = $stmt->fetch();

    if (!$note) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy ghi chú.'], 404);
    }
    if ((int)$note['user_id'] !== $userId) {
        jsonResponse(['ok' => false, 'error' => 'Chỉ chủ sở hữu mới được ghim ghi chú.'], 403);
    }
    if (!empty($note['password_hash']) && empty($_SESSION['unlocked_notes'][$noteId])) {
        jsonResponse(['ok' => false, 'error' => 'Vui lòng nhập mật khẩu mở khóa ghi chú trước.'], 403);
    }

    $newPin   = ((int)$note['is_pinned'] === 1) ? 0 : 1;
    $pinnedAt = $newPin === 1 ? date('Y-m-d H:i:s') : null;

    $upd = $pdo->prepare('UPDATE notes SET is_pinned = ?, pinned_at = ? WHERE id = ?');
    $upd->execute([$newPin, $pinnedAt, $noteId]);

    $stmt->execute([$noteId]);
    $updated = $stmt->fetch();
    jsonResponse(['ok' => true, 'note' => hydrateNoteRow($pdo, $updated, $userId)]);
}

// GET list of notes (my notes or shared with me)
if ($method === 'GET') {
    $view      = $_GET['view'] ?? 'all';
    $q         = trim((string)($_GET['q'] ?? ''));
    $labelsCsv = trim((string)($_GET['labels'] ?? ''));
    $labelIds  = array_values(array_filter(array_map('intval', explode(',', $labelsCsv)), fn($v) => $v > 0));

    if ($view === 'shared') {
        $sql = 'SELECT n.*, ns.permission AS shared_permission, ns.shared_at,
                       u.display_name AS owner_name, u.email AS owner_email
                FROM notes n
                INNER JOIN note_shares ns ON ns.note_id = n.id
                INNER JOIN users u ON u.id = n.user_id
                WHERE ns.recipient_id = ?
                ORDER BY ns.shared_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
    } else {
        $sql = 'SELECT n.* FROM notes n WHERE n.user_id = ?
                ORDER BY n.is_pinned DESC, n.pinned_at DESC, n.updated_at DESC, n.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
    }

    $notes = [];
    foreach ($rows as $row) {
        $hydrated = hydrateNoteRow($pdo, $row, $userId);

        // Filter by selected labels if requested
        if (!empty($labelIds)) {
            $noteLabelIds = array_map(fn($l) => (int)$l['id'], $hydrated['labels']);
            $hasMatch = false;
            foreach ($labelIds as $reqId) {
                if (in_array($reqId, $noteLabelIds, true)) {
                    $hasMatch = true;
                    break;
                }
            }
            if (!$hasMatch) {
                continue;
            }
        }

        // Live search filter (never matches secret content of locked notes unless unlocked)
        if ($q !== '') {
            $inTitle   = mb_stripos($hydrated['title'], $q) !== false;
            $inContent = $hydrated['is_unlocked'] && (mb_stripos($hydrated['content'], $q) !== false);
            if (!$inTitle && !$inContent) {
                continue;
            }
        }

        $notes[] = $hydrated;
    }

    jsonResponse(['ok' => true, 'notes' => $notes]);
}

// POST create a new note (Initial creation requires only title and content)
if ($method === 'POST') {
    $title   = trim((string)($input['title'] ?? ''));
    $content = (string)($input['content'] ?? '');
    $color   = trim((string)($input['color'] ?? ''));

    if ($color === '') {
        $uStmt = $pdo->prepare('SELECT pref_note_color FROM users WHERE id = ?');
        $uStmt->execute([$userId]);
        $color = (string)($uStmt->fetchColumn() ?: '#ffffff');
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO notes (user_id, title, content, color, is_pinned, created_at, updated_at)
         VALUES (?, ?, ?, ?, 0, ?, ?)'
    );
    $stmt->execute([$userId, $title, $content, $color, $now, $now]);
    $newId = (int)$pdo->lastInsertId();

    $getStmt = $pdo->prepare('SELECT * FROM notes WHERE id = ?');
    $getStmt->execute([$newId]);
    $row = $getStmt->fetch();

    jsonResponse(['ok' => true, 'note' => hydrateNoteRow($pdo, $row, $userId)]);
}

// PUT update an existing note (Auto-save)
if ($method === 'PUT') {
    $noteId  = (int)($input['id'] ?? 0);
    $title   = (string)($input['title'] ?? '');
    $content = (string)($input['content'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM notes WHERE id = ?');
    $stmt->execute([$noteId]);
    $note = $stmt->fetch();

    if (!$note) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy ghi chú.'], 404);
    }

    $isOwner = ((int)$note['user_id'] === $userId);
    $canEdit = $isOwner;

    if (!$isOwner) {
        $shStmt = $pdo->prepare('SELECT permission FROM note_shares WHERE note_id = ? AND recipient_id = ?');
        $shStmt->execute([$noteId, $userId]);
        $perm = $shStmt->fetchColumn();
        if ($perm === 'edit') {
            $canEdit = true;
        }
    }

    if (!$canEdit) {
        jsonResponse(['ok' => false, 'error' => 'Bạn không có quyền chỉnh sửa ghi chú này.'], 403);
    }

    if (!empty($note['password_hash']) && empty($_SESSION['unlocked_notes'][$noteId])) {
        jsonResponse(['ok' => false, 'error' => 'Ghi chú đang được khóa mật khẩu. Vui lòng mở khóa trước khi sửa.'], 403);
    }

    $color = isset($input['color']) && trim((string)$input['color']) !== ''
        ? trim((string)$input['color'])
        : (string)$note['color'];
    $now = date('Y-m-d H:i:s');

    $upd = $pdo->prepare('UPDATE notes SET title = ?, content = ?, color = ?, updated_at = ? WHERE id = ?');
    $upd->execute([$title, $content, $color, $now, $noteId]);

    $stmt->execute([$noteId]);
    $updated = $stmt->fetch();
    jsonResponse(['ok' => true, 'note' => hydrateNoteRow($pdo, $updated, $userId)]);
}

// DELETE a note
if ($method === 'DELETE') {
    $noteId = (int)($input['id'] ?? ($_GET['id'] ?? 0));
    $stmt   = $pdo->prepare('SELECT * FROM notes WHERE id = ?');
    $stmt->execute([$noteId]);
    $note = $stmt->fetch();

    if (!$note) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy ghi chú.'], 404);
    }
    if ((int)$note['user_id'] !== $userId) {
        jsonResponse(['ok' => false, 'error' => 'Chỉ chủ sở hữu mới có quyền xóa ghi chú này.'], 403);
    }
    if (!empty($note['password_hash']) && empty($_SESSION['unlocked_notes'][$noteId])) {
        jsonResponse(['ok' => false, 'error' => 'Ghi chú đang được khóa mật khẩu. Vui lòng nhập mật khẩu trước khi xóa.'], 403);
    }

    $del = $pdo->prepare('DELETE FROM notes WHERE id = ?');
    $del->execute([$noteId]);

    jsonResponse(['ok' => true, 'deleted_id' => $noteId]);
}

jsonResponse(['ok' => false, 'error' => 'Phương thức không được hỗ trợ.'], 405);
