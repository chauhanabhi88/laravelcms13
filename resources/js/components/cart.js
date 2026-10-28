import { fetchJson } from '../fetch-json';

const PENDING_BUY_NOW_KEY = 'cart:buy-now';

const config = () => window.__cart ?? {};

const toast = (message, type = 'success') => window.Alpine?.store('toast').push(message, type, 3500);

const setCount = (count) => {
    if (count !== undefined && count !== null) {
        window.Alpine?.store('cart').set(count);
    }
};

const session = {
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
            /* storage blocked - the buyer taps Buy Now again after login */
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

function openCustomerLogin() {
    if (window.__customerLoginDrawer) {
        window.dispatchEvent(new CustomEvent('open-drawer', { detail: 'customer-login' }));
    } else if (window.__customerLoginUrl) {
        window.location.href = `${window.__customerLoginUrl}?redirect=${encodeURIComponent(window.location.href)}`;
    }
}

/**
 * Send a cart form (line action, pincode) with fetch, asking for the re-rendered partial of
 * the surface it came from ("page" or "drawer"). Resolves with the JSON body.
 */
async function sendCartForm(form, view) {
    const body = new FormData(form);
    body.set('view', view);

    return fetchJson(form.action, { method: 'POST', body });
}

/**
 * Behaviour shared by the cart page and the mini-cart: send data-cart-action forms with
 * fetch, then swap in the returned HTML and update the badge. Falls back to a normal post
 * when the request fails for a reason other than validation.
 */
const cartSurface = (view) => ({
    busy: false,

    async submit(event) {
        const form = event.target.closest('form[data-cart-action]');
        if (!form || this.busy) {
            return;
        }
        event.preventDefault();
        this.busy = true;

        try {
            const data = await sendCartForm(form, view);
            setCount(data.count);
            this.render(data.html);
            toast(data.message);
            window.dispatchEvent(new CustomEvent('cart-changed', { detail: { count: data.count, source: view } }));
        } catch (failure) {
            setCount(failure.count);
            if (failure.status === 401) {
                openCustomerLogin();
            } else if (failure.status === 422 || failure.status === 404) {
                toast(failure.message, 'error');
                this.refresh();
            } else {
                form.submit();
            }
        } finally {
            this.busy = false;
        }
    },

    render(html) {
        if (typeof html === 'string') {
            this.apply(html);
        }
    },
});

/**
 * Cart page (M10 §7): line actions without reloads; quantity steppers submit their form
 * (debounced) after every change. The server re-renders the whole body, so totals, seller
 * groups and revalidation notices always match the database.
 */
export const cartPage = () => ({
    ...cartSurface('page'),
    timers: {},

    init() {
        window.addEventListener('cart-changed', (event) => {
            if (event.detail?.source === 'drawer') {
                this.refresh();
            }
        });
    },

    apply(html) {
        this.$root.innerHTML = html;
    },

    /**
     * A stepper changed: submit its form once the buyer stops tapping.
     */
    changed(event) {
        const form = event.target.closest('form[data-cart-qty]');
        if (!form) {
            return;
        }
        clearTimeout(this.timers[form.action]);
        this.timers[form.action] = setTimeout(() => form.requestSubmit(), 450);
    },

    async refresh() {
        window.location.reload();
    },
});

/**
 * Mini-cart drawer content: loaded on open (or handed over by Add to Cart).
 */
export const miniCart = () => ({
    ...cartSurface('drawer'),
    html: null,
    loadedAt: 0,

    init() {
        window.addEventListener('mini-cart:show', (event) => {
            if (typeof event.detail?.html === 'string') {
                this.html = event.detail.html;
                this.loadedAt = Date.now();
            }
        });
        window.addEventListener('open-drawer', (event) => {
            if (event.detail === 'mini-cart' && Date.now() - this.loadedAt > 1000) {
                this.refresh();
            }
        });
    },

    apply(html) {
        this.html = html;
        this.loadedAt = Date.now();
    },

    async refresh() {
        if (!config().drawerUrl) {
            return;
        }
        try {
            const data = await fetchJson(config().drawerUrl);
            setCount(data.count);
            this.apply(data.html);
        } catch {
            /* keep what is shown */
        }
    },
});

/**
 * Product page Add to Cart (form[data-cart-add]): posted with fetch, then the header badge
 * updates and the mini-cart opens. Buy Now (the submit button with formaction) stays a normal
 * post; guests are asked to log in first and the purchase continues after sign-in.
 */
export function startCartForms() {
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('form[data-cart-add]') || event.defaultPrevented) {
            return;
        }

        const submitter = event.submitter;
        const buyNowUrl = submitter?.getAttribute('formaction');

        if (buyNowUrl) {
            if (!window.__customerAuthenticated) {
                event.preventDefault();
                const data = new FormData(form);
                session.set(PENDING_BUY_NOW_KEY, JSON.stringify({ url: buyNowUrl, variant_id: data.get('variant_id'), quantity: data.get('quantity') ?? 1 }));
                openCustomerLogin();
            }
            return;
        }

        event.preventDefault();
        if (form.dataset.busy === '1') {
            return;
        }
        form.dataset.busy = '1';
        form.setAttribute('aria-busy', 'true');

        try {
            const body = new FormData(form);
            body.set('view', 'drawer');
            const data = await fetchJson(form.action, { method: 'POST', body });
            setCount(data.count);
            window.dispatchEvent(new CustomEvent('mini-cart:show', { detail: { html: data.html } }));
            window.dispatchEvent(new CustomEvent('open-drawer', { detail: 'mini-cart' }));
            toast(data.message);
        } catch (failure) {
            setCount(failure.count);
            if (failure.status === 419) {
                window.location.reload();
                return;
            }
            toast(failure.errors?.variant_id?.[0] ?? failure.errors?.quantity?.[0] ?? failure.message, 'error');
        } finally {
            delete form.dataset.busy;
            form.removeAttribute('aria-busy');
        }
    });

    resumePendingBuyNow();
}

/**
 * A guest tapped Buy Now and has now signed in: post the purchase they started.
 */
function resumePendingBuyNow() {
    const pending = session.get(PENDING_BUY_NOW_KEY);
    if (!pending || !window.__customerAuthenticated) {
        return;
    }
    session.remove(PENDING_BUY_NOW_KEY);

    let data;
    try {
        data = JSON.parse(pending);
    } catch {
        return;
    }
    if (!data?.url || !data.variant_id) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = data.url;
    form.hidden = true;
    const fields = {
        _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        variant_id: data.variant_id,
        quantity: data.quantity ?? 1,
    };
    for (const [name, value] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
}
