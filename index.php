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
    <title>CraftNotes — Quản lý Ghi chú</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563EB">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="craft-app-bg font-size-<?= htmlspecialchars($prefFont) ?>"
      data-default-note-color="<?= htmlspecialchars($prefColor) ?>"
      data-user-id="<?= (int)$currentUser['id'] ?>">

    <!-- Prominent Unverified Account Notification Banner (Mandatory Requirement) -->
    <?php if ((int)$currentUser['is_activated'] === 0): ?>
    <div id="unverifiedBanner" class="craft-unverified-banner py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-circle-fill text-warning fs-5"></i>
            <span class="small fw-medium">
                <strong>Tài khoản chưa xác thực:</strong> Vui lòng kiểm tra email (<strong><?= htmlspecialchars($currentUser['email']) ?></strong>) và nhấn vào liên kết kích hoạt để hoàn tất xác thực.
            </span>
        </div>
        <a href="logs/mails/latest.html" target="_blank" class="btn btn-sm btn-warning fw-semibold rounded-pill px-3">
            <i class="bi bi-envelope-open me-1"></i>Mở email kích hoạt (Local Box)
        </a>
    </div>
    <?php endif; ?>

    <!-- Offline Mode Banner (PWA Criterion #31) -->
    <div id="offlineBanner" class="craft-offline-banner d-none py-2 px-3 text-center small fw-semibold">
        <i class="bi bi-wifi-off me-1"></i>Đang ở chế độ Ngoại tuyến (Offline) — Dữ liệu đang đọc từ IndexedDB cục bộ và sẽ tự động đồng bộ khi có mạng lại.
    </div>

    <!-- Main Craft Shell -->
    <div class="craft-shell container-xxl py-3 py-md-4 px-3 px-md-4">

        <!-- Top Header Bar -->
        <header class="craft-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <button class="btn craft-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#labelSidebarOffcanvas" aria-label="Mở danh mục nhãn">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="craft-icon-tile craft-tint-blue">
                    <i class="bi bi-journal-richtext fs-5"></i>
                </div>
                <div>
                    <h1 class="h4 fw-bold mb-0 craft-heading">Home</h1>
                    <span class="text-secondary small">Xin chào, <?= htmlspecialchars($currentUser['display_name']) ?></span>
                </div>
            </div>

            <!-- Live Search Box (300ms Debounce - Strictly NO Search button) -->
            <div class="craft-search-wrapper flex-grow-1 mx-lg-4">
                <i class="bi bi-search craft-search-icon"></i>
                <input type="search" id="searchInput" class="form-control craft-search-input"
                       placeholder="Tìm kiếm trực tiếp theo tiêu đề hoặc nội dung ghi chú..." autocomplete="off">
            </div>

            <!-- View Switcher (Grid default vs List) & User Profile Menu -->
            <div class="d-flex align-items-center gap-2">
                <div class="craft-segmented-control" role="group" aria-label="Chế độ hiển thị">
                    <button type="button" id="btnGridView" class="craft-seg-btn active" onclick="NoteApp.setViewMode('grid')" title="Hiển thị dạng Lưới (Mặc định)">
                        <i class="bi bi-grid-fill me-1"></i><span class="d-none d-sm-inline">Lưới</span>
                    </button>
                    <button type="button" id="btnListView" class="craft-seg-btn" onclick="NoteApp.setViewMode('list')" title="Hiển thị dạng Danh sách">
                        <i class="bi bi-view-stacked me-1"></i><span class="d-none d-sm-inline">Danh sách</span>
                    </button>
                </div>

                <a href="logs/mails/latest.html" target="_blank" class="btn craft-icon-btn" title="Xem email gần nhất (Kích hoạt / OTP / Chia sẻ)">
                    <i class="bi bi-mailbox"></i>
                </a>

                <a href="profile.php" class="d-flex align-items-center gap-2 text-decoration-none craft-profile-pill px-2 py-1" title="Hồ sơ & Tùy chọn">
                    <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" class="craft-avatar-sm">
                    <span class="small fw-semibold d-none d-md-inline text-body"><?= htmlspecialchars($currentUser['display_name']) ?></span>
                </a>

                <button type="button" id="logoutBtn" class="btn craft-icon-btn text-danger" onclick="NoteApp.logout()" title="Đăng xuất">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </div>
        </header>

        <!-- Craft-Style Underline Navigation Tabs (Strictly Allowed Sections Only) -->
        <nav class="craft-tabs-nav d-flex align-items-center gap-4 border-bottom mb-3">
            <button type="button" id="tabMyNotes" class="craft-tab-link active" onclick="NoteApp.switchTab('all')">
                <span>Ghi chú của tôi</span>
                <span id="badgeMyNotesCount" class="craft-tab-count ms-1">0</span>
            </button>
            <button type="button" id="tabSharedNotes" class="craft-tab-link" onclick="NoteApp.switchTab('shared')">
                <span>Được chia sẻ với tôi</span>
                <span id="badgeSharedCount" class="craft-tab-count ms-1">0</span>
            </button>
            <a href="profile.php" class="craft-tab-link text-decoration-none">
                <span>Hồ sơ &amp; Tùy chọn</span>
            </a>
        </nav>

        <!-- Craft-Style Pastel Action Pills Row (Inspired directly by Mobbin Reference) -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button" class="craft-action-pill craft-pill-blue" onclick="NoteApp.openEditor(null)">
                    <span class="craft-pill-icon"><i class="bi bi-file-earmark-plus-fill"></i></span>
                    <span>Tạo ghi chú mới</span>
                </button>
                <button type="button" class="craft-action-pill craft-pill-purple" onclick="NoteApp.openLabelManager()">
                    <span class="craft-pill-icon"><i class="bi bi-tags-fill"></i></span>
                    <span>Quản lý nhãn</span>
                </button>
            </div>

            <!-- Active Label Filter Chips Bar -->
            <div id="labelFilterPills" class="d-flex flex-wrap align-items-center gap-2"></div>
        </div>

        <div class="row g-4">
            <!-- Left Sidebar (Desktop) / Offcanvas (Mobile & Tablet) for Label Management & Filtering -->
            <div class="col-lg-3">
                <div class="offcanvas-lg offcanvas-start craft-sidebar-card" tabindex="-1" id="labelSidebarOffcanvas" aria-labelledby="labelSidebarTitle">
                    <div class="offcanvas-header border-bottom">
                        <h2 class="h6 fw-bold mb-0" id="labelSidebarTitle">Bộ lọc Nhãn (Labels)</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#labelSidebarOffcanvas" aria-label="Đóng"></button>
                    </div>
                    <div class="offcanvas-body d-flex flex-column p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-uppercase small fw-bold text-secondary" style="letter-spacing:0.05em">Danh sách nhãn</span>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 fw-semibold" onclick="NoteApp.clearLabelFilter()">
                                Xóa lọc
                            </button>
                        </div>

                        <!-- Quick Add Label Input -->
                        <form id="quickAddLabelForm" class="d-flex gap-2 mb-3" onsubmit="NoteApp.handleCreateLabel(event)">
                            <input type="text" id="newLabelNameInput" class="form-control form-control-sm craft-input" placeholder="Thêm nhãn mới..." maxlength="100" required>
                            <button type="submit" class="btn btn-sm btn-primary craft-btn-primary px-3" title="Thêm nhãn">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </form>

                        <!-- Labels List -->
                        <div id="sidebarLabelsList" class="d-flex flex-column gap-1">
                            <div class="text-secondary small py-2">Đang tải nhãn...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Notes Area (Default Grid View, Switchable to List View) -->
            <div class="col-lg-9">
                <!-- Pinned Notes Section -->
                <section id="pinnedSection" class="mb-4 d-none">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-pin-angle-fill text-primary"></i>
                        <h2 class="h6 fw-bold text-uppercase text-secondary mb-0" style="letter-spacing:0.05em">Đã ghim lên đầu</h2>
                    </div>
                    <div id="pinnedNotesContainer" class="notes-grid"></div>
                </section>

                <!-- Other Notes / Main Notes Container (Default class: notes-grid) -->
                <section id="mainNotesSection">
                    <div id="otherSectionHeader" class="d-flex align-items-center gap-2 mb-2 d-none">
                        <i class="bi bi-journal-text text-secondary"></i>
                        <h2 class="h6 fw-bold text-uppercase text-secondary mb-0" style="letter-spacing:0.05em">Ghi chú khác</h2>
                    </div>
                    <div id="notesContainer" class="notes-grid"></div>
                </section>

                <!-- Empty State Card (Craft Style) -->
                <div id="emptyStateBox" class="craft-empty-state text-center py-5 px-3 d-none">
                    <div class="craft-icon-tile craft-tint-blue mx-auto mb-3" style="width:56px;height:56px">
                        <i class="bi bi-journal-plus fs-3"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-1">Chưa có ghi chú nào</h3>
                    <p class="text-secondary small mb-3">Nhấn vào nút <strong>Tạo ghi chú mới</strong> để bắt đầu viết. Mọi nội dung sẽ được tự động lưu.</p>
                    <button type="button" class="btn btn-primary craft-btn-primary px-4" onclick="NoteApp.openEditor(null)">
                        <i class="bi bi-plus-lg me-1"></i>Tạo ghi chú đầu tiên
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================================
         UNIFIED NOTE EDITOR MODAL (Used for BOTH Create & Edit - NO SAVE BUTTON)
         ====================================================================== -->
    <div class="modal fade" id="noteEditorModal" tabindex="-1" aria-labelledby="noteEditorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content craft-modal-sheet" id="noteEditorSheet">
                <div class="modal-header border-0 pb-1 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span id="editorModeBadge" class="badge rounded-pill text-bg-light border fw-medium">Ghi chú</span>
                        <span id="autoSaveStatus" class="small text-secondary d-flex align-items-center gap-1">
                            <i class="bi bi-cloud-check"></i><span>Tự động lưu</span>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" id="editorPinBtn" class="btn craft-icon-btn" onclick="NoteApp.togglePinCurrentNote()" title="Ghim ghi chú lên đầu">
                            <i class="bi bi-pin-angle"></i>
                        </button>
                        <button type="button" id="editorLockBtn" class="btn craft-icon-btn" onclick="SecurityShareApp.openPasswordDialogForCurrent()" title="Bảo vệ bằng mật khẩu">
                            <i class="bi bi-lock"></i>
                        </button>
                        <button type="button" id="editorShareBtn" class="btn craft-icon-btn" onclick="SecurityShareApp.openShareDialogForCurrent()" title="Chia sẻ ghi chú">
                            <i class="bi bi-person-plus"></i>
                        </button>
                        <button type="button" id="editorDeleteBtn" class="btn craft-icon-btn text-danger" onclick="NoteApp.confirmDeleteCurrentNote()" title="Xóa ghi chú">
                            <i class="bi bi-trash3"></i>
                        </button>
                        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                </div>

                <div class="modal-body pt-2">
                    <!-- Mandatory Initial Fields: ONLY Title and Content -->
                    <input type="text" id="noteTitleInput" class="form-control craft-editor-title mb-2"
                           placeholder="Tiêu đề ghi chú..." autocomplete="off">

                    <textarea id="noteContentInput" class="form-control craft-editor-content" rows="8"
                              placeholder="Bắt đầu viết nội dung ghi chú tại đây (tự động lưu khi bạn gõ)..."></textarea>

                    <!-- Subsequent Metadata Section (Labels, Attachments, Color) -->
                    <div id="editorMetadataSection" class="mt-3 pt-3 border-top">
                        <!-- Attached Labels Checkboxes -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="bi bi-tags me-1"></i>Gắn nhãn cho ghi chú:
                            </label>
                            <div id="editorLabelsCheckboxes" class="d-flex flex-wrap gap-2"></div>
                        </div>

                        <!-- Note Color Swatches -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary mb-1">
                                <i class="bi bi-palette me-1"></i>Màu nền thẻ ghi chú:
                            </label>
                            <div id="editorColorSwatches" class="d-flex flex-wrap gap-2">
                                <button type="button" class="craft-color-swatch" data-color="#ffffff" style="background:#ffffff" onclick="NoteApp.selectEditorColor('#ffffff')" title="Trắng mặc định"></button>
                                <button type="button" class="craft-color-swatch" data-color="#eef4ff" style="background:#eef4ff" onclick="NoteApp.selectEditorColor('#eef4ff')" title="Xanh lam dịu"></button>
                                <button type="button" class="craft-color-swatch" data-color="#f5eeff" style="background:#f5eeff" onclick="NoteApp.selectEditorColor('#f5eeff')" title="Tím pastel"></button>
                                <button type="button" class="craft-color-swatch" data-color="#ecfdf5" style="background:#ecfdf5" onclick="NoteApp.selectEditorColor('#ecfdf5')" title="Xanh ngọc"></button>
                                <button type="button" class="craft-color-swatch" data-color="#fef9ec" style="background:#fef9ec" onclick="NoteApp.selectEditorColor('#fef9ec')" title="Vàng kem"></button>
                                <button type="button" class="craft-color-swatch" data-color="#fff1f2" style="background:#fff1f2" onclick="NoteApp.selectEditorColor('#fff1f2')" title="Hồng phấn"></button>
                            </div>
                        </div>

                        <!-- Multi-file Attachments (Images, Videos, Files) -->
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label small fw-bold text-secondary mb-0">
                                    <i class="bi bi-paperclip me-1"></i>Tệp đính kèm (Ảnh, Video, Tài liệu):
                                </label>
                                <label class="btn btn-sm craft-action-pill craft-pill-mint mb-0 cursor-pointer">
                                    <i class="bi bi-cloud-arrow-up me-1"></i><span>Đính kèm tệp</span>
                                    <input type="file" id="noteAttachmentInput" class="d-none" multiple onchange="NoteApp.handleAttachmentUpload(event)">
                                </label>
                            </div>
                            <div id="editorAttachmentsList" class="row g-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================================
         DELETE CONFIRMATION DIALOG (Mandatory Requirement Criterion #13)
         ====================================================================== -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content craft-modal-sheet p-3 text-center">
                <div class="craft-icon-tile craft-tint-rose mx-auto mb-3">
                    <i class="bi bi-trash3-fill fs-4"></i>
                </div>
                <h3 class="h6 fw-bold mb-1">Xác nhận xóa ghi chú?</h3>
                <p class="text-secondary small mb-3">Hành động này sẽ xóa vĩnh viễn ghi chú và không thể hoàn tác.</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light flex-grow-1 rounded-3" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger flex-grow-1 rounded-3" onclick="NoteApp.executeDeleteNote()">Xóa ngay</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================================
         LABEL MANAGEMENT MODAL (Criterion #20)
         ====================================================================== -->
    <div class="modal fade" id="labelManagerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content craft-modal-sheet">
                <div class="modal-header border-0">
                    <h3 class="h6 fw-bold mb-0"><i class="bi bi-tags-fill text-primary me-2"></i>Quản lý danh sách nhãn</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body pt-0">
                    <p class="text-secondary small mb-3">Đổi tên nhãn sẽ tự động cập nhật trên mọi ghi chú. Xóa nhãn không làm mất ghi chú.</p>
                    <div id="modalLabelsManageList" class="d-flex flex-column gap-2"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================================
         NOTE PASSWORD PROTECTION MODAL (Criteria #23, #24 - Better Approach)
         ====================================================================== -->
    <div class="modal fade" id="notePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content craft-modal-sheet">
                <div class="modal-header border-0">
                    <h3 class="h6 fw-bold mb-0" id="notePasswordModalTitle"><i class="bi bi-shield-lock-fill text-primary me-2"></i>Bảo vệ ghi chú bằng mật khẩu</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body pt-0" id="notePasswordModalBody"></div>
            </div>
        </div>
    </div>

    <!-- ======================================================================
         NOTE SHARING MODAL (Criterion #25 - Better Approach)
         ====================================================================== -->
    <div class="modal fade" id="noteShareModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content craft-modal-sheet">
                <div class="modal-header border-0">
                    <h3 class="h6 fw-bold mb-0"><i class="bi bi-people-fill text-primary me-2"></i>Chia sẻ ghi chú &amp; Phân quyền</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body pt-0" id="noteShareModalBody"></div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/offline_db.js"></script>
    <script src="assets/js/security_share.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
