const clamp = (value, min = 0, max = 100) => Math.min(max, Math.max(min, value));

/**
 * Product page gallery (M07 §5.4), mixed into productPage. window.__pdp.images maps
 * "default" / "<colour option id>" to [{ src, zoom, thumb, alt }]. The server renders every
 * set; this only tracks which set and image are active, the desktop hover zoom (the zoom
 * size loads on first hover) and the full-screen viewer (zoom size loads when opened).
 */
export default (pdp) => ({
    images: pdp.images ?? {},
    imageKey: 'default',
    active: 0,
    zoomOn: false,
    zoomX: 50,
    zoomY: 50,
    fullscreen: false,
    touchStartX: null,

    initGallery() {
        this.imageKey = this.images[this.imageKeyForSelection()] ? this.imageKeyForSelection() : 'default';
    },

    get currentImages() {
        return this.images[this.imageKey] ?? this.images.default ?? [];
    },

    get current() {
        return this.currentImages[this.active] ?? this.currentImages[0] ?? null;
    },

    get counterText() {
        return this.format('imageOf', { number: this.active + 1, total: this.currentImages.length });
    },

    setImages(key) {
        const next = this.images[key] ? key : 'default';
        if (next === this.imageKey) {
            return;
        }
        this.imageKey = next;
        this.active = 0;
        this.$nextTick(() => this.$root.querySelectorAll('[data-gallery-track]').forEach((track) => track.scrollTo({ left: 0 })));
    },

    showImage(index) {
        this.active = index;
    },

    nextImage() {
        const total = this.currentImages.length;
        if (total > 1) {
            this.active = (this.active + 1) % total;
            this.syncTrack();
        }
    },

    previousImage() {
        const total = this.currentImages.length;
        if (total > 1) {
            this.active = (this.active - 1 + total) % total;
            this.syncTrack();
        }
    },

    onTrackScroll(track) {
        this.active = Math.round(track.scrollLeft / (track.clientWidth || 1));
    },

    syncTrack() {
        this.$root.querySelectorAll('[data-gallery-track]').forEach((track) => {
            if (track.offsetParent !== null) {
                track.scrollTo({ left: track.clientWidth * this.active });
            }
        });
    },

    zoomStart(event) {
        if (!window.matchMedia('(min-width: 1024px) and (hover: hover)').matches || !this.current?.zoom) {
            return;
        }
        this.zoomOn = true;
        this.zoomMove(event);
    },

    zoomMove(event) {
        if (!this.zoomOn) {
            return;
        }
        const rect = event.currentTarget.getBoundingClientRect();
        this.zoomX = clamp(((event.clientX - rect.left) / rect.width) * 100);
        this.zoomY = clamp(((event.clientY - rect.top) / rect.height) * 100);
    },

    zoomEnd() {
        this.zoomOn = false;
    },

    get lensStyle() {
        return `left: ${clamp(this.zoomX - 16.67, 0, 66.67)}%; top: ${clamp(this.zoomY - 16.67, 0, 66.67)}%;`;
    },

    get zoomStyle() {
        if (!this.zoomOn || !this.current?.zoom) {
            return '';
        }

        return `background-image: url(${JSON.stringify(this.current.zoom)}); background-size: 250%; background-position: ${this.zoomX}% ${this.zoomY}%;`;
    },

    openFullscreen(index = null) {
        if (index !== null) {
            this.active = index;
        }
        if (!this.current) {
            return;
        }
        this.zoomOn = false;
        this.fullscreen = true;
        document.documentElement.style.overflow = 'hidden';
        this.$nextTick(() => document.querySelector('[data-pdp-fullscreen-close]')?.focus());
    },

    closeFullscreen() {
        if (!this.fullscreen) {
            return;
        }
        this.fullscreen = false;
        document.documentElement.style.overflow = '';
        this.syncTrack();
    },

    swipeStart(event) {
        this.touchStartX = event.touches.length === 1 ? event.touches[0].clientX : null;
    },

    swipeEnd(event) {
        // Leave the gesture alone while the image is pinch-zoomed.
        if (this.touchStartX === null || (window.visualViewport?.scale ?? 1) > 1.01) {
            this.touchStartX = null;
            return;
        }
        const distance = event.changedTouches[0].clientX - this.touchStartX;
        this.touchStartX = null;
        if (Math.abs(distance) > 50) {
            distance < 0 ? this.nextImage() : this.previousImage();
        }
    },
});
