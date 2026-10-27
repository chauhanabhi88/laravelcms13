import { fetchJson } from '../fetch-json';
import pdpGallery from './pdp-gallery';
import pdpShare from './pdp-share';
import variantPicker from './pdp-variant-picker';

const FRESHNESS_MS = 10 * 60 * 1000;

/**
 * Combine Alpine data parts, keeping their getters live (object spread would freeze them).
 */
const mix = (...parts) => parts.reduce((target, part) => Object.defineProperties(target, Object.getOwnPropertyDescriptors(part)), {});

/**
 * Product detail page (M07): one Alpine scope for the variant picker, price block, gallery,
 * sharing and cart buttons, fed by window.__pdp (ProductPageViewModel::payload()).
 * After the page has been open for 10 minutes, prices and availability are refreshed when
 * the tab becomes visible again.
 */
export default (pdp = {}, messages = {}) =>
    mix(
        {
            messages,
            loadedAt: Date.now(),

            init() {
                this.initPicker();
                this.initGallery();

                const refresh = () => this.refreshAvailability();
                document.addEventListener('visibilitychange', refresh);
                window.addEventListener('focus', refresh);
            },

            format(key, replacements = {}) {
                return Object.entries(replacements).reduce(
                    (text, [name, value]) => text.replaceAll(`:${name}`, value),
                    this.messages[key] ?? key,
                );
            },

            async refreshAvailability() {
                if (document.visibilityState !== 'visible' || !pdp.availabilityUrl || Date.now() - this.loadedAt < FRESHNESS_MS) {
                    return;
                }
                this.loadedAt = Date.now();
                try {
                    const data = await fetchJson(pdp.availabilityUrl);
                    this.applyAvailability(data.variants ?? []);
                } catch {
                    /* keep the current data */
                }
            },
        },
        variantPicker(pdp),
        pdpGallery(pdp),
        pdpShare(pdp),
    );
