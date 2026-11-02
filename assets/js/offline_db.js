/**
 * CraftNotes IndexedDB Local Database & Online Synchronization Layer (Criterion #31)
 * Stores unlocked notes locally for offline viewing/searching and queues offline creates/edits
 * in `sync_queue` to replay automatically when internet connection is restored.
 */

const OfflineDB = {
    dbName: 'CraftNotesLocalDB',
    dbVersion: 1,
    dbPromise: null,

    open() {
        if (this.dbPromise) return this.dbPromise;
        this.dbPromise = new Promise((resolve, reject) => {
            if (!window.indexedDB) {
                reject(new Error('IndexedDB not supported'));
                return;
            }
            const request = window.indexedDB.open(this.dbName, this.dbVersion);
            request.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains('notes')) {
                    db.createObjectStore('notes', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('labels')) {
                    db.createObjectStore('labels', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('sync_queue')) {
                    db.createObjectStore('sync_queue', { keyPath: 'queue_id', autoIncrement: true });
                }
            };
            request.onsuccess = (e) => resolve(e.target.result);
            request.onerror = (e) => reject(e.target.error);
        });
        return this.dbPromise;
    },

    async saveNotes(notes) {
        try {
            const db = await this.open();
            const tx = db.transaction('notes', 'readwrite');
            const store = tx.objectStore('notes');
            store.clear();
            for (const note of notes) {
                // Never persist secret content of password-protected notes to disk in IndexedDB
                const safeCopy = { ...note };
                if (safeCopy.is_locked) {
                    safeCopy.content = '';
                    safeCopy.attachments = [];
                    safeCopy.is_unlocked = false;
                }
                store.put(safeCopy);
            }
        } catch (err) {}
    },

    async clearAll() {
        try {
            const db = await this.open();
            const tx = db.transaction(['notes', 'labels', 'sync_queue'], 'readwrite');
            tx.objectStore('notes').clear();
            tx.objectStore('labels').clear();
            tx.objectStore('sync_queue').clear();
        } catch (err) {}
    },

    async getNotes(filter = {}) {
        try {
            const db = await this.open();
            const allNotes = await new Promise((resolve, reject) => {
                const tx = db.transaction('notes', 'readonly');
                const req = tx.objectStore('notes').getAll();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            });

            const q = (filter.q || '').trim().toLowerCase();
            const labelIds = (filter.labels || []).map(Number);

            return allNotes
                .filter((n) => {
                    if (labelIds.length > 0) {
                        const nLabels = (n.labels || []).map((l) => Number(l.id));
                        if (!labelIds.some((id) => nLabels.includes(id))) return false;
                    }
                    if (q !== '') {
                        const inTitle = (n.title || '').toLowerCase().includes(q);
                        const inContent = (!n.is_locked || n.is_unlocked) && (n.content || '').toLowerCase().includes(q);
                        if (!inTitle && !inContent) return false;
                    }
                    return true;
                })
                .sort((a, b) => Number(b.is_pinned) - Number(a.is_pinned));
        } catch (err) {
            return [];
        }
    },

    async enqueueMutation(mutation) {
        const db = await this.open();
        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
        let localNote = null;

        if (mutation.type === 'create') {
            localNote = {
                id: -Date.now(),
                is_owner: true,
                title: mutation.title || '',
                content: mutation.content || '',
                color: mutation.color || '#ffffff',
                is_pinned: 0,
                is_locked: false,
                is_unlocked: true,
                labels: [],
                attachments: [],
                updated_at: now
            };
        } else if (mutation.type === 'update') {
            localNote = {
                id: mutation.id,
                is_owner: true,
                title: mutation.title || '',
                content: mutation.content || '',
                color: mutation.color || '#ffffff',
                updated_at: now
            };
        }

        const tx = db.transaction(['notes', 'sync_queue'], 'readwrite');
        if (localNote) {
            tx.objectStore('notes').put(localNote);
        }
        tx.objectStore('sync_queue').add({
            ...mutation,
            queued_at: now
        });

        return localNote;
    },

    async syncPendingMutations() {
        try {
            const db = await this.open();
            const items = await new Promise((resolve, reject) => {
                const tx = db.transaction('sync_queue', 'readonly');
                const req = tx.objectStore('sync_queue').getAll();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            });

            if (!items.length) return 0;

            let syncedCount = 0;
            for (const item of items) {
                try {
                    if (item.type === 'create') {
                        const res = await fetch('api/notes.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                title: item.title,
                                content: item.content,
                                color: item.color
                            })
                        });
                        if (res.ok) syncedCount++;
                    } else if (item.type === 'update' && Number(item.id) > 0) {
                        const res = await fetch('api/notes.php', {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                id: item.id,
                                title: item.title,
                                content: item.content,
                                color: item.color
                            })
                        });
                        if (res.ok) syncedCount++;
                    }
                } catch (e) {
                    break;
                }
            }

            if (syncedCount > 0) {
                const clearTx = db.transaction('sync_queue', 'readwrite');
                clearTx.objectStore('sync_queue').clear();
            }
            return syncedCount;
        } catch (err) {
            return 0;
        }
    }
};

window.OfflineDB = OfflineDB;
