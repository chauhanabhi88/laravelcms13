import { prefersReducedMotion } from '../fetch-json';

/**
 * Product page variant picker (M07 §5.3), mixed into productPage. Reads window.__pdp:
 * axes [{ attributeId, name, options: [{ id, label }] }] and variants
 * [{ id, sku, options: { "<attrId>": "<optId>" }, price, priceText, mrpText, discount, available, imagesKey }].
 * "available" is capped by the server; the real stock is never sent.
 */
export default (pdp) => ({
    axes: pdp.axes ?? [],
    variants: pdp.variants ?? [],
    selection: {},
    pickerError: '',
    lowStockThreshold: Number(pdp.lowStockThreshold ?? 5),

    initPicker() {
        const initial = this.variants.find((variant) => variant.id === pdp.defaultVariantId);
        if (initial) {
            this.selection = { ...initial.options };
        }
    },

    get selectedVariant() {
        if (this.axes.length === 0) {
            return this.variants.length === 1 ? this.variants[0] : null;
        }
        if (this.axes.some((axis) => !this.selection[String(axis.attributeId)])) {
            return null;
        }

        return this.variants.find((variant) => this.matches(variant, this.selection)) ?? null;
    },

    /**
     * The cheapest variant: what the price block shows until a variant is chosen.
     */
    get cheapestVariant() {
        return this.variants.reduce((cheapest, variant) => (!cheapest || Number(variant.price) < Number(cheapest.price) ? variant : cheapest), null);
    },

    get isSelectionComplete() {
        return this.axes.every((axis) => this.selection[String(axis.attributeId)]);
    },

    matches(variant, selection, skipAxisId = null) {
        return this.axes.every((axis) => {
            const key = String(axis.attributeId);
            if (key === String(skipAxisId) || !selection[key]) {
                return true;
            }

            return variant.options[key] === selection[key];
        });
    },

    isSelected(axisId, optionId) {
        return this.selection[String(axisId)] === String(optionId);
    },

    /**
     * Some in-stock variant has this option and fits the choices made on the other axes.
     */
    isAvailable(axisId, optionId) {
        return this.variants.some(
            (variant) => variant.available > 0 && variant.options[String(axisId)] === String(optionId) && this.matches(variant, this.selection, axisId),
        );
    },

    selectedLabel(axisId) {
        const axis = this.axes.find((candidate) => String(candidate.attributeId) === String(axisId));
        const optionId = this.selection[String(axisId)];

        return axis?.options.find((option) => String(option.id) === optionId)?.label ?? '';
    },

    /**
     * Roving tabindex: the selected option (else the first) is the group's tab stop.
     */
    tabIndexFor(axisId, optionId) {
        const selected = this.selection[String(axisId)];
        if (selected) {
            return selected === String(optionId) ? 0 : -1;
        }
        const axis = this.axes.find((candidate) => String(candidate.attributeId) === String(axisId));

        return String(axis?.options[0]?.id) === String(optionId) ? 0 : -1;
    },

    select(axisId, optionId) {
        const key = String(axisId);
        const next = { ...this.selection, [key]: String(optionId) };

        // Drop choices on other axes that no variant combines with the new option.
        for (const axis of this.axes) {
            const other = String(axis.attributeId);
            if (other !== key && next[other] && !this.variants.some((variant) => variant.options[key] === next[key] && variant.options[other] === next[other])) {
                delete next[other];
            }
        }

        this.selection = next;
        this.pickerError = '';
        this.variantChanged();
    },

    variantChanged() {
        const variant = this.selectedVariant;
        const url = new URL(window.location.href);

        if (variant && this.axes.length > 0) {
            url.searchParams.set('variant', variant.id);
        } else {
            url.searchParams.delete('variant');
        }
        window.history.replaceState(window.history.state, '', url);

        this.setImages(this.imageKeyForSelection());
        window.dispatchEvent(new CustomEvent('variant-changed', { detail: { id: variant?.id ?? null } }));
    },

    /**
     * The chosen colour's image set, else the general one.
     */
    imageKeyForSelection() {
        const variant = this.selectedVariant;
        if (variant?.imagesKey && this.images[variant.imagesKey]) {
            return variant.imagesKey;
        }
        for (const optionId of Object.values(this.selection)) {
            if (this.images[optionId]) {
                return optionId;
            }
        }

        return 'default';
    },

    get priceVariant() {
        return this.selectedVariant ?? this.cheapestVariant;
    },

    get priceText() {
        return this.priceVariant?.priceText ?? pdp.priceText;
    },

    get mrpText() {
        return this.priceVariant?.mrpText ?? pdp.mrpText;
    },

    get discount() {
        return Number(this.priceVariant?.discount ?? 0);
    },

    get discountText() {
        return this.format('off', { percent: this.discount });
    },

    get hasStock() {
        return this.variants.some((variant) => variant.available > 0);
    },

    get isOutOfStock() {
        if (!this.hasStock) {
            return true;
        }
        const variant = this.selectedVariant;

        return variant ? variant.available <= 0 : false;
    },

    get stockMessage() {
        if (!this.hasStock) {
            return this.messages.outOfStock;
        }
        const variant = this.selectedVariant;
        if (!variant) {
            return this.isSelectionComplete && this.axes.length > 0 ? this.messages.unavailableCombination : '';
        }
        if (variant.available <= 0) {
            return this.messages.outOfStock;
        }

        return variant.available <= this.lowStockThreshold ? this.format('onlyLeft', { count: variant.available }) : '';
    },

    get skuText() {
        return this.selectedVariant ? this.format('sku', { sku: this.selectedVariant.sku }) : '';
    },

    /**
     * Cart form guard: a size (every axis) must be chosen and in stock.
     */
    requireSelection(event) {
        const variant = this.selectedVariant;
        if (variant && variant.available > 0) {
            return true;
        }

        event.preventDefault();
        if (!variant) {
            const missing = this.axes.find((axis) => !this.selection[String(axis.attributeId)]);
            this.pickerError = missing ? this.format('required', { name: missing.name.toLowerCase() }) : this.messages.unavailableCombination;
            this.shakePicker();
        }

        return false;
    },

    shakePicker() {
        const picker = this.$refs.picker;
        if (!picker) {
            return;
        }
        picker.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'center' });
        if (!prefersReducedMotion() && picker.animate) {
            picker.animate(
                [{ transform: 'translateX(0)' }, { transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(-4px)' }, { transform: 'translateX(0)' }],
                { duration: 350, easing: 'ease-in-out' },
            );
        }
    },

    /**
     * Arrow keys move the selection within a radio group.
     */
    radioKeydown(event) {
        const steps = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
        if (!(event.key in steps)) {
            return;
        }
        const radios = [...event.currentTarget.querySelectorAll('[role="radio"]')];
        const index = radios.indexOf(document.activeElement);
        if (index === -1) {
            return;
        }
        event.preventDefault();
        const next = radios[(index + steps[event.key] + radios.length) % radios.length];
        next.focus();
        next.click();
    },

    /**
     * Merge fresh prices / availability (storefront.product.availability) into the payload.
     */
    applyAvailability(rows) {
        for (const row of rows) {
            const variant = this.variants.find((candidate) => candidate.id === row.id);
            if (variant) {
                Object.assign(variant, row);
            }
        }
        // Variants missing from the fresh list were disabled meanwhile.
        const ids = new Set(rows.map((row) => row.id));
        this.variants.forEach((variant) => {
            if (!ids.has(variant.id)) {
                variant.available = 0;
            }
        });
    },
});
