import Alpine from 'alpinejs';
import { fetchJson } from './fetch-json';
import registerStores from './components/stores';
import drawer from './components/drawer';
import carousel from './components/carousel';
import tabs from './components/tabs';
import infiniteList from './components/infinite-scroll';
import clamp from './components/clamp';
import megaMenu from './components/mega-menu';
import searchBox from './components/search-box';
import listing from './components/listing';
import recentlyViewed, { rememberRecentlyViewed } from './components/recently-viewed';
import productPage from './components/product-page';
import lazyProductRow from './components/lazy-product-row';
import { dropzone, pincodeChecker, priceRange, qtyStepper } from './components/form-controls';
import { addressForm, otpInput, otpLogin, resendTimer, startWishlistSync } from './components/customer-account';
import notificationBell from './components/notification-bell';
import { cartPage, miniCart, startCartForms } from './components/cart';

export { fetchJson };

window.fetchJson = fetchJson;
window.rememberRecentlyViewed = rememberRecentlyViewed;

document.addEventListener('alpine:init', () => {
    registerStores(Alpine);

    Alpine.data('overlay', drawer);
    Alpine.data('carousel', carousel);
    Alpine.data('tabs', tabs);
    Alpine.data('infiniteList', infiniteList);
    Alpine.data('clamp', clamp);
    Alpine.data('megaMenu', megaMenu);
    Alpine.data('searchBox', searchBox);
    Alpine.data('listing', listing);
    Alpine.data('recentlyViewed', recentlyViewed);
    Alpine.data('productPage', productPage);
    Alpine.data('lazyProductRow', lazyProductRow);
    Alpine.data('qtyStepper', qtyStepper);
    Alpine.data('priceRange', priceRange);
    Alpine.data('dropzone', dropzone);
    Alpine.data('pincodeChecker', pincodeChecker);
    Alpine.data('otpInput', otpInput);
    Alpine.data('resendTimer', resendTimer);
    Alpine.data('otpLogin', otpLogin);
    Alpine.data('addressForm', addressForm);
    Alpine.data('notificationBell', notificationBell);
    Alpine.data('cartPage', cartPage);
    Alpine.data('miniCart', miniCart);
    startWishlistSync(Alpine);
    startCartForms();

    /**
     * Progressive-enhancement form: submits via fetch when JS is on, falls back
     * to a normal POST otherwise. Server returns { message } or a 422 errors bag.
     */
    Alpine.data('ajaxForm', ({ resetOnSuccess = true } = {}) => ({
        loading: false,
        errors: {},

        error(field) {
            return this.errors[field]?.[0] ?? null;
        },

        async submit(event) {
            const form = event.target;
            this.loading = true;
            this.errors = {};

            try {
                const data = await fetchJson(form.action, {
                    method: form.getAttribute('method')?.toUpperCase() ?? 'POST',
                    body: new FormData(form),
                });
                Alpine.store('toast').push(data.message, 'success');
                if (resetOnSuccess) {
                    form.reset();
                }
                this.$dispatch('ajax-form:success', data);
            } catch (failure) {
                if (failure.status === 422) {
                    this.errors = failure.errors;
                } else if (failure.status === 401) {
                    window.location.reload();
                } else {
                    Alpine.store('toast').push(failure.message, 'error');
                }
            } finally {
                this.loading = false;
            }
        },
    }));

    /**
     * Client-side mirror of the server password policy. The server rule stays
     * authoritative; this only gives early feedback.
     */
    Alpine.data('passwordField', ({ min = 8, max = 20 } = {}) => ({
        value: '',
        visible: false,

        get checks() {
            return [
                { label: `${min}–${max} characters`, ok: this.value.length >= min && this.value.length <= max },
                { label: 'Upper and lower case letters', ok: /[a-z]/.test(this.value) && /[A-Z]/.test(this.value) },
                { label: 'A number', ok: /[0-9]/.test(this.value) },
                { label: 'A symbol', ok: /[^A-Za-z0-9]/.test(this.value) },
            ];
        },
    }));
});

/**
 * Customer online/offline heartbeat (Customer module). The server answers with
 * the interval to wait before the next ping.
 */
function startCustomerPresence({ url, interval }) {
    const ping = async () => {
        if (document.visibilityState !== 'visible') {
            return setTimeout(ping, interval);
        }
        try {
            const data = await fetchJson(url, { method: 'POST' });
            setTimeout(ping, data.afterSeconds || interval);
        } catch {
            /* logged out or offline - stop pinging */
        }
    };
    ping();
}

if (window.customerPresence?.url) {
    startCustomerPresence(window.customerPresence);
}

window.Alpine = Alpine;
Alpine.start();
