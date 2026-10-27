@extends('theme::layouts.frontend.master')

@section('meta')
    <meta name="robots" content="noindex">
@endsection

@section('content')
    <section class="relative isolate">
        <div class="absolute inset-x-0 top-0 -z-10 h-80 bg-[radial-gradient(40rem_20rem_at_50%_0%,var(--accent-soft),transparent)]"></div>
        <div class="container-page flex justify-center py-12 sm:py-20">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">@yield('heading')</h1>
                    @hasSection('subheading')
                        <p class="mt-2 text-sm text-muted">@yield('subheading')</p>
                    @endif
                </div>
                <x-theme::card>
                    @yield('form')
                </x-theme::card>
                @hasSection('footer')
                    <p class="mt-6 text-center text-sm text-muted">@yield('footer')</p>
                @endif
            </div>
        </div>
    </section>
@endsection
