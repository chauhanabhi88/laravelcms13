<?php

/*
| Marketplace taxonomy defaults (admin > Settings > Catalog). Read through
| Modules\Catalog\Support\CatalogSettings, never with settings() directly.
*/

$numberSetting = fn (string $key, int $min, int $max): array => [
    'label' => "catalog::catalog.settings.{$key}.label",
    'type' => 'number',
    'placeholder' => "catalog::catalog.settings.{$key}.label",
    'storage' => 'db',
    'comment' => "catalog::catalog.settings.{$key}.comment",
    'min' => $min,
    'max' => $max,
];

return [
    'catalog::catalog.settings.catalog' => [
        'default_return_window' => $numberSetting('default_return_window', 0, 90),
        'default_dispatch_sla_hours' => $numberSetting('default_dispatch_sla_hours', 1, 720),
        'default_gst_percent' => $numberSetting('default_gst_percent', 0, 40),
        'max_category_depth' => $numberSetting('max_category_depth', 1, 8),
        'max_variant_axes' => $numberSetting('max_variant_axes', 1, 4),
    ],
];
