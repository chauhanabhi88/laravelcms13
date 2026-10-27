import { fetchJson } from '../fetch-json';

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

/**
 * − n + quantity input. Fires a bubbling native "change" on the input after each step.
 */
export const qtyStepper = ({ value = 1, min = 1, max = 10 } = {}) => ({
    value: Number(value),
    min: Number(min),
    max: Number(max),

    step(delta) {
        this.set(this.value + delta);
    },

    set(next) {
        const value = clamp(Number.parseInt(next, 10) || this.min, this.min, this.max);
        const changed = value !== this.value;
        this.value = value;
        this.$refs.input.value = value;
        if (changed) {
            this.$nextTick(() => this.$refs.input.dispatchEvent(new Event('change', { bubbles: true })));
        }
    },
});

/**
 * Dual-thumb price range kept in sync with two number inputs.
 */
export const priceRange = ({ min = 0, max = 1000, from = null, to = null, step = 1 } = {}) => ({
    min: Number(min),
    max: Number(max),
    from: Number(from ?? min),
    to: Number(to ?? max),
    step: Number(step),

    setFrom(value) {
        this.from = clamp(Number(value) || this.min, this.min, this.to);
    },

    setTo(value) {
        this.to = clamp(Number(value) || this.max, this.from, this.max);
    },

    percent(value) {
        return this.max === this.min ? 0 : ((value - this.min) / (this.max - this.min)) * 100;
    },
});

/**
 * File picker with drag & drop, previews and client-side type/size/count checks.
 * The server must still validate every upload.
 */
export const dropzone = ({ accept = '', maxFiles = 1, maxSizeMb = 5, multiple = false, messages = {} } = {}) => ({
    accept: accept.split(',').map((token) => token.trim().toLowerCase()).filter(Boolean),
    maxFiles: Number(maxFiles),
    maxBytes: Number(maxSizeMb) * 1024 * 1024,
    multiple,
    messages,
    files: [],
    errors: [],
    dragging: false,

    destroy() {
        this.files.forEach((item) => item.url && URL.revokeObjectURL(item.url));
    },

    accepts(file) {
        if (!this.accept.length) {
            return true;
        }
        const name = file.name.toLowerCase();
        const type = file.type.toLowerCase();

        return this.accept.some((token) => {
            if (token.startsWith('.')) {
                return name.endsWith(token);
            }
            if (token.endsWith('/*')) {
                return type.startsWith(token.slice(0, -1));
            }
            return type === token;
        });
    },

    add(fileList) {
        this.errors = [];
        if (!this.multiple) {
            this.clear();
        }
        for (const file of fileList) {
            if (this.files.length >= this.maxFiles) {
                this.errors.push(this.message('max_files', { count: this.maxFiles }));
                break;
            }
            if (!this.accepts(file)) {
                this.errors.push(this.message('type', { name: file.name }));
                continue;
            }
            if (file.size > this.maxBytes) {
                this.errors.push(this.message('size', { name: file.name, size: this.maxBytes / 1024 / 1024 }));
                continue;
            }
            this.files.push({
                file,
                name: file.name,
                url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
            });
        }
        this.sync();
    },

    remove(index) {
        const [item] = this.files.splice(index, 1);
        if (item?.url) {
            URL.revokeObjectURL(item.url);
        }
        this.sync();
    },

    clear() {
        this.files.forEach((item) => item.url && URL.revokeObjectURL(item.url));
        this.files = [];
    },

    drop(event) {
        this.dragging = false;
        this.add(event.dataTransfer.files);
    },

    sync() {
        const transfer = new DataTransfer();
        this.files.forEach((item) => transfer.items.add(item.file));
        this.$refs.input.files = transfer.files;
    },

    message(key, replacements) {
        return Object.entries(replacements).reduce(
            (text, [name, value]) => text.replace(`:${name}`, value),
            this.messages[key] ?? key,
        );
    },
});

/**
 * Pincode serviceability check. The endpoint answers { serviceable, cod, message } (plus
 * delivery_text / cod_text / return_text on the product page) for GET ?pincode=&product_id=
 * or, with method "post", a JSON { pincode, product_id, variant_id } body. A prefilled
 * pincode is checked on load; a variant change re-checks a shown result.
 */
export const pincodeChecker = ({ endpoint = null, productId = null, variantId = null, method = 'get', pincode = '', messages = {} } = {}) => ({
    endpoint,
    productId,
    variantId,
    method,
    messages,
    pincode: pincode ?? '',
    loading: false,
    result: null,
    error: null,

    init() {
        if (this.endpoint && /^[1-9][0-9]{5}$/.test(this.pincode)) {
            this.check();
        }
    },

    setVariant(id) {
        const next = id ?? null;
        if (String(next) === String(this.variantId)) {
            return;
        }
        this.variantId = next;
        if (this.result?.serviceable) {
            this.check();
        }
    },

    async check() {
        this.error = null;
        this.result = null;
        this.pincode = String(this.pincode ?? '').replace(/\s+/g, '');

        if (!/^[1-9][0-9]{5}$/.test(this.pincode)) {
            this.error = this.messages.invalid ?? 'Enter a valid 6-digit pincode.';
            return;
        }
        if (!this.endpoint) {
            this.error = this.messages.unavailable ?? 'Delivery check is not available yet.';
            return;
        }

        this.loading = true;
        try {
            if (this.method === 'post') {
                this.result = await fetchJson(this.endpoint, {
                    method: 'POST',
                    body: { pincode: this.pincode, product_id: this.productId, variant_id: this.variantId || null },
                });
            } else {
                const url = new URL(this.endpoint, window.location.origin);
                url.searchParams.set('pincode', this.pincode);
                if (this.productId) {
                    url.searchParams.set('product_id', this.productId);
                }
                this.result = await fetchJson(url.toString());
            }
        } catch (failure) {
            this.error = failure.errors?.pincode?.[0] ?? failure.errors?.variant_id?.[0] ?? failure.message;
        } finally {
            this.loading = false;
        }
    },
});
