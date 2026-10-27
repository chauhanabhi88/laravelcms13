@extends('theme::layouts.frontend.master')

@section('title', trans("theme::frontend.errors.{$code}_title"))

@section('meta')
    <meta name="robots" content="noindex">
@endsection

@section('content')
    <section class="container-page flex flex-col items-center py-24 text-center sm:py-32">
        <p class="text-sm font-semibold tracking-wider text-accent">{{ $code }}</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-ink sm:text-5xl">{{ trans("theme::frontend.errors.{$code}_title") }}</h1>
        <p class="mt-4 max-w-md text-base text-muted">{{ trans("theme::frontend.errors.{$code}_text") }}</p>
        <div class="mt-10 flex flex-wrap justify-center gap-3">
            <x-theme::button :href="frontRoute('homepage')" size="lg">
                <x-theme::icon name="home" size="size-4" /> {{ trans('theme::frontend.common.back_home') }}
            </x-theme::button>
            @if (\Illuminate\Support\Facades\Route::has('blog.index'))
                <x-theme::button variant="secondary" size="lg" :href="frontRoute('blog.index')">{{ trans('theme::frontend.nav.blog') }}</x-theme::button>
            @endif
        </div>
    </section>
@endsection
