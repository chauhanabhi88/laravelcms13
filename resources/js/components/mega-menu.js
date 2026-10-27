/**
 * Keyboard support for the category bar. Mouse users get CSS hover (group-hover);
 * keyboard users: Left/Right move between top-level items, Enter/Space/Down open the
 * flyout and focus its first link, Esc closes and returns focus to the trigger.
 */
export default () => ({
    openIndex: null,

    triggers() {
        return [...this.$root.querySelectorAll('[data-mega-trigger]')];
    },

    isOpen(index) {
        return this.openIndex === index;
    },

    toggle(index) {
        this.openIndex = this.openIndex === index ? null : index;
    },

    close(focusTrigger = false) {
        const index = this.openIndex;
        this.openIndex = null;
        if (focusTrigger && index !== null) {
            this.triggers()[index]?.focus();
        }
    },

    focusTrigger(index) {
        const triggers = this.triggers();
        if (!triggers.length) {
            return;
        }
        const next = triggers[(index + triggers.length) % triggers.length];
        if (this.openIndex !== null) {
            this.openIndex = null;
        }
        next.focus();
    },

    openAndFocus(index) {
        this.openIndex = index;
        this.$nextTick(() => document.getElementById(`mega-panel-${index}`)?.querySelector('a')?.focus());
    },

    onTriggerKeydown(event, index) {
        switch (event.key) {
            case 'ArrowRight':
                event.preventDefault();
                this.focusTrigger(index + 1);
                break;
            case 'ArrowLeft':
                event.preventDefault();
                this.focusTrigger(index - 1);
                break;
            case 'ArrowDown':
                if (event.currentTarget.hasAttribute('aria-expanded')) {
                    event.preventDefault();
                    this.openAndFocus(index);
                }
                break;
            case 'Escape':
                this.close(true);
                break;
        }
    },
});
