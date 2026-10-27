import Alpine from 'alpinejs';
import variantMatrix from './variant-matrix';
import imageUploader from './image-uploader';
import { categoryPicker, earningsEstimate, inventoryRow, uploadStatus } from './catalog-tools';

document.addEventListener('alpine:init', () => {
    Alpine.data('variantMatrix', variantMatrix);
    Alpine.data('imageUploader', imageUploader);
    Alpine.data('categoryPicker', categoryPicker);
    Alpine.data('inventoryRow', inventoryRow);
    Alpine.data('earningsEstimate', earningsEstimate);
    Alpine.data('uploadStatus', uploadStatus);
});
