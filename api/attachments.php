<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$userId = requireAuthApi();
$pdo    = getPDO();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input  = readJsonInput();

/**
 * Verifies current user has owner or 'edit' share access to $noteId and that the note is unlocked.
 */
function verifyNoteEditAccess(PDO $pdo, int $noteId, int $userId): array {
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
        if ($shStmt->fetchColumn() === 'edit') {
            $canEdit = true;
        }
    }

    if (!$canEdit) {
        jsonResponse(['ok' => false, 'error' => 'Bạn không có quyền chỉnh sửa tệp đính kèm của ghi chú này.'], 403);
    }

    if (!empty($note['password_hash']) && empty($_SESSION['unlocked_notes'][$noteId])) {
        jsonResponse(['ok' => false, 'error' => 'Vui lòng mở khóa mật khẩu ghi chú trước khi thao tác tệp đính kèm.'], 403);
    }

    return $note;
}

if ($method === 'POST') {
    $noteId = (int)($_POST['note_id'] ?? ($input['note_id'] ?? 0));
    verifyNoteEditAccess($pdo, $noteId, $userId);

    if (empty($_FILES['files'])) {
        jsonResponse(['ok' => false, 'error' => 'Không có tệp nào được chọn.'], 400);
    }

    $files = $_FILES['files'];
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

    $forbiddenExtensions = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'cgi', 'pl', 'svg'];
    $imageExtensions     = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    $videoExtensions     = ['mp4', 'webm', 'ogg', 'mov', 'm4v'];
    $maxFileSize         = 15 * 1024 * 1024; // 15MB per file

    $uploadDir = dirname(__DIR__) . '/uploads/attachments';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    for ($i = 0; $i < count($names); $i++) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            jsonResponse(['ok' => false, 'error' => 'Lỗi trong quá trình tải tệp lên.'], 400);
        }

        $origName = basename((string)$names[$i]);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $size     = (int)($sizes[$i] ?? 0);
        $tmpPath  = (string)$tmpNames[$i];

        if ($size <= 0 || $size > $maxFileSize) {
            jsonResponse(['ok' => false, 'error' => "Tệp '{$origName}' vượt quá giới hạn dung lượng 15MB."], 400);
        }

        if ($ext === '' || in_array($ext, $forbiddenExtensions, true)) {
            jsonResponse(['ok' => false, 'error' => "Định dạng tệp '.{$ext}' bị từ chối vì lý do bảo mật."], 400);
        }

        $mime = (string)($finfo->file($tmpPath) ?: 'application/octet-stream');
        if (str_contains($mime, 'php') || str_contains($mime, 'x-executable') || str_contains($mime, 'x-dosexec')) {
            jsonResponse(['ok' => false, 'error' => 'Nội dung tệp thực thi không được phép tải lên.'], 400);
        }

        $fileType = 'file';
        if (in_array($ext, $imageExtensions, true) || str_starts_with($mime, 'image/')) {
            $fileType = 'image';
        } elseif (in_array($ext, $videoExtensions, true) || str_starts_with($mime, 'video/')) {
            $fileType = 'video';
        }

        $safeStoredName = 'att_' . $noteId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destFull       = $uploadDir . '/' . $safeStoredName;

        if (!move_uploaded_file($tmpPath, $destFull)) {
            if (getenv('APP_TEST_MODE') === '1') {
                copy($tmpPath, $destFull);
            } else {
                jsonResponse(['ok' => false, 'error' => 'Không thể lưu tệp đính kèm lên máy chủ.'], 500);
            }
        }

        $relativePath = 'uploads/attachments/' . $safeStoredName;
        $ins = $pdo->prepare(
            'INSERT INTO note_attachments (note_id, file_name, file_path, file_type, mime_type, file_size)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$noteId, $origName, $relativePath, $fileType, $mime, $size]);
    }

    $attStmt = $pdo->prepare('SELECT * FROM note_attachments WHERE note_id = ? ORDER BY id ASC');
    $attStmt->execute([$noteId]);

    jsonResponse([
        'ok'          => true,
        'message'     => 'Đã đính kèm tệp thành công.',
        'attachments' => $attStmt->fetchAll(),
    ]);
}

if ($method === 'DELETE') {
    $attId = (int)($input['attachment_id'] ?? ($_GET['attachment_id'] ?? 0));
    $stmt  = $pdo->prepare('SELECT * FROM note_attachments WHERE id = ?');
    $stmt->execute([$attId]);
    $att = $stmt->fetch();

    if (!$att) {
        jsonResponse(['ok' => false, 'error' => 'Không tìm thấy tệp đính kèm.'], 404);
    }

    verifyNoteEditAccess($pdo, (int)$att['note_id'], $userId);

    $fullPath = dirname(__DIR__) . '/' . $att['file_path'];
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }

    $del = $pdo->prepare('DELETE FROM note_attachments WHERE id = ?');
    $del->execute([$attId]);

    jsonResponse(['ok' => true, 'deleted_id' => $attId]);
}

jsonResponse(['ok' => false, 'error' => 'Phương thức không hợp lệ.'], 405);
