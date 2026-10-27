import { fetchJson } from '../fetch-json';

/**
 * A row of product cards fetched when it scrolls near the viewport (product page "Similar
 * products" / "More from this seller"). The endpoint answers { count, html }; an empty
 * answer hides the row (state "empty").
 */
export default ({ url }) => ({
    state: 'idle',
    observer: null,

    init() {
        if (!('IntersectionObserver' in window)) {
            this.load();
            return;
        }
        this.observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    this.observer.disconnect();
                    this.load();
                }
            },
            { rootMargin: '300px 0px' },
        );
        this.observer.observe(this.$el);
    },

    destroy() {
        this.observer?.disconnect();
    },

    async load() {
        this.state = 'loading';
        try {
            const data = await fetchJson(url);
            if (!Number(data.count)) {
                this.state = 'empty';
                return;
            }
            this.$refs.items.innerHTML = data.html ?? '';
            this.state = 'loaded';
        } catch {
            this.state = 'empty';
        }
    },
});
