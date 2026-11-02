<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

$token   = trim((string)($_GET['token'] ?? ''));
$pdo     = getPDO();
$success = false;
$message = 'Mã kích hoạt không hợp lệ hoặc tài khoản đã được kích hoạt trước đó.';

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT id, display_name FROM users WHERE activation_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $upd = $pdo->prepare('UPDATE users SET is_activated = 1, activation_token = NULL WHERE id = ?');
        $upd->execute([(int)$user['id']]);
        $success = true;
        $message = 'Tài khoản của bạn đã được kích hoạt thành công!';
    }
}

if (getenv('APP_TEST_MODE') === '1') {
    jsonResponse(['ok' => $success, 'message' => $message], $success ? 200 : 400);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kích hoạt tài khoản — CraftNotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="craft-auth-bg d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="craft-auth-card text-center">
        <div class="craft-icon-tile <?= $success ? 'craft-tint-green' : 'craft-tint-amber' ?> mx-auto mb-3">
            <i class="bi <?= $success ? 'bi-patch-check-fill' : 'bi-exclamation-triangle-fill' ?> fs-3"></i>
        </div>
        <h1 class="h5 fw-bold mb-2"><?= $success ? 'Xác thực thành công' : 'Thông báo kích hoạt' ?></h1>
        <p class="text-secondary small mb-4"><?= htmlspecialchars($message) ?></p>
        <a href="index.php" class="btn btn-primary craft-btn-primary w-100 py-2">Quay về Trang chủ Ghi chú</a>
    </div>
</body>
</html>
