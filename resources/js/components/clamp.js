/**
 * "Read more" for line-clamped text: the toggle only shows when the text overflows.
 */
export default () => ({
    expanded: false,
    overflowing: false,

    init() {
        this.$nextTick(() => {
            const content = this.$refs.content;
            this.overflowing = content.scrollHeight > content.clientHeight + 1;
        });
    },
});
