<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex">
<meta name="theme-color" content="{{ config('theme.brand_color') }}">
<title>{{ trim($__env->yieldContent('title')) ? trim($__env->yieldContent('title')).' · ' : '' }}{{ trans('theme::seller.panel_name') }} · {{ config('app.name') }}</title>
<link rel="icon" href="{{ \Modules\Theme\Support\ThemeSettings::faviconUrl() }}">
@fonts
{{-- seller.js registers the panel's Alpine components and then loads app.js. --}}
@vite(['resources/css/app.css', 'resources/js/seller.js'])
@stack('head')
