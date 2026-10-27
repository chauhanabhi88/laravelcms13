import { fetchJson } from '../fetch-json';

/**
 * Variant matrix generator (wizard step 3, FR-SCT-002).
 *
 * The seller picks options per variant axis (chips); every combination becomes a row keyed
 * by its combination key ("attrId:optionId|…", or "default" without axes). Regenerating keeps
 * the values of rows that still exist; saved rows that drop out stay greyed as "will be
 * deleted / disabled" and are left out of the save. The server re-validates everything.
 */
export default function variantMatrix(config) {
    const blankRow = (options) => ({
        id: null,
        key: '',
        options,
        seller_sku: '',
        mrp: '',
        price: '',
        stock: '',
        weight_grams: '',
        locked: false,
        removed: false,
        checked: false,
        errors: {},
        skuError: null,
    });

    return {
        axes: config.axes,
        maxVariants: config.maxVariants,
        published: config.published,
        labels: config.labels,
        selected: {},
        rows: [],
        bulk: { mrp: '', price: '', stock: '', weight_grams: '' },
        skuPattern: '',
        saving: false,
        formError: null,

        init() {
            this.skuPattern = ['{base}', ...this.axes.map((axis) => `{${this.token(axis.name)}}`)].join('-');

            for (const axis of this.axes) {
                const used = new Set(config.rows.map((row) => Number(row.options[axis.id])).filter(Boolean));
                this.selected[axis.id] = axis.options.map((option) => option.id).filter((id) => used.has(id));
            }

            this.rows = config.rows.map((row) => ({ ...blankRow(row.options), ...row, removed: false, checked: false, errors: {}, skuError: null }));

            if (this.rows.length === 0 && this.axes.length === 0) {
                this.rows = [{ ...blankRow({}), key: 'default' }];
            }
        },

        token(name) {
            return String(name).toLowerCase().replace(/[^a-z0-9]+/g, '');
        },

        keyFor(options) {
            const ids = Object.keys(options).map(Number).sort((a, b) => a - b);

            return ids.length === 0 ? 'default' : ids.map((id) => `${id}:${Number(options[id])}`).join('|');
        },

        optionName(axisId, optionId) {
            const axis = this.axes.find((item) => item.id === Number(axisId));

            return axis?.options.find((option) => option.id === Number(optionId))?.name ?? String(optionId);
        },

        label(row) {
            return this.axes.map((axis) => this.optionName(axis.id, row.options[axis.id])).join(' / ') || '—';
        },

        isSelected(axisId, optionId) {
            return (this.selected[axisId] ?? []).includes(optionId);
        },

        toggle(axisId, optionId) {
            const current = this.selected[axisId] ?? [];
            const axis = this.axes.find((item) => item.id === axisId);
            const next = current.includes(optionId) ? current.filter((id) => id !== optionId) : [...current, optionId];

            this.selected[axisId] = axis.options.map((option) => option.id).filter((id) => next.includes(id));
            this.regenerate();
        },

        combinations() {
            if (this.axes.length === 0) {
                return [{}];
            }

            let combos = [{}];
            for (const axis of this.axes) {
                const picked = this.selected[axis.id] ?? [];
                if (picked.length === 0) {
                    return [];
                }
                combos = combos.flatMap((combo) => picked.map((optionId) => ({ ...combo, [axis.id]: optionId })));
            }

            return combos;
        },

        regenerate() {
            const existing = new Map(this.rows.map((row) => [row.key, row]));
            const wanted = this.combinations();
            const keys = new Set();

            const rows = wanted.map((options) => {
                const key = this.keyFor(options);
                keys.add(key);
                const row = existing.get(key);

                return row ? { ...row, removed: false } : { ...blankRow(options), key };
            });

            // Saved variants that dropped out stay visible (greyed) until the next save.
            for (const row of this.rows) {
                if (row.id !== null && !keys.has(row.key)) {
                    rows.push({ ...row, removed: true, checked: false });
                }
            }

            this.rows = rows;
        },

        get activeRows() {
            return this.rows.filter((row) => !row.removed);
        },

        get tooMany() {
            return this.activeRows.length > this.maxVariants;
        },

        get anyChecked() {
            return this.activeRows.some((row) => row.checked);
        },

        toggleAll(event) {
            this.activeRows.forEach((row) => (row.checked = event.target.checked));
        },

        applyBulk(field) {
            const value = this.bulk[field];
            if (value === '' || value === null) {
                return;
            }
            const targets = this.anyChecked ? this.activeRows.filter((row) => row.checked) : this.activeRows;
            targets.forEach((row) => {
                row[field] = value;
                delete row.errors[field];
            });
        },

        generateSkus() {
            const base = (config.baseSku || 'SKU').toUpperCase();
            for (const row of this.activeRows) {
                if (row.locked && row.seller_sku) {
                    continue;
                }
                let sku = this.skuPattern.replace(/\{base\}/gi, base);
                for (const axis of this.axes) {
                    const name = this.optionName(axis.id, row.options[axis.id]).toUpperCase().replace(/[^A-Z0-9]+/g, '');
                    sku = sku.replace(new RegExp(`\\{${this.token(axis.name)}\\}`, 'gi'), name);
                }
                row.seller_sku = sku.replace(/[^A-Za-z0-9._\-/]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '').slice(0, 64);
                row.skuError = null;
                delete row.errors.seller_sku;
            }
        },

        duplicateSku(row) {
            const sku = String(row.seller_sku).trim().toLowerCase();

            return sku !== '' && this.activeRows.filter((other) => String(other.seller_sku).trim().toLowerCase() === sku).length > 1;
        },

        priceAboveMrp(row) {
            return row.price !== '' && row.mrp !== '' && Number(row.price) > Number(row.mrp);
        },

        async checkSku(row) {
            row.skuError = null;
            const sku = String(row.seller_sku).trim();
            if (sku === '') {
                return;
            }
            try {
                const url = `${config.skuCheckUrl}?sku=${encodeURIComponent(sku)}&product=${config.productId}`;
                const data = await fetchJson(url);
                row.skuError = data.available ? null : data.message || this.labels.skuTaken;
            } catch {
                /* the server checks again on save */
            }
        },

        validate() {
            let valid = !this.tooMany && this.activeRows.length > 0;

            for (const row of this.activeRows) {
                row.errors = {};
                for (const field of ['seller_sku', 'mrp', 'price', 'stock', 'weight_grams']) {
                    if (String(row[field] ?? '').trim() === '') {
                        row.errors[field] = this.labels.required;
                        valid = false;
                    }
                }
                if (this.priceAboveMrp(row)) {
                    row.errors.price = this.labels.priceAboveMrp;
                    valid = false;
                }
                if (this.duplicateSku(row)) {
                    row.errors.seller_sku = this.labels.duplicateSku;
                    valid = false;
                }
                if (row.skuError) {
                    valid = false;
                }
            }

            return valid;
        },

        async save() {
            this.formError = null;

            if (!this.validate()) {
                this.formError = this.tooMany ? this.labels.tooMany : null;
                return;
            }

            this.saving = true;
            const rows = this.activeRows;

            try {
                const data = await fetchJson(config.saveUrl, {
                    method: 'POST',
                    body: {
                        variants: rows.map((row) => ({
                            id: row.id,
                            options: row.options,
                            seller_sku: String(row.seller_sku).trim(),
                            mrp: String(row.mrp).trim(),
                            price: String(row.price).trim(),
                            stock: row.stock === '' ? '' : Number(row.stock),
                            weight_grams: row.weight_grams === '' ? '' : Number(row.weight_grams),
                        })),
                    },
                });
                window.Alpine?.store('toast')?.push(data.message, 'success');
                window.location.href = data.redirect;
            } catch (failure) {
                this.saving = false;
                if (failure.status === 422) {
                    for (const [key, messages] of Object.entries(failure.errors ?? {})) {
                        const match = key.match(/^variants\.(\d+)\.(\w+)/);
                        if (match && rows[Number(match[1])]) {
                            rows[Number(match[1])].errors[match[2] === 'options' ? 'seller_sku' : match[2]] = messages[0];
                        } else {
                            this.formError = messages[0];
                        }
                    }
                    this.formError ??= failure.message;
                } else {
                    this.formError = failure.message;
                }
            }
        },
    };
}
