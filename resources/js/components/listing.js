import { fetchJson, prefersReducedMotion } from '../fetch-json';

/**
 * Storefront listing (M06): filters, sort, chips and pagination links re-query the page in
 * place through ?partial=1 and push the URL, so filter state is shareable and survives
 * back / forward. The grid loads the next page through ?partial=grid when its sentinel
 * scrolls into view. Without JS the same links and the GET filter form work as plain pages.
 */
export default ({ nextUrl = null, total = null, filterCount = 0, term = null, clickUrl = null } = {}) => ({
    nextUrl,
    total,
    filterCount,
    term,
    clickUrl,
    loading: false,
    loadingMore: false,
    loadFailed: false,
    enhanced: true,
    showTop: false,
    sheetTab: null,
    observer: null,
    controller: null,
    onScroll: null,
    onDocumentClick: null,

    init() {
        history.replaceState({ ...(history.state ?? {}), listing: true }, '', window.location.href);

        this.onScroll = () => {
            this.showTop = window.scrollY > window.innerHeight * 2;
        };
        window.addEventListener('scroll', this.onScroll, { passive: true });

        // The filter and sort sheets are teleported to <body>, so links are caught at the document.
        this.onDocumentClick = (event) => this.handleClick(event);
        document.addEventListener('click', this.onDocumentClick);

        this.observe();
    },

    destroy() {
        window.removeEventListener('scroll', this.onScroll);
        document.removeEventListener('click', this.onDocumentClick);
        this.observer?.disconnect();
    },

    handleClick(event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const link = event.target.closest('a[data-listing-link]');
        if (link && this.owns(link)) {
            event.preventDefault();
            this.navigate(link.href);

            return;
        }

        const card = event.target.closest('[data-listing-items] [data-product-id]');
        if (card && this.clickUrl && this.term && this.$root.contains(card) && navigator.sendBeacon) {
            const data = new FormData();
            data.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
            data.append('q', this.term);
            data.append('product_id', card.dataset.productId);
            navigator.sendBeacon(this.clickUrl, data);
        }
    },

    owns(element) {
        return this.$root.contains(element)
            || Boolean(element.closest('[data-listing-facets-mobile], [data-listing-sort-sheet], [id^="overlay-listing-"]'));
    },

    /**
     * Serialise the filter form into the compact URL format: size=12,13&price=199-499.
     */
    applyForm(form, event = null) {
        if (event?.target?.matches?.('input[type="range"], input[type="search"]')) {
            return;
        }

        const grouped = {};
        for (const [key, value] of new FormData(form).entries()) {
            if (value === '') {
                continue;
            }
            const name = key.endsWith('[]') ? key.slice(0, -2) : key;
            (grouped[name] ??= []).push(value);
        }

        const range = form.querySelector('[data-price-range]');
        const from = grouped.price_from?.[0] ?? '';
        const to = grouped.price_to?.[0] ?? '';
        delete grouped.price_from;
        delete grouped.price_to;

        if (range) {
            const lower = from !== '' && Number(from) > Number(range.dataset.min) ? String(Math.round(Number(from))) : '';
            const upper = to !== '' && Number(to) < Number(range.dataset.max) ? String(Math.round(Number(to))) : '';
            if (lower || upper) {
                grouped.price = [`${lower}-${upper}`];
            }
        }

        const params = new URLSearchParams();
        Object.entries(grouped).forEach(([name, values]) => params.set(name, [...new Set(values)].join(',')));

        const query = params.toString();
        this.navigate(form.action + (query ? `?${query}` : ''));
    },

    setParam(name, value) {
        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        url.searchParams.delete('partial');
        if (value === null || value === '') {
            url.searchParams.delete(name);
        } else {
            url.searchParams.set(name, value);
        }
        this.navigate(url.toString());
    },

    async navigate(url, push = true) {
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;

        try {
            const target = new URL(url, window.location.origin);
            target.searchParams.set('partial', '1');

            const response = await fetch(target, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: this.controller.signal,
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const data = await response.json();
            this.render(data);

            if (push) {
                history.pushState({ listing: true }, '', data.url ?? url);
            }

            const top = this.$root.getBoundingClientRect().top;
            if (top < 0) {
                window.scrollBy({ top: top - 120, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
            }
        } catch (error) {
            if (error?.name !== 'AbortError') {
                window.location.href = url;
            }
        } finally {
            this.loading = false;
        }
    },

    render(data) {
        const replace = (selector, html, scope = this.$root) => {
            const element = scope.querySelector(selector);
            if (element && typeof html === 'string') {
                element.innerHTML = html;
            }
        };

        replace('[data-listing-results]', data.results_html);
        replace('[data-listing-facets]', data.facets_html);
        replace('[data-listing-facets-mobile]', data.facets_mobile_html, document);
        replace('[data-listing-sort-sheet]', data.sort_html, document);

        this.nextUrl = data.next_page_url ?? null;
        this.total = data.total ?? null;
        this.filterCount = data.filter_count ?? 0;
        this.loadFailed = false;
        this.$nextTick(() => this.observe());
    },

    onPopState(event) {
        if (event.state?.listing) {
            this.navigate(window.location.href, false);
        }
    },

    observe() {
        this.observer?.disconnect();
        const sentinel = this.$root.querySelector('[data-listing-sentinel]');

        if (!sentinel || !('IntersectionObserver' in window)) {
            this.enhanced = 'IntersectionObserver' in window;

            return;
        }

        this.observer = new IntersectionObserver(
            (entries) => entries.some((entry) => entry.isIntersecting) && this.loadMore(),
            { rootMargin: '600px 0px' },
        );
        this.observer.observe(sentinel);
    },

    async loadMore() {
        if (!this.nextUrl || this.loadingMore || this.loading) {
            return;
        }

        this.loadingMore = true;
        this.loadFailed = false;

        try {
            const data = await fetchJson(this.nextUrl);
            this.$root.querySelector('[data-listing-items]')?.insertAdjacentHTML('beforeend', data.html);
            this.nextUrl = data.next_page_url ?? null;

            if (data.url) {
                history.replaceState({ ...(history.state ?? {}), listing: true }, '', data.url);
            }
            if (!this.nextUrl) {
                this.observer?.disconnect();
            }
        } catch {
            this.loadFailed = true;
        } finally {
            this.loadingMore = false;
        }
    },

    applyLabel(count, element) {
        let labels = {};
        try {
            labels = JSON.parse(element?.dataset.applyLabels ?? '{}');
        } catch {
            /* keep the server-rendered text */
        }

        if (count === null || count === undefined) {
            return labels.none ?? '';
        }
        if (count === 0) {
            return labels.zero ?? '';
        }
        if (count === 1) {
            return labels.one ?? '';
        }

        return (labels.many ?? '').replace('__COUNT__', Number(count).toLocaleString('en-IN'));
    },
});
