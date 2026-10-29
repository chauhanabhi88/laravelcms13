<?php

return [
    'name' => 'Report',

    /*
    | Nightly run (02:00): aggregates D-1 and re-aggregates the previous reaggregate_days days for
    | the metrics that keep changing after placement (RTO, returns, cancellations, fees).
    */
    'reaggregate_days' => 30,

    /*
    | Exports with more rows than this are written by a queued job (download from Reports >
    | Exports); smaller ones stream straight away.
    */
    'export_sync_limit' => 5000,
    'exports' => [
        'disk' => 'private',
        'folder' => 'report-exports',
        'formats' => ['csv', 'xlsx'],
        'keep_days' => 7,
    ],

    /*
    | "Today" is computed live from the order tables and cached this long (seconds).
    */
    'live_cache_seconds' => 300,

    /*
    | Leaderboards and ranges.
    */
    'leaderboard_limit' => 20,
    'max_range_days' => 366,
    'default_preset' => 'last_30',
    'presets' => ['today', 'yesterday', 'last_7', 'last_30', 'this_month', 'custom'],
    'compare_modes' => ['previous', 'last_year', 'none'],
    'seller_presets' => ['last_7', 'last_30', 'last_90'],

    /*
    | Most sellers listed in the report filter.
    */
    'seller_filter_limit' => 500,

    /*
    | Chart palette (validated for colour-vision deficiency on the white card surface, in this
    | fixed order: series keep their slot when a filter drops one). "previous" is the muted
    | comparison series.
    */
    'palette' => ['#0d9488', '#eb6834', '#2a78d6', '#c98500', '#6250d6'],
    'palette_previous' => '#94a3b8',
];
