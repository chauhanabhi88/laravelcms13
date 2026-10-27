import { prefersReducedMotion } from '../fetch-json';

const RECENT_KEY = 'recent_searches';
const RECENT_LIMIT = 8;

const readRecent = () => {
    try {
        const terms = JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]');

        return Array.isArray(terms) ? terms.filter((term) => typeof term === 'string') : [];
    } catch {
        return [];
    }
};

const writeRecent = (terms) => {
    try {
        localStorage.setItem(RECENT_KEY, JSON.stringify(terms.slice(0, RECENT_LIMIT)));
    } catch {
        /* storage unavailable (private mode) */
    }
};

/**
 * Header search box. Rotates the placeholder through category names and shows grouped
 * suggestions from the storefront autocomplete API (M06): categories, brands, products and
 * trending terms while typing (debounced, 2+ characters); recent searches (kept in this
 * browser) and trending terms on focus.
 */
export default ({ enabled = true, placeholders = [], suggestUrl = null, searchUrl = null, minLength = 2, query = '', labels = {} } = {}) => ({
    enabled,
    placeholders,
    suggestUrl,
    searchUrl,
    minLength,
    labels,
    index: 0,
    query,
    open: false,
    groups: [],
    trending: null,
    timer: null,
    debounce: null,
    controller: null,

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

    /** Flat list kept for templates that render a single list. */
    get suggestions() {
        return this.groups.flatMap((group) => group.items);
    },

    onFocus() {
        if (this.query.trim().length < this.minLength) {
            this.showIdle();
        } else {
            this.open = this.groups.length > 0;
        }
    },

    onInput() {
        clearTimeout(this.debounce);

        if (this.query.trim().length < this.minLength) {
            this.showIdle();

            return;
        }

        if (this.suggestUrl) {
            this.debounce = setTimeout(() => this.suggest(), 200);
        }
    },

    async suggest() {
        const term = this.query.trim();
        const data = await this.fetchGroups(term);

        if (data !== null && term === this.query.trim()) {
            this.groups = data;
            this.open = this.groups.length > 0;
        }
    },

    /**
     * Recent searches plus trending terms (fetched once per page).
     */
    async showIdle() {
        if (this.trending === null && this.suggestUrl) {
            this.trending = [];
            this.trending = (await this.fetchGroups('')) ?? [];
        }

        const recent = readRecent().map((term) => ({ label: term, sublabel: null, url: this.termUrl(term), type: 'recent' }));
        this.groups = [
            ...(recent.length ? [{ key: 'recent', label: this.labels.recent ?? '', items: recent, clearable: true }] : []),
            ...(this.trending ?? []),
        ];
        this.open = this.groups.length > 0 && this.query.trim().length < this.minLength;
    },

    async fetchGroups(term) {
        this.controller?.abort();
        this.controller = new AbortController();

        try {
            const url = new URL(this.suggestUrl, window.location.origin);
            url.searchParams.set('q', term);
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: this.controller.signal,
            });

            if (!response.ok) {
                return [];
            }

            const data = await response.json();

            return data.data?.groups ?? (Array.isArray(data.data) ? [{ key: 'results', label: '', items: data.data }] : []);
        } catch (error) {
            return error?.name === 'AbortError' ? null : [];
        }
    },

    clearRecent() {
        writeRecent([]);
        this.groups = this.groups.filter((group) => group.key !== 'recent');
        this.open = this.groups.length > 0;
    },

    remember(term) {
        const clean = term.trim().toLowerCase();
        if (clean) {
            writeRecent([clean, ...readRecent().filter((existing) => existing !== clean)]);
        }
    },

    termUrl(term) {
        if (!this.searchUrl) {
            return '#';
        }
        const url = new URL(this.searchUrl, window.location.origin);
        url.searchParams.set('q', term);

        return url.toString();
    },

    choose(item) {
        if (item.type === 'recent' || item.type === 'trending' || item.type === 'product') {
            this.remember(item.label);
        }
    },

    /** Arrow keys move through the suggestions. */
    move(step) {
        const options = [...this.$root.querySelectorAll('[role="option"]')];
        if (!options.length) {
            return;
        }
        this.open = true;
        const current = options.indexOf(document.activeElement);
        const next = current === -1 ? (step > 0 ? 0 : options.length - 1) : (current + step + options.length) % options.length;
        options[next].focus();
    },

    submit(event) {
        if (!this.enabled || !this.query.trim()) {
            event.preventDefault();

            return;
        }
        this.remember(this.query);
    },
});
