import { fetchJson } from '../fetch-json';

/**
 * Product image uploader (wizard step 4, FR-PRD-003): a shared set and optional per-colour
 * sets. Files are checked in the browser (type, size, minimum pixels) before upload; the
 * server checks again, re-encodes and resizes. Drag a thumbnail onto another in the same set
 * to reorder; the first shared image is the main one.
 */
export default function imageUploader(config) {
    return {
        images: config.images,
        groups: config.groups,
        busy: false,
        error: null,
        dragging: null,

        inGroup(groupId) {
            return this.images.filter((image) => (image.attribute_option_id ?? null) === groupId);
        },

        limit(groupId) {
            return groupId === null ? config.maxImages : config.maxPerOption;
        },

        canAdd(groupId) {
            return this.inGroup(groupId).length < this.limit(groupId);
        },

        dimensions(file) {
            return new Promise((resolve) => {
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = () => {
                    resolve({ width: img.naturalWidth, height: img.naturalHeight });
                    URL.revokeObjectURL(url);
                };
                img.onerror = () => {
                    resolve({ width: 0, height: 0 });
                    URL.revokeObjectURL(url);
                };
                img.src = url;
            });
        },

        async check(file) {
            const extension = file.name.split('.').pop().toLowerCase();
            if (!config.types.includes(extension) || !file.type.startsWith('image/')) {
                return config.labels.wrongType;
            }
            if (file.size > config.maxKb * 1024) {
                return config.labels.tooBig;
            }
            const { width, height } = await this.dimensions(file);
            if (width < config.minPx || height < config.minPx) {
                return config.labels.tooSmall;
            }

            return null;
        },

        async upload(groupId, fileList) {
            this.error = null;
            this.busy = true;

            for (const file of Array.from(fileList ?? [])) {
                if (!this.canAdd(groupId)) {
                    this.error = config.labels.full;
                    break;
                }
                const problem = await this.check(file);
                if (problem) {
                    this.error = `${file.name}: ${problem}`;
                    continue;
                }
                const body = new FormData();
                body.append('image', file);
                if (groupId !== null) {
                    body.append('attribute_option_id', groupId);
                }
                try {
                    const data = await fetchJson(config.uploadUrl, { method: 'POST', body });
                    this.images = data.images;
                } catch (failure) {
                    this.error = `${file.name}: ${failure.message}`;
                }
            }

            this.busy = false;
        },

        async remove(path) {
            if (!window.confirm(config.labels.deleteConfirm)) {
                return;
            }
            await this.send(config.deleteUrl, 'DELETE', { path });
        },

        async reorder(groupId, fromPath, toPath) {
            if (fromPath === toPath) {
                return;
            }
            const group = this.inGroup(groupId).map((image) => image.path);
            const from = group.indexOf(fromPath);
            const to = group.indexOf(toPath);
            if (from < 0 || to < 0) {
                return;
            }
            group.splice(to, 0, group.splice(from, 1)[0]);

            const others = this.images.filter((image) => (image.attribute_option_id ?? null) !== groupId).map((image) => image.path);
            const paths = groupId === null ? [...group, ...others] : [...others, ...group];
            await this.send(config.reorderUrl, 'POST', { paths });
        },

        move(groupId, path, step) {
            const group = this.inGroup(groupId);
            const index = group.findIndex((image) => image.path === path);
            const target = group[index + step];
            if (target) {
                this.reorder(groupId, path, target.path);
            }
        },

        async send(url, method, body) {
            this.error = null;
            this.busy = true;
            try {
                const data = await fetchJson(url, { method, body });
                this.images = data.images;
            } catch (failure) {
                this.error = failure.message;
            } finally {
                this.busy = false;
            }
        },
    };
}
