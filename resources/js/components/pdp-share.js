const formatRupees = (amount) => `₹${new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 }).format(amount)}`;

async function copyText(text) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return;
    }
    const field = Object.assign(document.createElement('textarea'), { value: text, readOnly: true });
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    document.execCommand('copy');
    field.remove();
}

/**
 * Product page sharing (M07 §5.6), mixed into productPage: the native share sheet when the
 * browser has one, else a WhatsApp / Copy link / Facebook menu; and the reseller "Share &
 * Earn" modal (own price ≥ selling price, margin, a copyable message, images shared as
 * files where supported, otherwise downloaded).
 */
export default (pdp) => ({
    shareOpen: false,
    resellerPrice: '',

    shareText() {
        return this.format('shareText', { name: pdp.name, price: this.priceText });
    },

    whatsappUrl() {
        return `https://wa.me/?text=${encodeURIComponent(`${this.shareText()} ${pdp.url}`)}`;
    },

    facebookUrl() {
        return `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(pdp.url)}`;
    },

    async share() {
        if (navigator.share) {
            try {
                await navigator.share({ title: pdp.name, text: this.shareText(), url: pdp.url });
                return;
            } catch (failure) {
                if (failure?.name === 'AbortError') {
                    return;
                }
            }
        }
        this.shareOpen = !this.shareOpen;
    },

    async copyLink() {
        this.shareOpen = false;
        try {
            await copyText(pdp.url);
            this.$store.toast.push(this.messages.copied);
        } catch {
            /* clipboard blocked */
        }
    },

    currentPrice() {
        return Number(this.priceVariant?.price ?? pdp.price ?? 0);
    },

    openShareEarn() {
        this.resellerPrice = String(Math.ceil(this.currentPrice()));
        this.$dispatch('open-modal', 'share-earn');
    },

    get resellerPriceError() {
        const price = Number(this.resellerPrice);

        return this.resellerPrice === '' || Number.isNaN(price) || price < this.currentPrice() ? this.format('priceTooLow', { price: this.priceText }) : '';
    },

    get marginText() {
        return formatRupees(Math.max(0, Number(this.resellerPrice || 0) - this.currentPrice()));
    },

    get resellerText() {
        const sizes = pdp.shareSizes ?? [];

        return [
            pdp.name,
            this.format('textPrice', { price: formatRupees(Number(this.resellerPrice || 0)) }),
            sizes.length ? this.format('textSizes', { sizes: sizes.join(', ') }) : '',
            this.messages.textPerks,
        ]
            .filter(Boolean)
            .join('\n');
    },

    async copyResellerText() {
        if (this.resellerPriceError) {
            return;
        }
        try {
            await copyText(this.resellerText);
            this.$store.toast.push(this.messages.detailsCopied);
        } catch {
            /* clipboard blocked */
        }
    },

    async shareImages() {
        const urls = (pdp.shareImages ?? []).slice(0, 10);
        if (urls.length === 0) {
            return;
        }

        try {
            const files = await Promise.all(
                urls.map(async (url, index) => {
                    const blob = await (await fetch(url, { credentials: 'same-origin' })).blob();
                    const extension = (blob.type.split('/')[1] ?? 'jpg').replace('jpeg', 'jpg');

                    return new File([blob], `product-${pdp.productId}-${index + 1}.${extension}`, { type: blob.type });
                }),
            );
            if (navigator.canShare?.({ files })) {
                await navigator.share({ files, text: this.resellerText });
                return;
            }
        } catch (failure) {
            if (failure?.name === 'AbortError') {
                return;
            }
        }

        urls.forEach((url, index) => {
            const link = Object.assign(document.createElement('a'), { href: url, download: `product-${pdp.productId}-${index + 1}` });
            document.body.appendChild(link);
            link.click();
            link.remove();
        });
    },
});
