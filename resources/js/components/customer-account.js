import { fetchJson } from '../fetch-json';

const PENDING_WISHLIST_KEY = 'wishlist:pending';

const storage = {
    get(key) {
        try {
            return window.sessionStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.sessionStorage.setItem(key, value);
        } catch {
            /* storage blocked - the retry after login is skipped */
        }
    },
    remove(key) {
        try {
            window.sessionStorage.removeItem(key);
        } catch {
            /* ignore */
        }
    },
};

/**
 * Six one-digit boxes for a one-time code (Customer M08): auto-advance, backspace to the
 * previous box, paste of the whole code, and an "otp-complete" event once every box is
 * filled (the form listens and submits). The boxes are named code[] so the form also
 * works without JavaScript.
 */
export const otpInput = ({ length = 6 } = {}) => ({
    digits: Array.from({ length }, () => ''),

    get code() {
        return this.digits.join('');
    },

    boxes() {
        return [...this.$root.querySelectorAll('[data-otp-box]')];
    },

    focusBox(index) {
        this.boxes()[Math.max(0, Math.min(length - 1, index))]?.focus();
    },

    onInput(index, event) {
        const value = event.target.value.replace(/\D/g, '');

        if (value.length > 1) {
            this.fill(value, index);
            return;
        }

        this.digits[index] = value;
        event.target.value = value;

        if (value && index < length - 1) {
            this.focusBox(index + 1);
        }
        this.announceIfComplete();
    },

    onKeydown(index, event) {
        if (event.key === 'Backspace' && !this.digits[index] && index > 0) {
            event.preventDefault();
            this.digits[index - 1] = '';
            this.focusBox(index - 1);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            this.focusBox(index - 1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            this.focusBox(index + 1);
        }
    },

    onPaste(index, event) {
        event.preventDefault();
        this.fill((event.clipboardData?.getData('text') ?? '').replace(/\D/g, ''), index);
    },

    fill(text, start = 0) {
        [...text].slice(0, length - start).forEach((digit, offset) => {
            this.digits[start + offset] = digit;
        });
        this.boxes().forEach((box, index) => {
            box.value = this.digits[index];
        });
        this.focusBox(Math.min(length - 1, start + text.length));
        this.announceIfComplete();
    },

    clear() {
        this.digits = Array.from({ length }, () => '');
        this.boxes().forEach((box) => {
            box.value = '';
        });
        this.focusBox(0);
    },

    announceIfComplete() {
        if (this.digits.every((digit) => digit !== '')) {
            this.$dispatch('otp-complete', { code: this.code });
        }
    },
});

/**
 * "Resend code in 30 s" countdown.
 */
export const resendTimer = ({ seconds = 0 } = {}) => ({
    remaining: Math.max(0, Number(seconds) || 0),
    timer: null,

    init() {
        this.start(this.remaining);
    },

    start(seconds) {
        clearInterval(this.timer);
        this.remaining = Math.max(0, Number(seconds) || 0);
        this.timer = setInterval(() => {
            this.remaining = Math.max(0, this.remaining - 1);
            if (this.remaining === 0) {
                clearInterval(this.timer);
            }
        }, 1000);
    },

    destroy() {
        clearInterval(this.timer);
    },
});

/**
 * In-page OTP sign-in for the login drawer (wishlist heart, Buy Now): mobile/email → code
 * → signed in, then the page reloads so the wishlist retry (and the cart, M10) run as the
 * customer. Talks JSON to the same endpoints as the full login page.
 */
export const otpLogin = ({ sendUrl, verifyUrl, emailEnabled = false } = {}) => ({
    step: 'identify',
    channel: 'sms',
    phone: '',
    email: '',
    masked: '',
    error: '',
    loading: false,
    remaining: 0,
    timer: null,

    useChannel(channel) {
        if (channel === 'email' && !emailEnabled) {
            return;
        }
        this.channel = channel;
        this.error = '';
    },

    async send() {
        this.loading = true;
        this.error = '';

        try {
            const data = await fetchJson(sendUrl, {
                method: 'POST',
                body: { channel: this.channel, phone: this.phone, email: this.email },
            });
            this.masked = data.masked;
            this.step = 'verify';
            this.countdown(data.resend_in);
            this.$nextTick(() => this.$root.querySelector('[data-otp-box]')?.focus());
        } catch (failure) {
            this.error = failure.errors?.phone?.[0] ?? failure.errors?.email?.[0] ?? failure.errors?.channel?.[0] ?? failure.message;
        } finally {
            this.loading = false;
        }
    },

    async verify(code) {
        if (this.loading) {
            return;
        }
        this.loading = true;
        this.error = '';

        try {
            const data = await fetchJson(verifyUrl, { method: 'POST', body: { code } });
            window.Alpine?.store('toast').push(data.message, 'success');
            window.location.reload();
        } catch (failure) {
            this.error = failure.errors?.code?.[0] ?? failure.message;
            this.loading = false;
        }
    },

    change() {
        this.step = 'identify';
        this.error = '';
        clearInterval(this.timer);
    },

    countdown(seconds) {
        clearInterval(this.timer);
        this.remaining = Math.max(0, Number(seconds) || 0);
        this.timer = setInterval(() => {
            this.remaining = Math.max(0, this.remaining - 1);
            if (this.remaining === 0) {
                clearInterval(this.timer);
            }
        }, 1000);
    },
});

/**
 * Address form, pincode first (FR-CUS-004): six digits look the pincode up in the pincode
 * master (Directory API) and fill city/state, with a serviceability chip. The server derives
 * city/state again on save; the lookup only gives early feedback.
 */
export const addressForm = ({ lookupUrl = null, pincode = '', city = '', state = '', messages = {} } = {}) => ({
    pincode: String(pincode ?? ''),
    city: city ?? '',
    state: state ?? '',
    serviceable: null,
    cod: null,
    lookupError: '',
    looking: false,
    loading: false,
    lastLookup: '',

    init() {
        if (/^[1-9][0-9]{5}$/.test(this.pincode) && !this.city) {
            this.lookup();
        }
        this.$watch('pincode', () => this.lookup());
    },

    async lookup() {
        const pin = this.pincode.replace(/\s+/g, '');

        if (!lookupUrl || !/^[1-9][0-9]{5}$/.test(pin)) {
            this.serviceable = null;
            this.lookupError = '';
            return;
        }
        if (pin === this.lastLookup) {
            return;
        }

        this.lastLookup = pin;
        this.looking = true;
        this.lookupError = '';

        try {
            const response = await fetchJson(lookupUrl.replace('000000', pin));
            const data = response.data ?? response;
            this.city = data.city?.name ?? data.district ?? '';
            this.state = data.state?.name ?? '';
            this.serviceable = Boolean(data.is_serviceable);
            this.cod = Boolean(data.cod_allowed);
        } catch {
            this.city = '';
            this.state = '';
            this.serviceable = null;
            this.lookupError = messages.unknown ?? '';
        } finally {
            this.looking = false;
        }
    },
});

function openCustomerLogin() {
    if (window.__customerLoginDrawer) {
        window.dispatchEvent(new CustomEvent('open-drawer', { detail: 'customer-login' }));
    } else if (window.__customerLoginUrl) {
        window.location.href = `${window.__customerLoginUrl}?redirect=${encodeURIComponent(window.location.href)}`;
    }
}

/**
 * Persists the optimistic wishlist store (stores.js "wishlist-changed") to the Customer
 * wishlist endpoint. Guests are asked to log in; the product they tapped is kept in
 * sessionStorage and saved right after they sign in.
 */
export function startWishlistSync(Alpine) {
    const url = window.__wishlistToggleUrl;

    if (!url) {
        return;
    }

    const store = () => Alpine.store('wishlist');
    const toast = (message, type = 'success') => Alpine.store('toast').push(message, type, 3000);

    window.addEventListener('wishlist-changed', async ({ detail }) => {
        const { id, added } = detail ?? {};

        if (!window.__customerAuthenticated) {
            store().apply(id, !added);
            if (added) {
                storage.set(PENDING_WISHLIST_KEY, String(id));
            }
            openCustomerLogin();
            return;
        }

        try {
            const data = await fetchJson(url, { method: 'POST', body: { product_id: Number(id), wishlisted: added } });
            store().apply(id, data.wishlisted);
            toast(data.message);
        } catch (failure) {
            store().apply(id, !added);
            if (failure.status === 401) {
                window.__customerAuthenticated = false;
                if (added) {
                    storage.set(PENDING_WISHLIST_KEY, String(id));
                }
                openCustomerLogin();
                return;
            }
            toast(failure.message, 'error');
        }
    });

    const pending = storage.get(PENDING_WISHLIST_KEY);

    if (window.__customerAuthenticated && pending) {
        storage.remove(PENDING_WISHLIST_KEY);
        fetchJson(url, { method: 'POST', body: { product_id: Number(pending), wishlisted: true } })
            .then((data) => {
                store().apply(pending, data.wishlisted);
                toast(data.message);
            })
            .catch(() => {
                /* product gone or list full - nothing to restore */
            });
    }
}
