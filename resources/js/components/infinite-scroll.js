import { fetchJson } from '../fetch-json';

/**
 * "Load more" + infinite scroll for server-rendered card grids. The endpoint
 * returns { html, next_page_url } when requested with Accept: application/json.
 */
export default ({ nextUrl = null, auto = true } = {}) => ({
    nextUrl,
    loading: false,
    failed: false,
    observer: null,

    init() {
        if (!auto || !('IntersectionObserver' in window) || !this.$refs.sentinel) {
            return;
        }
        this.observer = new IntersectionObserver(
            (entries) => entries.some((entry) => entry.isIntersecting) && this.load(),
            { rootMargin: '400px 0px' },
        );
        this.observer.observe(this.$refs.sentinel);
    },

    destroy() {
        this.observer?.disconnect();
    },

    async load() {
        if (!this.nextUrl || this.loading) {
            return;
        }
        this.loading = true;
        this.failed = false;

        try {
            const data = await fetchJson(this.nextUrl);
            this.$refs.items.insertAdjacentHTML('beforeend', data.html);
            this.nextUrl = data.next_page_url;
            if (!this.nextUrl) {
                this.observer?.disconnect();
            }
        } catch {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },
});
