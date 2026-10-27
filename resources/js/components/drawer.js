const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

let openOverlays = 0;

function lockScroll(lock) {
    openOverlays = Math.max(0, openOverlays + (lock ? 1 : -1));
    document.documentElement.classList.toggle('overflow-hidden', openOverlays > 0);
}

/**
 * Shared behaviour for <x-theme::drawer> and <x-theme::modal>: open/close by name via
 * window events ($dispatch('open-drawer', 'name') / 'open-modal'), Esc to close, a small
 * focus trap (no @alpinejs/focus dependency) and focus restore on close.
 */
export default ({ name }) => ({
    name,
    open: false,
    returnFocus: null,

    panel() {
        return document.getElementById(`overlay-${this.name}`);
    },

    show(target = this.name) {
        if (target !== this.name || this.open) {
            return;
        }
        this.returnFocus = document.activeElement;
        this.open = true;
        lockScroll(true);
        this.$nextTick(() => setTimeout(() => this.focusFirst(), 50));
    },

    hide(target = this.name) {
        if (target !== this.name || !this.open) {
            return;
        }
        this.open = false;
        lockScroll(false);
        this.returnFocus?.focus?.({ preventScroll: true });
    },

    focusables() {
        const panel = this.panel();

        return panel ? [...panel.querySelectorAll(FOCUSABLE)].filter((el) => el.getClientRects().length > 0) : [];
    },

    focusFirst() {
        const [first] = this.focusables();
        (first ?? this.panel())?.focus();
    },

    trap(event) {
        const items = this.focusables();
        if (!items.length) {
            event.preventDefault();
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    },
});
