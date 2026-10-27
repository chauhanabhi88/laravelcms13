<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>{{ trim($__env->yieldContent('title')) ? trim($__env->yieldContent('title')).' · '.config('app.name') : config('app.name') }}</title>
    @yield('meta')

    <meta name="theme-color" content="{{ config('theme.brand_color') }}">
    <link rel="icon" href="{{ \Modules\Theme\Support\ThemeSettings::faviconUrl() }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @foreach ($cssFiles ?? [] as $css)
        <link media="all" type="text/css" rel="stylesheet" href="{{ asset($css) }}">
    @endforeach
    @stack('head')
</head>

<body class="{{ $bodyClassName ?? '' }} flex min-h-dvh flex-col pb-16 md:pb-0">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:shadow-pop">Skip to content</a>

    @include('theme::layouts.frontend.partials.header')

    <main id="main" class="flex-1">
        @yield('content')
    </main>

    @include('theme::layouts.frontend.partials.footer')
    @include('theme::layouts.frontend.partials.bottom-nav')
    @include('theme::layouts.frontend.partials.mobile-drawer')
    @include('theme::layouts.frontend.partials.notifications')

    {{-- Seeds for the Alpine cart / wishlist stores (resources/js/components/stores.js). --}}
    <script>
        window.__cartCount = @js($cartCount ?? 0);
        window.__wishlistIds = @js($wishlistIds ?? []);
    </script>

    @if (config('customer.show_customer_online_offline_grid') && auth('customer')->check())
        <script>
            window.customerPresence = {
                url: @js(frontRoute('customer.customer_online_offline_log')),
                interval: 60000,
            };
        </script>
    @endif

    @foreach ($jsFiles ?? [] as $js)
        <script src="{{ asset($js) }}" type="text/javascript"></script>
    @endforeach
    @yield('scripts')
    @stack('js-stack')
</body>

</html>
