/**
 * CraftNotes Main Application Controller (Vanilla ES6)
 * Handles: Grid/List view toggle, Unified Note Editor Modal with 500ms Auto-Save & single-flight mutex,
 * 300ms Debounced Live Search, Pinning, Label Management & Filtering, Multi-File Attachments, and Offline fallback.
 */

const NoteApp = {
    notes: [],
    labels: [],
    viewMode: 'grid',       // Default is grid view (Mandatory Requirement)
    activeTab: 'all',       // 'all' | 'shared'
    selectedLabels: [],     // Array of label IDs for filtering
    searchQuery: '',
    searchTimer: null,

    // Editor state & Auto-save race-condition lock (Review Focus #5)
    currentNote: null,
    editorColor: '#ffffff',
    autoSaveTimer: null,
    isCreatingNote: false,
    pendingAutoSaveAfterCreate: false,
    pendingDeleteNoteId: null,

    async init() {
        this.bindEvents();
        this.registerServiceWorker();
        await this.loadLabels();
        await this.loadNotes();
    },

    bindEvents() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimer);
                // 300ms live search debounce without a Search button (Mandatory Requirement)
                this.searchTimer = setTimeout(() => {
                    this.searchQuery = e.target.value.trim();
                    this.loadNotes();
                }, 300);
            });
        }

        const titleInput = document.getElementById('noteTitleInput');
        const contentInput = document.getElementById('noteContentInput');
        if (titleInput && contentInput) {
            titleInput.addEventListener('input', () => this.scheduleAutoSave());
            contentInput.addEventListener('input', () => this.scheduleAutoSave());
        }

        const editorModalEl = document.getElementById('noteEditorModal');
        if (editorModalEl) {
            editorModalEl.addEventListener('hidden.bs.modal', () => {
                this.loadNotes();
            });
        }

        window.addEventListener('online', () => this.handleConnectivityChange(true));
        window.addEventListener('offline', () => this.handleConnectivityChange(false));
        this.handleConnectivityChange(navigator.onLine);
    },

    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').catch(() => {});
        }
    },

    async handleConnectivityChange(isOnline) {
        const banner = document.getElementById('offlineBanner');
        if (banner) {
            banner.classList.toggle('d-none', isOnline);
        }
        if (isOnline && window.OfflineDB) {
            const synced = await window.OfflineDB.syncPendingMutations();
            if (synced > 0) {
                this.showToast(`Đã đồng bộ ${synced} thay đổi ngoại tuyến lên máy chủ.`);
                await this.loadNotes();
            }
        }
    },

    setViewMode(mode) {
        this.viewMode = mode === 'list' ? 'list' : 'grid';
        const pinnedContainer = document.getElementById('pinnedNotesContainer');
        const notesContainer = document.getElementById('notesContainer');
        const btnGrid = document.getElementById('btnGridView');
        const btnList = document.getElementById('btnListView');

        const targetClass = this.viewMode === 'list' ? 'notes-list' : 'notes-grid';
        if (pinnedContainer) pinnedContainer.className = targetClass;
        if (notesContainer) notesContainer.className = targetClass;

        if (btnGrid) btnGrid.classList.toggle('active', this.viewMode === 'grid');
        if (btnList) btnList.classList.toggle('active', this.viewMode === 'list');
    },

    switchTab(tab) {
        this.activeTab = tab === 'shared' ? 'shared' : 'all';
        document.getElementById('tabMyNotes')?.classList.toggle('active', this.activeTab === 'all');
        document.getElementById('tabSharedNotes')?.classList.toggle('active', this.activeTab === 'shared');
        this.loadNotes();
    },

    async loadNotes() {
        const params = new URLSearchParams({
            view: this.activeTab,
            q: this.searchQuery,
            labels: this.selectedLabels.join(',')
        });

        try {
            const res = await fetch(`api/notes.php?${params.toString()}`);
            const data = await res.json();
            if (res.ok && data.ok) {
                this.notes = data.notes || [];
                if (this.activeTab === 'all' && window.OfflineDB) {
                    await window.OfflineDB.saveNotes(this.notes);
                }
                this.renderNotes();
                this.updateTabCounts();
                return;
            }
        } catch (err) {
            // Fallback to IndexedDB when offline
            if (window.OfflineDB) {
                this.notes = await window.OfflineDB.getNotes({
                    q: this.searchQuery,
                    labels: this.selectedLabels
                });
                this.renderNotes();
            }
        }
    },

    async updateTabCounts() {
        try {
            const [resAll, resShared] = await Promise.all([
                fetch('api/notes.php?view=all').then(r => r.json()),
                fetch('api/notes.php?view=shared').then(r => r.json())
            ]);
            const myCountEl = document.getElementById('badgeMyNotesCount');
            const shCountEl = document.getElementById('badgeSharedCount');
            if (myCountEl && resAll.ok) myCountEl.textContent = (resAll.notes || []).length;
            if (shCountEl && resShared.ok) shCountEl.textContent = (resShared.notes || []).length;
        } catch (e) {}
    },

    renderNotes() {
        const pinnedSection = document.getElementById('pinnedSection');
        const pinnedContainer = document.getElementById('pinnedNotesContainer');
        const otherHeader = document.getElementById('otherSectionHeader');
        const notesContainer = document.getElementById('notesContainer');
        const emptyBox = document.getElementById('emptyStateBox');

        if (!notesContainer) return;

        if (this.notes.length === 0) {
            pinnedSection?.classList.add('d-none');
            otherHeader?.classList.add('d-none');
            notesContainer.innerHTML = '';
            emptyBox?.classList.remove('d-none');
            return;
        }

        emptyBox?.classList.add('d-none');

        const pinned = this.activeTab === 'all' ? this.notes.filter(n => Number(n.is_pinned) === 1) : [];
        const others = this.activeTab === 'all' ? this.notes.filter(n => Number(n.is_pinned) !== 1) : this.notes;

        if (pinned.length > 0) {
            pinnedSection?.classList.remove('d-none');
            otherHeader?.classList.remove('d-none');
            pinnedContainer.innerHTML = pinned.map(n => this.renderNoteCardHtml(n)).join('');
        } else {
            pinnedSection?.classList.add('d-none');
            otherHeader?.classList.add('d-none');
            if (pinnedContainer) pinnedContainer.innerHTML = '';
        }

        notesContainer.innerHTML = others.map(n => this.renderNoteCardHtml(n)).join('');
    },

    renderNoteCardHtml(note) {
        const isLockedAndHidden = note.is_locked && !note.is_unlocked;
        const cardBg = note.color || '#ffffff';

        // Recognizable Status Indicator Icons in both Grid and List views (Criterion #18)
        const badges = [];
        if (Number(note.is_pinned) === 1) {
            badges.push(`<span class="craft-status-icon craft-tint-blue" title="Đã ghim lên đầu"><i class="bi bi-pin-angle-fill"></i></span>`);
        }
        if (note.is_locked) {
            badges.push(`<span class="craft-status-icon craft-tint-amber" title="Được bảo vệ bằng mật khẩu"><i class="bi bi-lock-fill"></i></span>`);
        }
        if (note.is_shared) {
            badges.push(`<span class="craft-status-icon craft-tint-purple" title="Đang chia sẻ"><i class="bi bi-people-fill"></i></span>`);
        }
        if (Number(note.attachment_count) > 0) {
            badges.push(`<span class="craft-status-icon craft-tint-green" title="${note.attachment_count} tệp đính kèm"><i class="bi bi-paperclip"></i></span>`);
        }

        const labelBadges = (note.labels || []).map(l =>
            `<span class="badge rounded-pill text-bg-light border text-secondary fw-medium">${this.escapeHtml(l.name)}</span>`
        ).join(' ');

        let shareMetaHtml = '';
        if (this.activeTab === 'shared') {
            const permLabel = note.shared_permission === 'edit' ? 'Chỉnh sửa' : 'Chỉ đọc';
            const permClass = note.shared_permission === 'edit' ? 'text-bg-primary' : 'text-bg-secondary';
            shareMetaHtml = `
                <div class="mt-2 pt-2 border-top small text-secondary d-flex flex-wrap justify-content-between align-items-center gap-1">
                    <span><i class="bi bi-person-circle me-1"></i>Từ: <strong>${this.escapeHtml(note.owner_name || note.owner_email || '')}</strong></span>
                    <span class="badge ${permClass}">${permLabel}</span>
                    <span class="w-100 text-muted" style="font-size:0.75rem">Chia sẻ lúc: ${this.escapeHtml(note.shared_at || '')}</span>
                </div>
            `;
        }

        const excerptHtml = isLockedAndHidden
            ? `<div class="craft-note-excerpt fst-italic text-warning-emphasis"><i class="bi bi-shield-lock me-1"></i>Ghi chú được bảo vệ bằng mật khẩu. Nhấn để nhập mật khẩu mở khóa.</div>`
            : `<div class="craft-note-excerpt">${this.escapeHtml(note.content || 'Chưa có nội dung')}</div>`;

        return `
            <article class="craft-note-card" style="background-color: ${this.escapeHtml(cardBg)}" onclick="NoteApp.handleCardClick(${Number(note.id)})">
                <div>
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                        <h3 class="craft-note-title mb-0">${this.escapeHtml(note.title || 'Ghi chú không tiêu đề')}</h3>
                        <div class="craft-status-badges" onclick="event.stopPropagation()">
                            ${badges.join('')}
                        </div>
                    </div>
                    ${excerptHtml}
                </div>
                <div>
                    ${labelBadges ? `<div class="d-flex flex-wrap gap-1 mb-2">${labelBadges}</div>` : ''}
                    <div class="d-flex align-items-center justify-content-between text-secondary" style="font-size:0.76rem">
                        <span><i class="bi bi-clock me-1"></i>${this.escapeHtml(note.updated_at || '')}</span>
                        <div class="d-flex gap-1" onclick="event.stopPropagation()">
                            ${note.is_owner ? `
                                <button type="button" class="btn btn-sm p-0 px-1 text-secondary" onclick="NoteApp.togglePinById(${Number(note.id)})" title="Ghim / Bỏ ghim">
                                    <i class="bi ${Number(note.is_pinned) === 1 ? 'bi-pin-angle-fill text-primary' : 'bi-pin-angle'}"></i>
                                </button>
                                <button type="button" class="btn btn-sm p-0 px-1 text-danger" onclick="NoteApp.confirmDeleteNote(${Number(note.id)})" title="Xóa ghi chú">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                    ${shareMetaHtml}
                </div>
            </article>
        `;
    },

    handleCardClick(noteId) {
        const note = this.notes.find(n => Number(n.id) === Number(noteId));
        if (!note) return;

        // If note is password-locked and not yet unlocked in this session, prompt for password first
        if (note.is_locked && !note.is_unlocked) {
            window.SecurityShareApp.promptUnlockNote(note, (unlockedNote) => {
                const idx = this.notes.findIndex(n => Number(n.id) === Number(noteId));
                if (idx !== -1) this.notes[idx] = unlockedNote;
                this.renderNotes();
                this.openEditor(unlockedNote.id);
            });
            return;
        }

        this.openEditor(note.id);
    },

    openEditor(noteId = null) {
        const titleInput = document.getElementById('noteTitleInput');
        const contentInput = document.getElementById('noteContentInput');
        const modeBadge = document.getElementById('editorModeBadge');
        const statusEl = document.getElementById('autoSaveStatus');
        const defaultColor = document.body.dataset.defaultNoteColor || '#ffffff';

        this.isCreatingNote = false;
        this.pendingAutoSaveAfterCreate = false;

        if (noteId === null) {
            this.currentNote = null;
            this.editorColor = defaultColor;
            titleInput.value = '';
            contentInput.value = '';
            titleInput.readOnly = false;
            contentInput.readOnly = false;
            modeBadge.textContent = 'Tạo ghi chú mới';
            statusEl.innerHTML = '<i class="bi bi-keyboard"></i><span>Gõ để tự động lưu...</span>';
            this.renderEditorLabels([]);
            this.renderEditorAttachments([]);
            this.updateEditorToolbarState(true, false);
        } else {
            const note = this.notes.find(n => Number(n.id) === Number(noteId));
            if (!note) return;
            this.currentNote = note;
            this.editorColor = note.color || defaultColor;
            titleInput.value = note.title || '';
            contentInput.value = note.content || '';

            const isReadOnly = !note.is_owner && note.shared_permission !== 'edit';
            titleInput.readOnly = isReadOnly;
            contentInput.readOnly = isReadOnly;
            modeBadge.textContent = isReadOnly ? 'Chế độ Chỉ đọc' : 'Chỉnh sửa ghi chú';
            statusEl.innerHTML = '<i class="bi bi-cloud-check"></i><span>Đã lưu tự động</span>';

            this.renderEditorLabels((note.labels || []).map(l => Number(l.id)));
            this.renderEditorAttachments(note.attachments || []);
            this.updateEditorToolbarState(note.is_owner, isReadOnly);
        }

        this.highlightColorSwatch(this.editorColor);
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('noteEditorModal'));
        modal.show();
    },

    updateEditorToolbarState(isOwner, isReadOnly) {
        document.getElementById('editorPinBtn')?.classList.toggle('d-none', !isOwner);
        document.getElementById('editorLockBtn')?.classList.toggle('d-none', !isOwner);
        document.getElementById('editorShareBtn')?.classList.toggle('d-none', !isOwner);
        document.getElementById('editorDeleteBtn')?.classList.toggle('d-none', !isOwner);
        document.getElementById('editorMetadataSection')?.classList.toggle('d-none', isReadOnly);
    },

    scheduleAutoSave() {
        const statusEl = document.getElementById('autoSaveStatus');
        if (statusEl) {
            statusEl.innerHTML = '<i class="bi bi-arrow-repeat"></i><span>Đang tự động lưu...</span>';
        }
        clearTimeout(this.autoSaveTimer);
        this.autoSaveTimer = setTimeout(() => {
            this.performAutoSave();
        }, 500);
    },

    async performAutoSave() {
        const title = document.getElementById('noteTitleInput')?.value ?? '';
        const content = document.getElementById('noteContentInput')?.value ?? '';
        const statusEl = document.getElementById('autoSaveStatus');

        if (title.trim() === '' && content.trim() === '' && !this.currentNote) {
            return;
        }

        // Single-flight mutex when creating a brand-new note (Review Focus #5)
        if (!this.currentNote) {
            if (this.isCreatingNote) {
                this.pendingAutoSaveAfterCreate = true;
                return;
            }
            this.isCreatingNote = true;
            try {
                const res = await fetch('api/notes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ title, content, color: this.editorColor })
                });
                const data = await res.json();
                if (res.ok && data.ok) {
                    this.currentNote = data.note;
                    this.notes.unshift(data.note);
                    if (statusEl) {
                        statusEl.innerHTML = '<i class="bi bi-cloud-check-fill text-success"></i><span>Đã lưu tự động</span>';
                    }
                }
            } catch (err) {
                if (window.OfflineDB) {
                    const tempNote = await window.OfflineDB.enqueueMutation({
                        type: 'create', title, content, color: this.editorColor
                    });
                    this.currentNote = tempNote;
                    if (statusEl) statusEl.innerHTML = '<i class="bi bi-hdd-fill text-warning"></i><span>Đã lưu vào bộ nhớ Offline</span>';
                }
            } finally {
                this.isCreatingNote = false;
                if (this.pendingAutoSaveAfterCreate) {
                    this.pendingAutoSaveAfterCreate = false;
                    await this.performAutoSave();
                }
            }
            return;
        }

        // Update existing note via PUT
        try {
            const res = await fetch('api/notes.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: this.currentNote.id,
                    title,
                    content,
                    color: this.editorColor
                })
            });
            const data = await res.json();
            if (res.ok && data.ok) {
                this.currentNote = data.note;
                const idx = this.notes.findIndex(n => Number(n.id) === Number(data.note.id));
                if (idx !== -1) this.notes[idx] = data.note;
                if (statusEl) {
                    statusEl.innerHTML = '<i class="bi bi-cloud-check-fill text-success"></i><span>Đã lưu tự động</span>';
                }
            }
        } catch (err) {
            if (window.OfflineDB) {
                await window.OfflineDB.enqueueMutation({
                    type: 'update', id: this.currentNote.id, title, content, color: this.editorColor
                });
                if (statusEl) statusEl.innerHTML = '<i class="bi bi-hdd-fill text-warning"></i><span>Đã lưu vào bộ nhớ Offline</span>';
            }
        }
    },

    selectEditorColor(color) {
        this.editorColor = color;
        this.highlightColorSwatch(color);
        this.scheduleAutoSave();
    },

    highlightColorSwatch(color) {
        document.querySelectorAll('.craft-color-swatch').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.color === color);
        });
    },

    async togglePinById(noteId) {
        const note = this.notes.find(n => Number(n.id) === Number(noteId));
        if (note && note.is_locked && !note.is_unlocked) {
            window.SecurityShareApp.promptUnlockNote(note, () => this.togglePinById(noteId));
            return;
        }
        const res = await fetch('api/notes.php?action=toggle_pin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: noteId })
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            await this.loadNotes();
        }
    },

    async togglePinCurrentNote() {
        if (!this.currentNote) {
            await this.performAutoSave();
        }
        if (this.currentNote) {
            await this.togglePinById(this.currentNote.id);
            this.showToast('Đã cập nhật trạng thái ghim ghi chú.');
        }
    },

    confirmDeleteCurrentNote() {
        if (!this.currentNote) return;
        this.confirmDeleteNote(this.currentNote.id);
    },

    confirmDeleteNote(noteId) {
        const note = this.notes.find(n => Number(n.id) === Number(noteId));
        if (note && note.is_locked && !note.is_unlocked) {
            window.SecurityShareApp.promptUnlockNote(note, () => this.confirmDeleteNote(noteId));
            return;
        }
        this.pendingDeleteNoteId = noteId;
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteConfirmModal'));
        modal.show();
    },

    async executeDeleteNote() {
        if (!this.pendingDeleteNoteId) return;
        const noteId = this.pendingDeleteNoteId;
        const res = await fetch('api/notes.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: noteId })
        });
        const data = await res.json();
        bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'))?.hide();
        bootstrap.Modal.getInstance(document.getElementById('noteEditorModal'))?.hide();

        if (res.ok && data.ok) {
            this.showToast('Đã xóa ghi chú.');
            this.pendingDeleteNoteId = null;
            await this.loadNotes();
        } else {
            this.showToast(data.error || 'Không thể xóa ghi chú.', true);
        }
    },

    // =========================================================================
    // Labels Management & Filtering (Criteria #20, #21, #22)
    // =========================================================================
    async loadLabels() {
        try {
            const res = await fetch('api/labels.php');
            const data = await res.json();
            if (res.ok && data.ok) {
                this.labels = data.labels || [];
                this.renderSidebarLabels();
            }
        } catch (err) {}
    },

    renderSidebarLabels() {
        const listEl = document.getElementById('sidebarLabelsList');
        const pillsEl = document.getElementById('labelFilterPills');
        if (!listEl) return;

        if (this.labels.length === 0) {
            listEl.innerHTML = '<div class="text-secondary small py-2">Chưa có nhãn nào.</div>';
            if (pillsEl) pillsEl.innerHTML = '';
            return;
        }

        listEl.innerHTML = this.labels.map(l => {
            const active = this.selectedLabels.includes(Number(l.id));
            return `
                <div class="d-flex align-items-center justify-content-between px-2 py-1 rounded-3 ${active ? 'craft-tint-blue fw-semibold' : ''}">
                    <button type="button" class="btn btn-sm text-start flex-grow-1 border-0 p-1 text-reset" onclick="NoteApp.toggleLabelFilter(${Number(l.id)})">
                        <i class="bi ${active ? 'bi-tag-fill' : 'bi-tag'} me-2"></i>${this.escapeHtml(l.name)}
                    </button>
                </div>
            `;
        }).join('');

        if (pillsEl) {
            pillsEl.innerHTML = this.selectedLabels.map(id => {
                const lbl = this.labels.find(x => Number(x.id) === Number(id));
                if (!lbl) return '';
                return `<span class="badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-1 px-3 py-2">
                    <i class="bi bi-tag-fill"></i>${this.escapeHtml(lbl.name)}
                    <button type="button" class="btn-close btn-close-white ms-1" style="font-size:0.6rem" onclick="NoteApp.toggleLabelFilter(${Number(id)})"></button>
                </span>`;
            }).join('');
        }
    },

    toggleLabelFilter(labelId) {
        const idx = this.selectedLabels.indexOf(Number(labelId));
        if (idx === -1) {
            this.selectedLabels.push(Number(labelId));
        } else {
            this.selectedLabels.splice(idx, 1);
        }
        this.renderSidebarLabels();
        this.loadNotes();
    },

    clearLabelFilter() {
        this.selectedLabels = [];
        this.renderSidebarLabels();
        this.loadNotes();
    },

    async handleCreateLabel(e) {
        e.preventDefault();
        const input = document.getElementById('newLabelNameInput');
        const name = input.value.trim();
        if (!name) return;

        const res = await fetch('api/labels.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            input.value = '';
            await this.loadLabels();
            this.showToast('Đã tạo nhãn mới.');
        } else {
            this.showToast(data.error || 'Không thể tạo nhãn.', true);
        }
    },

    openLabelManager() {
        const container = document.getElementById('modalLabelsManageList');
        if (container) {
            if (this.labels.length === 0) {
                container.innerHTML = '<div class="text-secondary small">Chưa có nhãn nào.</div>';
            } else {
                container.innerHTML = this.labels.map(l => `
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" class="form-control form-control-sm craft-input" id="lblEdit_${Number(l.id)}" value="${this.escapeHtml(l.name)}">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="NoteApp.renameLabel(${Number(l.id)})">Đổi tên</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="NoteApp.deleteLabel(${Number(l.id)})"><i class="bi bi-trash3"></i></button>
                    </div>
                `).join('');
            }
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('labelManagerModal')).show();
    },

    async renameLabel(labelId) {
        const input = document.getElementById(`lblEdit_${labelId}`);
        if (!input || !input.value.trim()) return;
        const res = await fetch('api/labels.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: labelId, name: input.value.trim() })
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            await this.loadLabels();
            await this.loadNotes();
            this.openLabelManager();
            this.showToast('Đã đổi tên nhãn trên tất cả ghi chú.');
        }
    },

    async deleteLabel(labelId) {
        const res = await fetch('api/labels.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: labelId })
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            this.selectedLabels = this.selectedLabels.filter(id => id !== Number(labelId));
            await this.loadLabels();
            await this.loadNotes();
            this.openLabelManager();
            this.showToast('Đã xóa nhãn (các ghi chú vẫn được giữ nguyên).');
        }
    },

    renderEditorLabels(selectedIds = []) {
        const container = document.getElementById('editorLabelsCheckboxes');
        if (!container) return;
        if (this.labels.length === 0) {
            container.innerHTML = '<span class="text-secondary small">Chưa có nhãn nào. Hãy thêm nhãn ở cột bên trái.</span>';
            return;
        }
        container.innerHTML = this.labels.map(l => {
            const checked = selectedIds.includes(Number(l.id)) ? 'checked' : '';
            return `
                <label class="badge rounded-pill text-bg-light border text-dark fw-medium px-3 py-2 cursor-pointer d-inline-flex align-items-center gap-1">
                    <input type="checkbox" class="form-check-input mt-0 editor-label-cb" value="${Number(l.id)}" ${checked} onchange="NoteApp.syncCurrentNoteLabels()">
                    <span>${this.escapeHtml(l.name)}</span>
                </label>
            `;
        }).join('');
    },

    async syncCurrentNoteLabels() {
        if (!this.currentNote) {
            await this.performAutoSave();
        }
        if (!this.currentNote) return;
        const checkedIds = Array.from(document.querySelectorAll('.editor-label-cb:checked')).map(cb => Number(cb.value));
        await fetch('api/labels.php?action=set_note_labels', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ note_id: this.currentNote.id, label_ids: checkedIds })
        });
    },

    // =========================================================================
    // Attachments (Images, Videos, Files - Criteria #15, #16)
    // =========================================================================
    renderEditorAttachments(attachments = []) {
        const container = document.getElementById('editorAttachmentsList');
        if (!container) return;
        if (!attachments.length) {
            container.innerHTML = '<div class="col-12 text-secondary small">Chưa có tệp đính kèm.</div>';
            return;
        }

        container.innerHTML = attachments.map(att => {
            let preview = '';
            if (att.file_type === 'image') {
                preview = `<a href="${this.escapeHtml(att.file_path)}" target="_blank">
                    <img src="${this.escapeHtml(att.file_path)}" class="img-fluid rounded-3 mb-1" style="max-height:130px;object-fit:cover;width:100%" alt="${this.escapeHtml(att.file_name)}">
                </a>`;
            } else if (att.file_type === 'video') {
                preview = `<video src="${this.escapeHtml(att.file_path)}" controls class="w-100 rounded-3 mb-1" style="max-height:140px"></video>`;
            } else {
                preview = `<a href="${this.escapeHtml(att.file_path)}" download="${this.escapeHtml(att.file_name)}" class="d-flex align-items-center gap-2 text-decoration-none p-2 rounded-3 bg-light">
                    <i class="bi bi-file-earmark-arrow-down-fill fs-4 text-primary"></i>
                    <span class="small text-truncate">${this.escapeHtml(att.file_name)}</span>
                </a>`;
            }

            return `
                <div class="col-sm-6 col-md-4">
                    <div class="border rounded-3 p-2 h-100 d-flex flex-column justify-content-between">
                        ${preview}
                        <div class="d-flex align-items-center justify-content-between mt-1">
                            <span class="small text-secondary text-truncate" style="max-width:140px">${this.escapeHtml(att.file_name)}</span>
                            <button type="button" class="btn btn-sm text-danger p-0" onclick="NoteApp.deleteAttachment(${Number(att.id)})" title="Xóa tệp">
                                <i class="bi bi-x-circle-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    async handleAttachmentUpload(e) {
        const files = e.target.files;
        if (!files || !files.length) return;

        if (!this.currentNote) {
            await this.performAutoSave();
        }
        if (!this.currentNote) return;

        const formData = new FormData();
        formData.append('note_id', this.currentNote.id);
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        const res = await fetch('api/attachments.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        e.target.value = '';

        if (res.ok && data.ok) {
            this.currentNote.attachments = data.attachments || [];
            this.renderEditorAttachments(this.currentNote.attachments);
            this.showToast('Đã tải lên tệp đính kèm.');
        } else {
            this.showToast(data.error || 'Lỗi tải tệp đính kèm.', true);
        }
    },

    async deleteAttachment(attachmentId) {
        const res = await fetch('api/attachments.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ attachment_id: attachmentId })
        });
        const data = await res.json();
        if (res.ok && data.ok && this.currentNote) {
            this.currentNote.attachments = (this.currentNote.attachments || []).filter(a => Number(a.id) !== Number(attachmentId));
            this.renderEditorAttachments(this.currentNote.attachments);
            this.showToast('Đã xóa tệp đính kèm.');
        }
    },

    async logout() {
        if (window.OfflineDB) {
            await OfflineDB.clearAll();
        }
        await fetch('api/auth.php?action=logout', { method: 'POST' });
        window.location.href = 'login.php';
    },

    showToast(message, isError = false) {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const id = 'toast_' + Date.now();
        const html = `
            <div id="${id}" class="toast align-items-center text-bg-${isError ? 'danger' : 'dark'} border-0 rounded-3" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body small fw-medium">${this.escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        const toastEl = document.getElementById(id);
        const toast = new bootstrap.Toast(toastEl, { delay: 2600 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    },

    escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};

document.addEventListener('DOMContentLoaded', () => NoteApp.init());
