import { prefersReducedMotion } from '../fetch-json';

/**
 * Scroll-snap carousel: native swipe, prev/next buttons, optional autoplay (ms)
 * that pauses on hover/focus and is off under prefers-reduced-motion.
 */
export default ({ autoplay = 0 } = {}) => ({
    autoplay,
    timer: null,
    atStart: true,
    atEnd: false,

    init() {
        this.update();
        this.start();
    },

    destroy() {
        this.stop();
    },

    update() {
        const track = this.$refs.track;
        this.atStart = track.scrollLeft <= 1;
        this.atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 1;
    },

    scroll(direction) {
        const track = this.$refs.track;
        if (direction > 0 && this.atEnd) {
            track.scrollTo({ left: 0, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
            return;
        }
        track.scrollBy({ left: direction * track.clientWidth * 0.9, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
    },

    start() {
        if (!this.autoplay || prefersReducedMotion() || this.timer) {
            return;
        }
        this.timer = setInterval(() => this.scroll(1), this.autoplay);
    },

    stop() {
        clearInterval(this.timer);
        this.timer = null;
    },
});
