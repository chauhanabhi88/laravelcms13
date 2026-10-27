import { fetchJson, prefersReducedMotion } from '../fetch-json';

/**
 * Header search shell. Rotates the placeholder through category names and renders a
 * suggestions panel; the suggestions endpoint is wired by the search plan (M06).
 */
export default ({ enabled = true, placeholders = [], suggestUrl = null, minLength = 2, query = '' } = {}) => ({
    enabled,
    placeholders,
    suggestUrl,
    minLength,
    index: 0,
    query,
    open: false,
    suggestions: [],
    timer: null,
    debounce: null,

    init() {
        if (this.placeholders.length > 1 && !prefersReducedMotion()) {
            this.timer = setInterval(() => {
                if (!this.query) {
                    this.index = (this.index + 1) % this.placeholders.length;
                }
            }, 3000);
        }
    },

    destroy() {
        clearInterval(this.timer);
    },

    get placeholder() {
        return this.placeholders[this.index] ?? '';
    },

    onInput() {
        clearTimeout(this.debounce);
        if (!this.suggestUrl || this.query.trim().length < this.minLength) {
            this.suggestions = [];
            this.open = false;
            return;
        }
        this.debounce = setTimeout(() => this.suggest(), 250);
    },

    async suggest() {
        try {
            const url = new URL(this.suggestUrl, window.location.origin);
            url.searchParams.set('q', this.query.trim());
            const data = await fetchJson(url.toString());
            this.suggestions = data.data ?? data.suggestions ?? [];
            this.open = this.suggestions.length > 0;
        } catch {
            this.suggestions = [];
            this.open = false;
        }
    },

    submit(event) {
        if (!this.enabled || !this.query.trim()) {
            event.preventDefault();
        }
    },
});
