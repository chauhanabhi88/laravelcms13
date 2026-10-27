@extends('theme::layouts.frontend.master')

@section('meta')
    <meta name="robots" content="noindex">
@endsection

@section('content')
    <div class="container-page py-10 sm:py-14">
        <div class="grid gap-8 lg:grid-cols-[16rem_1fr] lg:gap-12">
            <aside>
                <nav aria-label="{{ trans('customer::customer_front.titles.my_account') }}"
                    class="flex gap-1 overflow-x-auto rounded-2xl border border-line bg-surface p-2 shadow-soft lg:sticky lg:top-24 lg:flex-col">
                    @include('theme::layouts.frontend.partials.account-links', ['linkClass' => 'flex shrink-0 items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-soft hover:bg-surface-alt hover:text-ink'])
                </nav>
            </aside>
            <div class="min-w-0">
                <header class="mb-8">
                    <h1 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">@yield('heading')</h1>
                    @hasSection('subheading')
                        <p class="mt-1.5 text-sm text-muted">@yield('subheading')</p>
                    @endif
                </header>
                @yield('account')
            </div>
        </div>
    </div>
@endsection
