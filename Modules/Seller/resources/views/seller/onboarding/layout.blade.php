<!DOCTYPE html>
{{--
    Onboarding wizard / application status: a focused page without the panel sidebar
    (panel sections open after approval). Pages set @section('title') and @section('content');
    wizard steps also pass $steps / $step for the progress bar.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('theme::layouts.seller.partials.head')
</head>

<body class="bg-canvas">
    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex h-16 max-w-3xl items-center justify-between gap-4 px-4">
            <a href="{{ frontRoute('homepage') }}" class="flex items-center">
                <img src="{{ \Modules\Theme\Support\ThemeSettings::logoUrl() }}" alt="{{ config('app.name') }}" class="h-7 w-auto">
            </a>
            <form method="POST" action="{{ frontRoute('seller.logout') }}">
                @csrf
                <button type="submit" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-ink-soft hover:bg-surface-alt hover:text-ink">
                    <x-theme::icon name="logout" size="size-4" /> {{ trans('theme::frontend.account.logout') }}
                </button>
            </form>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-3xl px-4 py-6 sm:py-10">
        @isset($steps, $step)
            <p class="text-sm font-medium text-muted">{{ trans('seller::onboarding.title') }}</p>
            <x-theme::stepper class="mt-3 mb-6" :steps="array_values($steps)" :current="$step" />
        @endisset

        @if (($seller ?? null)?->status === \Modules\Seller\Enums\SellerStatus::ChangesRequested && $seller->status_reason && isset($step))
            <x-theme::alert type="warning" :title="trans('seller::onboarding.status.reason')" class="mb-6">{{ $seller->status_reason }}</x-theme::alert>
        @endif

        @yield('content')
    </main>

    @include('theme::layouts.frontend.partials.notifications')
    @stack('js-stack')
</body>

</html>
