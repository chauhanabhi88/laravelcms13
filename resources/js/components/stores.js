/**
 * Global Alpine stores. The layout seeds window.__cartCount and window.__wishlistIds
 * from the server (HeaderComposer); Cart (M10) and Wishlist (M08) keep them in sync.
 */
export default function registerStores(Alpine) {
    Alpine.store('toast', {
        items: [],
        nextId: 1,

        push(message, type = 'success', timeout = 5000) {
            if (!message) {
                return;
            }
            const id = this.nextId++;
            this.items.push({ id, message, type });
            if (timeout) {
                setTimeout(() => this.dismiss(id), timeout);
            }
        },

        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });

    Alpine.store('cart', {
        count: Number(window.__cartCount ?? 0) || 0,

        set(count) {
            this.count = Math.max(0, Number(count) || 0);
        },
    });

    Alpine.store('wishlist', {
        ids: new Set((window.__wishlistIds ?? []).map(String)),

        has(id) {
            return this.ids.has(String(id));
        },

        /**
         * Optimistic local toggle. Announces "wishlist-changed" so the server sync
         * (M08) can persist it and roll back with set() on failure.
         */
        toggle(id) {
            const key = String(id);
            const ids = new Set(this.ids);
            const added = !ids.has(key);

            added ? ids.add(key) : ids.delete(key);
            this.ids = ids;
            window.dispatchEvent(new CustomEvent('wishlist-changed', { detail: { id: key, added } }));

            return added;
        },

        set(ids) {
            this.ids = new Set(ids.map(String));
        },

        get count() {
            return this.ids.size;
        },
    });

    window.addEventListener('wishlist-toggle', (event) => Alpine.store('wishlist').toggle(event.detail?.id ?? event.detail));
}
