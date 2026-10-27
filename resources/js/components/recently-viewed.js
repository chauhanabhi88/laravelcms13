import { fetchJson } from '../fetch-json';

const STORAGE_KEY = 'rv';
const LIMIT = 20;

/**
 * Remember a viewed product for guests (newest first, at most 20). The product page (M07)
 * calls window.rememberRecentlyViewed(id); signed-in customers are recorded server side.
 */
export function rememberRecentlyViewed(id) {
    try {
        const ids = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]').filter((value) => String(value) !== String(id));
        localStorage.setItem(STORAGE_KEY, JSON.stringify([String(id), ...ids].slice(0, LIMIT)));
    } catch {
        /* storage unavailable (private mode) */
    }
}

/**
 * Home page "Recently viewed" row: fetches the cards and shows the row once there are
 * at least `min` products still on sale.
 */
export default ({ url, min = 3, signedIn = false } = {}) => ({
    count: 0,
    min,

    async init() {
        let ids = [];
        try {
            ids = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');
        } catch {
            ids = [];
        }

        if (!signedIn && ids.length < this.min) {
            return;
        }

        const target = new URL(url, window.location.origin);
        if (!signedIn) {
            target.searchParams.set('ids', ids.slice(0, LIMIT).join(','));
        }

        try {
            const data = await fetchJson(target.toString());
            this.$refs.items.innerHTML = data.html ?? '';
            this.count = Number(data.count ?? 0);
        } catch {
            this.count = 0;
        }
    },
});
