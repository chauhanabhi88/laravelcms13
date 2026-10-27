/**
 * WAI-ARIA tabs: roving tabindex, arrow/Home/End keys move and activate.
 */
export default ({ active = null } = {}) => ({
    active,

    init() {
        if (!this.active) {
            this.active = this.tabs()[0]?.dataset.tab ?? null;
        }
    },

    tabs() {
        return [...this.$root.querySelectorAll('[role="tab"]')];
    },

    select(tab) {
        this.active = tab;
    },

    isActive(tab) {
        return this.active === tab;
    },

    onKeydown(event) {
        const tabs = this.tabs();
        const index = tabs.findIndex((tab) => tab.dataset.tab === this.active);
        const moves = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 };

        if (!(event.key in moves)) {
            return;
        }
        event.preventDefault();
        const next = tabs[(moves[event.key] + tabs.length) % tabs.length];
        this.select(next.dataset.tab);
        next.focus();
    },
});
