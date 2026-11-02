<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

$currentUser = requireAuthPage();
$avatarUrl   = !empty($currentUser['avatar']) ? $currentUser['avatar'] : 'assets/img/default-avatar.svg';
$prefTheme   = ($currentUser['pref_theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
$prefFont    = $currentUser['pref_font_size'] ?? 'medium';
$prefColor   = $currentUser['pref_note_color'] ?? '#ffffff';
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="<?= htmlspecialchars($prefTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ &amp; Tùy chọn — CraftNotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="craft-app-bg font-size-<?= htmlspecialchars($prefFont) ?>">
    <div class="container py-4" style="max-width: 860px;">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center gap-3">
                <a href="index.php" class="btn craft-icon-btn border rounded-3" title="Quay về Trang chủ">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <div>
                    <h1 class="h4 fw-bold mb-0">Hồ sơ &amp; Tùy chọn cá nhân</h1>
                    <p class="text-secondary small mb-0">Quản lý thông tin tài khoản, mật khẩu và giao diện ghi chú</p>
                </div>
            </div>
            <a href="index.php" class="btn btn-outline-primary rounded-pill px-3 small fw-semibold">
                <i class="bi bi-journal-text me-1"></i>Về danh sách ghi chú
            </a>
        </div>

        <div id="profileAlert" class="alert d-none rounded-3 py-2 small" role="alert"></div>

        <div class="row g-4">
            <!-- Section 1: View & Edit Profile + Avatar (Criteria #5, #6) -->
            <div class="col-md-6">
                <div class="craft-sidebar-card p-4 h-100">
                    <h2 class="h6 fw-bold mb-3"><i class="bi bi-person-badge text-primary me-2"></i>Thông tin Hồ sơ &amp; Ảnh đại diện</h2>
                    <form id="profileForm" enctype="multipart/form-data">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="<?= htmlspecialchars($avatarUrl) ?>" id="avatarPreview" alt="Avatar"
                                 class="rounded-circle border" style="width:72px;height:72px;object-fit:cover">
                            <div>
                                <label class="btn btn-sm craft-action-pill craft-pill-blue mb-1 cursor-pointer">
                                    <i class="bi bi-upload me-1"></i><span>Chọn ảnh đại diện</span>
                                    <input type="file" name="avatar" id="avatarInput" class="d-none" accept="image/png,image/jpeg,image/gif,image/webp">
                                </label>
                                <div class="text-secondary" style="font-size:0.75rem">Hỗ trợ JPG, PNG, GIF, WEBP (Tối đa 2MB)</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Email đăng nhập</label>
                            <input type="email" class="form-control craft-input" value="<?= htmlspecialchars($currentUser['email']) ?>" disabled>
                        </div>

                        <div class="mb-3">
                            <label for="displayNameInput" class="form-label small fw-semibold text-secondary">Tên hiển thị</label>
                            <input type="text" id="displayNameInput" name="display_name" class="form-control craft-input"
                                   value="<?= htmlspecialchars($currentUser['display_name']) ?>" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 rounded-3">Lưu hồ sơ</button>
                    </form>
                </div>
            </div>

            <!-- Section 2: User Preferences (Criterion #8: Font size, Note color, Light/Dark theme) -->
            <div class="col-md-6">
                <div class="craft-sidebar-card p-4 h-100">
                    <h2 class="h6 fw-bold mb-3"><i class="bi bi-sliders text-primary me-2"></i>Tùy chọn hiển thị (Preferences)</h2>
                    <form id="preferencesForm">
                        <div class="mb-3">
                            <label for="prefFontSize" class="form-label small fw-semibold text-secondary">Cỡ chữ ghi chú</label>
                            <select id="prefFontSize" class="form-select craft-input">
                                <option value="small" <?= $prefFont === 'small' ? 'selected' : '' ?>>Nhỏ (Small)</option>
                                <option value="medium" <?= $prefFont === 'medium' ? 'selected' : '' ?>>Vừa (Medium - Mặc định)</option>
                                <option value="large" <?= $prefFont === 'large' ? 'selected' : '' ?>>Lớn (Large)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="prefNoteColor" class="form-label small fw-semibold text-secondary">Màu nền ghi chú mặc định</label>
                            <select id="prefNoteColor" class="form-select craft-input">
                                <option value="#ffffff" <?= $prefColor === '#ffffff' ? 'selected' : '' ?>>Trắng tinh khôi (#FFFFFF)</option>
                                <option value="#eef4ff" <?= $prefColor === '#eef4ff' ? 'selected' : '' ?>>Xanh lam Craft (#EEF4FF)</option>
                                <option value="#f5eeff" <?= $prefColor === '#f5eeff' ? 'selected' : '' ?>>Tím Lavender (#F5EEFF)</option>
                                <option value="#ecfdf5" <?= $prefColor === '#ecfdf5' ? 'selected' : '' ?>>Xanh ngọc Mint (#ECFDF5)</option>
                                <option value="#fef9ec" <?= $prefColor === '#fef9ec' ? 'selected' : '' ?>>Vàng kem ấm (#FEF9EC)</option>
                                <option value="#fff1f2" <?= $prefColor === '#fff1f2' ? 'selected' : '' ?>>Hồng phấn (#FFF1F2)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="prefTheme" class="form-label small fw-semibold text-secondary">Giao diện Sáng / Tối (Theme)</label>
                            <select id="prefTheme" class="form-select craft-input">
                                <option value="light" <?= $prefTheme === 'light' ? 'selected' : '' ?>>☀️ Giao diện Sáng (Light Mode)</option>
                                <option value="dark" <?= $prefTheme === 'dark' ? 'selected' : '' ?>>🌙 Giao diện Tối (Dark Mode)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 rounded-3">Áp dụng tùy chọn</button>
                    </form>
                </div>
            </div>

            <!-- Section 3: Change Password (Criterion #7) -->
            <div class="col-12">
                <div class="craft-sidebar-card p-4">
                    <h2 class="h6 fw-bold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Đổi mật khẩu tài khoản</h2>
                    <form id="changePasswordForm" class="row g-3">
                        <div class="col-md-4">
                            <label for="curPassword" class="form-label small fw-semibold text-secondary">Mật khẩu hiện tại</label>
                            <input type="password" id="curPassword" class="form-control craft-input" required>
                        </div>
                        <div class="col-md-4">
                            <label for="newPassword" class="form-label small fw-semibold text-secondary">Mật khẩu mới</label>
                            <input type="password" id="newPassword" class="form-control craft-input" required>
                        </div>
                        <div class="col-md-4">
                            <label for="confirmNewPassword" class="form-label small fw-semibold text-secondary">Nhập lại mật khẩu mới</label>
                            <input type="password" id="confirmNewPassword" class="form-control craft-input" required>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-dark rounded-3 px-4">Cập nhật mật khẩu</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showAlert(msg, isSuccess = true) {
        const box = document.getElementById('profileAlert');
        box.className = `alert ${isSuccess ? 'alert-success' : 'alert-danger'} rounded-3 py-2 small`;
        box.textContent = msg;
        box.classList.remove('d-none');
    }

    document.getElementById('profileForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const res = await fetch('api/profile.php?action=update_profile', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            showAlert(data.error || 'Lỗi cập nhật hồ sơ.', false);
            return;
        }
        document.getElementById('avatarPreview').src = data.profile.avatar_url;
        showAlert(data.message, true);
    });

    document.getElementById('preferencesForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            pref_font_size: document.getElementById('prefFontSize').value,
            pref_note_color: document.getElementById('prefNoteColor').value,
            pref_theme: document.getElementById('prefTheme').value
        };
        const res = await fetch('api/profile.php?action=update_preferences', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            showAlert(data.error || 'Lỗi lưu tùy chọn.', false);
            return;
        }
        document.documentElement.setAttribute('data-bs-theme', data.profile.pref_theme);
        document.body.className = `craft-app-bg font-size-${data.profile.pref_font_size}`;
        showAlert(data.message, true);
    });

    document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await fetch('api/profile.php?action=change_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_password: document.getElementById('curPassword').value,
                new_password: document.getElementById('newPassword').value,
                confirm_password: document.getElementById('confirmNewPassword').value
            })
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            showAlert(data.error || 'Không thể đổi mật khẩu.', false);
            return;
        }
        e.target.reset();
        showAlert(data.message, true);
    });
    </script>
</body>
</html>
