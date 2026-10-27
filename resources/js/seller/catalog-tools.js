import { fetchJson } from '../fetch-json';

const debounce = (fn, wait = 350) => {
    let timer;

    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
};

/**
 * Leaf category type-ahead (wizard step 1, bulk upload): suggestions from the server, the
 * pick lands in a hidden input.
 */
export function categoryPicker({ url, selectedId = null, selectedLabel = '' }) {
    return {
        query: '',
        results: [],
        open: false,
        loading: false,
        selectedId,
        selectedLabel,

        init() {
            this.search = debounce(this.search.bind(this));
        },

        async search() {
            if (this.query.trim().length < 2) {
                this.results = [];
                this.open = false;
                return;
            }
            this.loading = true;
            try {
                const data = await fetchJson(`${url}?q=${encodeURIComponent(this.query.trim())}`);
                this.results = data.data;
                this.open = true;
            } catch {
                this.results = [];
            } finally {
                this.loading = false;
            }
        },

        pick(leaf) {
            this.selectedId = leaf.id;
            this.selectedLabel = leaf.breadcrumb;
            this.query = '';
            this.open = false;
        },
    };
}

/**
 * Inline edit of one inventory row (stock, threshold, status): PATCH on change.
 */
export function inventoryRow({ url, stock, threshold, status, available, reserved }) {
    return {
        stock,
        threshold,
        status,
        available,
        reserved,
        low: available <= threshold,
        saving: false,
        saved: false,
        error: null,

        async save(changes) {
            this.saving = true;
            this.saved = false;
            this.error = null;
            try {
                const data = await fetchJson(url, { method: 'PATCH', body: changes });
                const variant = data.variant;
                this.stock = variant.stock;
                this.threshold = variant.low_stock_threshold;
                this.status = variant.status;
                this.available = variant.available;
                this.reserved = variant.reserved;
                this.low = variant.low;
                this.saved = true;
                setTimeout(() => (this.saved = false), 2000);
            } catch (failure) {
                this.error = Object.values(failure.errors ?? {})[0]?.[0] ?? failure.message;
            } finally {
                this.saving = false;
            }
        },
    };
}

/**
 * Settlement estimate for one price (calculator page, wizard pricing rows).
 */
export function earningsEstimate({ url, category, gst = null, price = '', weight = '', initial = null }) {
    return {
        category,
        gst,
        price,
        weight,
        result: initial,
        error: null,
        loading: false,

        init() {
            this.refresh = debounce(this.refresh.bind(this), 400);
        },

        async refresh() {
            this.error = null;
            if (!this.category || !/^\d+(\.\d{1,2})?$/.test(String(this.price)) || Number(this.price) <= 0 || !(Number(this.weight) >= 1)) {
                this.result = null;
                return;
            }
            this.loading = true;
            try {
                const data = await fetchJson(url, {
                    method: 'POST',
                    body: { catalog_category_id: this.category, price: String(this.price), weight_grams: Number(this.weight), gst_percent: this.gst || null },
                });
                this.result = data.formatted;
            } catch (failure) {
                this.error = Object.values(failure.errors ?? {})[0]?.[0] ?? failure.message;
                this.result = null;
            } finally {
                this.loading = false;
            }
        },
    };
}

/**
 * Upload status polling on the history / detail pages: reload once processing ends.
 */
export function uploadStatus({ url, finished, interval = 3000 }) {
    return {
        finished,

        init() {
            if (!this.finished) {
                this.timer = setInterval(() => this.poll(), interval);
            }
        },

        async poll() {
            try {
                const data = await fetchJson(url);
                if (data.finished) {
                    clearInterval(this.timer);
                    window.location.reload();
                }
            } catch {
                clearInterval(this.timer);
            }
        },

        destroy() {
            clearInterval(this.timer);
        },
    };
}
