/**
 * Security & Note Sharing Controller (TV1 - Criteria #23, #24, #25)
 * Implements "Better Approach" password confirmation dialogs and recipient management table.
 */

const SecurityShareApp = {
    unlockCallback: null,
    targetNote: null,

    // Prompt user to unlock a password-protected note before viewing, editing, or deleting
    promptUnlockNote(note, onSuccess) {
        this.targetNote = note;
        this.unlockCallback = onSuccess;

        const titleEl = document.getElementById('notePasswordModalTitle');
        const bodyEl = document.getElementById('notePasswordModalBody');
        titleEl.innerHTML = '<i class="bi bi-lock-fill text-warning me-2"></i>Nhập mật khẩu mở khóa ghi chú';

        bodyEl.innerHTML = `
            <p class="text-secondary small mb-3">Ghi chú <strong>"${NoteApp.escapeHtml(note.title || 'Không tiêu đề')}"</strong> đang được khóa bảo mật. Vui lòng nhập mật khẩu của ghi chú này để tiếp tục.</p>
            <div id="secModalAlert" class="alert alert-danger d-none py-2 small"></div>
            <form id="unlockNoteForm">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Mật khẩu ghi chú</label>
                    <input type="password" id="unlockPassInput" class="form-control craft-input" placeholder="Nhập mật khẩu..." required autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100 rounded-3">Mở khóa ghi chú</button>
            </form>
        `;

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('notePasswordModal'));
        modal.show();

        document.getElementById('unlockNoteForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = document.getElementById('unlockPassInput').value;
            const res = await fetch('api/note_security.php?action=verify_password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_id: note.id, password })
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                const alertEl = document.getElementById('secModalAlert');
                alertEl.textContent = data.error || 'Mật khẩu không đúng.';
                alertEl.classList.remove('d-none');
                return;
            }
            modal.hide();
            if (typeof this.unlockCallback === 'function') {
                this.unlockCallback(data.note);
            }
        });
    },

    // Open Set / Change / Disable Password modal from inside the Note Editor
    async openPasswordDialogForCurrent() {
        if (!NoteApp.currentNote) {
            await NoteApp.performAutoSave();
        }
        const note = NoteApp.currentNote;
        if (!note) return;

        const titleEl = document.getElementById('notePasswordModalTitle');
        const bodyEl = document.getElementById('notePasswordModalBody');

        if (!note.is_locked) {
            titleEl.innerHTML = '<i class="bi bi-shield-lock-fill text-primary me-2"></i>Đặt mật khẩu riêng cho ghi chú';
            bodyEl.innerHTML = `
                <p class="text-secondary small mb-3">Nhập mật khẩu 2 lần để kích hoạt khóa bảo vệ riêng cho ghi chú này.</p>
                <div id="secModalAlert" class="alert alert-danger d-none py-2 small"></div>
                <form id="setNotePassForm">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mật khẩu mới</label>
                        <input type="password" id="newNotePass" class="form-control craft-input" placeholder="Tối thiểu 4 ký tự" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Xác nhận mật khẩu mới</label>
                        <input type="password" id="confirmNotePass" class="form-control craft-input" placeholder="Nhập lại mật khẩu" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-3">Bật bảo vệ mật khẩu</button>
                </form>
            `;

            document.getElementById('setNotePassForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const res = await fetch('api/note_security.php?action=set_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        note_id: note.id,
                        password: document.getElementById('newNotePass').value,
                        confirm_password: document.getElementById('confirmNotePass').value
                    })
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    const alertEl = document.getElementById('secModalAlert');
                    alertEl.textContent = data.error || 'Không thể đặt mật khẩu.';
                    alertEl.classList.remove('d-none');
                    return;
                }
                note.is_locked = true;
                note.is_unlocked = true;
                bootstrap.Modal.getInstance(document.getElementById('notePasswordModal'))?.hide();
                NoteApp.showToast(data.message);
                await NoteApp.loadNotes();
            });
        } else {
            titleEl.innerHTML = '<i class="bi bi-shield-check text-success me-2"></i>Quản lý mật khẩu ghi chú';
            bodyEl.innerHTML = `
                <div id="secModalAlert" class="alert alert-danger d-none py-2 small"></div>
                <div class="border rounded-3 p-3 mb-3">
                    <h4 class="h6 fw-bold mb-2">Đổi mật khẩu ghi chú</h4>
                    <form id="changeNotePassForm">
                        <div class="mb-2">
                            <input type="password" id="curNotePass" class="form-control form-control-sm craft-input" placeholder="Mật khẩu hiện tại" required>
                        </div>
                        <div class="mb-2">
                            <input type="password" id="chgNewPass" class="form-control form-control-sm craft-input" placeholder="Mật khẩu mới" required>
                        </div>
                        <div class="mb-2">
                            <input type="password" id="chgConfirmPass" class="form-control form-control-sm craft-input" placeholder="Nhập lại mật khẩu mới" required>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100 rounded-3">Cập nhật mật khẩu</button>
                    </form>
                </div>

                <div class="border border-danger-subtle rounded-3 p-3">
                    <h4 class="h6 fw-bold text-danger mb-2">Tắt bảo vệ mật khẩu</h4>
                    <p class="text-secondary small mb-2">Nhập lại mật khẩu hiện tại để xác nhận gỡ khóa bảo vệ.</p>
                    <form id="disableNotePassForm" class="d-flex gap-2">
                        <input type="password" id="disableCurPass" class="form-control form-control-sm craft-input" placeholder="Mật khẩu hiện tại" required>
                        <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">Tắt khóa</button>
                    </form>
                </div>
            `;

            document.getElementById('changeNotePassForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const res = await fetch('api/note_security.php?action=change_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        note_id: note.id,
                        current_password: document.getElementById('curNotePass').value,
                        new_password: document.getElementById('chgNewPass').value,
                        confirm_password: document.getElementById('chgConfirmPass').value
                    })
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    const alertEl = document.getElementById('secModalAlert');
                    alertEl.textContent = data.error || 'Lỗi đổi mật khẩu.';
                    alertEl.classList.remove('d-none');
                    return;
                }
                bootstrap.Modal.getInstance(document.getElementById('notePasswordModal'))?.hide();
                NoteApp.showToast(data.message);
            });

            document.getElementById('disableNotePassForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const res = await fetch('api/note_security.php?action=disable_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        note_id: note.id,
                        current_password: document.getElementById('disableCurPass').value
                    })
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    const alertEl = document.getElementById('secModalAlert');
                    alertEl.textContent = data.error || 'Không thể tắt khóa.';
                    alertEl.classList.remove('d-none');
                    return;
                }
                note.is_locked = false;
                bootstrap.Modal.getInstance(document.getElementById('notePasswordModal'))?.hide();
                NoteApp.showToast(data.message);
                await NoteApp.loadNotes();
            });
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('notePasswordModal')).show();
    },

    // Open Note Sharing Modal (Criterion #25 - Better Approach)
    async openShareDialogForCurrent() {
        if (!NoteApp.currentNote) {
            await NoteApp.performAutoSave();
        }
        const note = NoteApp.currentNote;
        if (!note) return;

        await this.renderShareModalContent(note.id);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('noteShareModal')).show();
    },

    async renderShareModalContent(noteId) {
        const bodyEl = document.getElementById('noteShareModalBody');
        const res = await fetch(`api/shares.php?note_id=${Number(noteId)}`);
        const data = await res.json();
        const shares = (res.ok && data.ok) ? (data.shares || []) : [];

        const rowsHtml = shares.length === 0
            ? '<div class="text-secondary small py-2">Ghi chú này chưa được chia sẻ cho ai.</div>'
            : shares.map(s => `
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom">
                    <div>
                        <div class="fw-semibold small">${NoteApp.escapeHtml(s.display_name)} (${NoteApp.escapeHtml(s.email)})</div>
                        <div class="text-secondary" style="font-size:0.74rem">Chia sẻ lúc: ${NoteApp.escapeHtml(s.shared_at)}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <select class="form-select form-select-sm" style="width:auto" onchange="SecurityShareApp.updateRecipientPermission(${Number(s.id)}, this.value, ${Number(noteId)})">
                            <option value="read" ${s.permission === 'read' ? 'selected' : ''}>Chỉ đọc (Read-only)</option>
                            <option value="edit" ${s.permission === 'edit' ? 'selected' : ''}>Chỉnh sửa (Edit)</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="SecurityShareApp.revokeRecipient(${Number(s.id)}, ${Number(noteId)})" title="Thu hồi quyền">
                            Thu hồi
                        </button>
                    </div>
                </div>
            `).join('');

        bodyEl.innerHTML = `
            <div id="shareModalAlert" class="alert alert-danger d-none py-2 small"></div>
            <form id="addShareForm" class="row g-2 mb-4">
                <div class="col-md-7">
                    <input type="text" id="shareEmailsInput" class="form-control craft-input" placeholder="Nhập 1 hoặc nhiều email (ngăn cách bằng dấu phẩy)" required>
                </div>
                <div class="col-md-3">
                    <select id="sharePermissionSelect" class="form-select craft-input">
                        <option value="read">Chỉ đọc (Read-only)</option>
                        <option value="edit">Chỉnh sửa (Edit)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 rounded-3">Chia sẻ</button>
                </div>
            </form>

            <h4 class="h6 fw-bold mb-2">Danh sách người nhận hiện tại (${shares.length})</h4>
            <div>${rowsHtml}</div>
        `;

        document.getElementById('addShareForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const rawEmails = document.getElementById('shareEmailsInput').value;
            const permission = document.getElementById('sharePermissionSelect').value;

            const postRes = await fetch('api/shares.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_id: noteId, emails: rawEmails, permission })
            });
            const postData = await postRes.json();
            if (!postRes.ok || !postData.ok) {
                const alertEl = document.getElementById('shareModalAlert');
                alertEl.textContent = postData.error || 'Không thể chia sẻ ghi chú.';
                alertEl.classList.remove('d-none');
                return;
            }
            NoteApp.showToast(postData.message);
            await this.renderShareModalContent(noteId);
            await NoteApp.loadNotes();
        });
    },

    async updateRecipientPermission(shareId, permission, noteId) {
        const res = await fetch('api/shares.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ share_id: shareId, permission })
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            NoteApp.showToast('Đã thay đổi quyền người nhận.');
            await this.renderShareModalContent(noteId);
        }
    },

    async revokeRecipient(shareId, noteId) {
        const res = await fetch('api/shares.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ share_id: shareId })
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            NoteApp.showToast('Đã thu hồi quyền chia sẻ.');
            await this.renderShareModalContent(noteId);
            await NoteApp.loadNotes();
        }
    }
};

window.SecurityShareApp = SecurityShareApp;
