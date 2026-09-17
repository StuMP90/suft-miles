/**
 * Thin promise-based wrapper around IndexedDB for storing each visitor's
 * cumulative trips database (as a Blob) keyed by their chosen car/profile
 * nickname. This is the only place trip data is persisted - nothing here
 * ever leaves the browser except when explicitly POSTed to /api/report.php.
 */
(function () {
    const DB_NAME = 'surf4miles';
    const DB_VERSION = 1;
    const STORE_NAME = 'profiles';

    function openDb() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    db.createObjectStore(STORE_NAME, { keyPath: 'nickname' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    async function getProfile(nickname) {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readonly');
            const req = tx.objectStore(STORE_NAME).get(nickname);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => reject(req.error);
        });
    }

    async function putProfile(nickname, dbBlob) {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readwrite');
            tx.objectStore(STORE_NAME).put({ nickname, dbBlob, lastUpdated: Date.now() });
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }

    async function deleteProfile(nickname) {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readwrite');
            tx.objectStore(STORE_NAME).delete(nickname);
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }

    window.Surf4MilesStore = { getProfile, putProfile, deleteProfile };
})();
