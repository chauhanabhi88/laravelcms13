/**
 * In-app notification bell (M09, FR-NTF-005): unread badge, a dropdown with the latest
 * entries loaded on first open, and a refresh every `pollSeconds` while the tab is visible.
 *
 *     <div x-data="notificationBell({ count, feedUrl, readUrl, readAllUrl, pollSeconds })">
 *
 * `readUrl` contains the placeholder __ID__ for the notification id.
 */
export default function notificationBell({ count = 0, feedUrl, readUrl, readAllUrl, pollSeconds = 60 } = {}) {
    return {
        open: false,
        loading: false,
        loaded: false,
        failed: false,
        count: Number(count) || 0,
        items: [],
        timer: null,

        init() {
            const poll = Math.max(15, Number(pollSeconds) || 60) * 1000;

            this.timer = setInterval(() => {
                if (document.visibilityState === 'visible') {
                    this.refresh();
                }
            }, poll);

            this.onVisible = () => {
                if (document.visibilityState === 'visible' && this.loaded) {
                    this.refresh();
                }
            };
            document.addEventListener('visibilitychange', this.onVisible);
        },

        destroy() {
            clearInterval(this.timer);
            document.removeEventListener('visibilitychange', this.onVisible);
        },

        toggle() {
            this.open = !this.open;

            if (this.open && !this.loaded) {
                this.refresh();
            }
        },

        async refresh() {
            if (!feedUrl || this.loading) {
                return;
            }

            this.loading = true;

            try {
                const { data } = await window.fetchJson(feedUrl);
                this.count = Number(data?.unread) || 0;
                this.items = Array.isArray(data?.items) ? data.items : [];
                this.loaded = true;
                this.failed = false;
            } catch {
                this.failed = true;
            } finally {
                this.loading = false;
            }
        },

        async read(item) {
            if (!item.read) {
                item.read = true;
                this.count = Math.max(0, this.count - 1);
                await window.fetchJson(readUrl.replace('__ID__', encodeURIComponent(item.id)), { method: 'POST' }).catch(() => {});
            }

            if (item.url) {
                window.location.href = item.url;
            }
        },

        async readAll() {
            this.items.forEach((item) => {
                item.read = true;
            });
            this.count = 0;
            await window.fetchJson(readAllUrl, { method: 'POST' }).catch(() => {});
        },
    };
}
