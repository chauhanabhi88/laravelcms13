<!DOCTYPE html>
{{--
    Supplier panel layout. Pages: @extends('theme::layouts.seller.master'), @section('title'), @section('content').
    Optional: @section('page_actions') for buttons beside the page title, $sellerStoreName / $sellerUnreadCount from the Seller module.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('theme::layouts.seller.partials.head')
</head>

<body class="bg-canvas">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:shadow-pop">{{ trans('theme::seller.skip_to_content') }}</a>

    <div x-data="{
            collapsed: false,
            init() { try { this.collapsed = localStorage.getItem('seller.sidebar.collapsed') === '1' } catch {} },
            toggleCollapsed() {
                this.collapsed = ! this.collapsed;
                try { localStorage.setItem('seller.sidebar.collapsed', this.collapsed ? '1' : '0') } catch {}
            },
        }" class="flex min-h-dvh">
        <aside class="sticky top-0 hidden h-dvh shrink-0 flex-col border-r border-line bg-surface transition-[width] duration-200 lg:flex"
            x-bind:class="collapsed ? 'w-16' : 'w-64'" aria-label="{{ trans('theme::seller.navigation') }}">
            <a href="{{ $sellerDashboardUrl ?? frontRoute('homepage') }}" class="flex h-16 shrink-0 items-center gap-2 border-b border-line px-4">
                <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-7 w-auto" x-show="! collapsed">
                <span class="flex size-8 items-center justify-center rounded-lg bg-accent text-sm font-bold text-accent-ink" x-show="collapsed" x-cloak aria-hidden="true">
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($siteName, 0, 1)) }}
                </span>
            </a>
            <div class="flex-1 overflow-y-auto">
                @include('theme::layouts.seller.partials.sidebar', ['collapsible' => true])
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            @include('theme::layouts.seller.partials.topbar')

            <main id="main" class="flex-1 p-4 lg:p-6">
                @hasSection('title')
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <h1 class="text-xl font-semibold text-ink lg:text-2xl">@yield('title')</h1>
                        @yield('page_actions')
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <x-theme::drawer name="seller-menu" side="left" :title="trans('theme::seller.panel_name')">
        @include('theme::layouts.seller.partials.sidebar', ['collapsible' => false])
    </x-theme::drawer>

    @include('theme::layouts.frontend.partials.notifications')

    @yield('scripts')
    @stack('js-stack')
</body>

</html>
