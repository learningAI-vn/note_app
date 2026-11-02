<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$userId = requireAuthApi();
$pdo    = getPDO();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$input  = readJsonInput();

// POST action=set_note_labels (Attach / sync labels on a note)
if ($method === 'POST' && $action === 'set_note_labels') {
    $noteId   = (int)($input['note_id'] ?? 0);
    $labelIds = isset($input['label_ids']) && is_array($input['label_ids'])
        ? array_values(array_unique(array_filter(array_map('intval', $input['label_ids']), fn($v) => $v > 0)))
        : [];

    $nStmt = $pdo->prepare('SELECT * FROM notes WHERE id = ?');
    $nStmt->execute([$noteId]);
    $note = $nStmt->fetch();

    if (!$note) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy ghi chú.'], 404);
    }
    if ((int)$note['user_id'] !== $userId) {
        jsonResponse(['ok' => false, 'error' => 'Chỉ chủ sở hữu mới được gắn nhãn ghi chú.'], 403);
    }
    if (!empty($note['password_hash']) && empty($_SESSION['unlocked_notes'][$noteId])) {
        jsonResponse(['ok' => false, 'error' => 'Vui lòng mở khóa ghi chú trước khi thay đổi nhãn.'], 403);
    }

    $delStmt = $pdo->prepare('DELETE FROM note_labels WHERE note_id = ?');
    $delStmt->execute([$noteId]);

    $insStmt = $pdo->prepare('INSERT INTO note_labels (note_id, label_id) VALUES (?, ?)');
    $chkStmt = $pdo->prepare('SELECT id FROM labels WHERE id = ? AND user_id = ?');
    foreach ($labelIds as $lblId) {
        $chkStmt->execute([$lblId, $userId]);
        if ($chkStmt->fetch()) {
            $insStmt->execute([$noteId, $lblId]);
        }
    }

    $lblStmt = $pdo->prepare(
        'SELECT l.id, l.name
         FROM labels l
         INNER JOIN note_labels nl ON nl.label_id = l.id
         WHERE nl.note_id = ?
         ORDER BY l.name ASC'
    );
    $lblStmt->execute([$noteId]);

    jsonResponse(['ok' => true, 'labels' => $lblStmt->fetchAll()]);
}

// GET list all labels for current user
if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT id, name, created_at FROM labels WHERE user_id = ? ORDER BY name ASC');
    $stmt->execute([$userId]);
    jsonResponse(['ok' => true, 'labels' => $stmt->fetchAll()]);
}

// POST create a new label
if ($method === 'POST') {
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 100) {
        jsonResponse(['ok' => false, 'error' => 'Tên nhãn không hợp lệ.'], 400);
    }

    $chk = $pdo->prepare('SELECT id FROM labels WHERE user_id = ? AND name = ?');
    $chk->execute([$userId, $name]);
    if ($chk->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Nhãn này đã tồn tại.'], 400);
    }

    $ins = $pdo->prepare('INSERT INTO labels (user_id, name) VALUES (?, ?)');
    $ins->execute([$userId, $name]);
    $newId = (int)$pdo->lastInsertId();

    jsonResponse([
        'ok'    => true,
        'label' => [
            'id'   => $newId,
            'name' => $name,
        ],
    ]);
}

// PUT rename an existing label (Automatically reflects across all notes via note_labels join)
if ($method === 'PUT') {
    $labelId = (int)($input['id'] ?? 0);
    $name    = trim((string)($input['name'] ?? ''));

    if ($labelId <= 0 || $name === '' || mb_strlen($name) > 100) {
        jsonResponse(['ok' => false, 'error' => 'Thông tin đổi tên nhãn không hợp lệ.'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM labels WHERE id = ? AND user_id = ?');
    $stmt->execute([$labelId, $userId]);
    if (!$stmt->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy nhãn.'], 404);
    }

    $dup = $pdo->prepare('SELECT id FROM labels WHERE user_id = ? AND name = ? AND id != ?');
    $dup->execute([$userId, $name, $labelId]);
    if ($dup->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Tên nhãn mới đã trùng với một nhãn khác.'], 400);
    }

    $upd = $pdo->prepare('UPDATE labels SET name = ? WHERE id = ? AND user_id = ?');
    $upd->execute([$name, $labelId, $userId]);

    jsonResponse([
        'ok'    => true,
        'label' => [
            'id'   => $labelId,
            'name' => $name,
        ],
    ]);
}

// DELETE a label (Cascades only to note_labels; associated notes remain untouched)
if ($method === 'DELETE') {
    $labelId = (int)($input['id'] ?? ($_GET['id'] ?? 0));
    $stmt    = $pdo->prepare('SELECT * FROM labels WHERE id = ? AND user_id = ?');
    $stmt->execute([$labelId, $userId]);
    if (!$stmt->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy nhãn.'], 404);
    }

    // Explicitly remove note_labels links first in case foreign keys are disabled on a host MySQL setup
    $delLinks = $pdo->prepare('DELETE FROM note_labels WHERE label_id = ?');
    $delLinks->execute([$labelId]);

    $del = $pdo->prepare('DELETE FROM labels WHERE id = ? AND user_id = ?');
    $del->execute([$labelId, $userId]);

    jsonResponse(['ok' => true, 'deleted_id' => $labelId]);
}

jsonResponse(['ok' => false, 'error' => 'Phương thức không hợp lệ.'], 405);
