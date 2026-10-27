<!DOCTYPE html>
{{-- Supplier login / register / onboarding entry: marketing panel left (lg+), form right. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('theme::layouts.seller.partials.head')
</head>

<body class="bg-canvas">
    <div class="grid min-h-dvh lg:grid-cols-2">
        <section class="relative hidden overflow-hidden bg-accent p-12 text-accent-ink lg:flex lg:flex-col lg:justify-between">
            <a href="{{ frontRoute('homepage') }}" class="text-lg font-bold">{{ $siteName }}</a>
            <div class="max-w-md">
                <h2 class="text-4xl leading-tight font-extrabold text-balance">{{ trans('theme::seller.auth.headline') }}</h2>
                <ul class="mt-8 space-y-4">
                    @foreach (['wallet' => 'zero_commission', 'truck' => 'reach', 'clock' => 'payments', 'rotate-ccw' => 'returns'] as $icon => $benefit)
                        <li class="flex items-start gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent-ink/15">
                                <x-theme::icon :name="$icon" size="size-5" />
                            </span>
                            <span class="pt-1.5 text-base opacity-90">{{ trans('theme::seller.auth.benefits.'.$benefit) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="text-sm opacity-75">&copy; {{ now()->year }} {{ $siteName }}</p>
        </section>

        <main id="main" class="flex items-center justify-center p-4 sm:p-8">
            <div class="w-full max-w-md">
                <a href="{{ frontRoute('homepage') }}" class="mb-8 inline-flex lg:hidden">
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-8 w-auto">
                </a>
                @hasSection('title')
                    <h1 class="text-2xl font-semibold text-ink">@yield('title')</h1>
                @endif
                @hasSection('subtitle')
                    <p class="mt-1 text-sm text-muted">@yield('subtitle')</p>
                @endif
                <div class="mt-6 rounded-card border border-line bg-surface p-6 shadow-card sm:p-8">
                    @yield('content')
                </div>
            </div>
        </main>
    </div>

    @include('theme::layouts.frontend.partials.notifications')
    @yield('scripts')
    @stack('js-stack')
</body>

</html>
