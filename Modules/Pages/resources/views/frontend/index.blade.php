@extends('theme::layouts.frontend.master')

@php $pageTitle = $page->meta_title ?: $page->title; @endphp

@section('title', $pageTitle)

@section('meta')
    <x-theme::seo-meta :title="$pageTitle" :description="$page->meta_description" :image="$bannerUrl"
        :canonical="frontRoute('page', [$page->slug])" />
@endsection

@section('content')
    <section @class([
        'relative isolate overflow-hidden border-b border-line',
        'bg-ink' => $bannerUrl,
        'bg-surface' => ! $bannerUrl,
    ])>
        @if ($bannerUrl)
            <img src="{{ $bannerUrl }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-50" fetchpriority="high">
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-ink/80 to-ink/20"></div>
        @endif
        <div @class(['container-page max-w-4xl', 'py-24 sm:py-32' => $bannerUrl, 'py-12 sm:py-16' => ! $bannerUrl])>
            <x-theme::breadcrumbs :items="[$page->title => null]" class="{{ $bannerUrl ? '[&_*]:text-white/80!' : '' }}" />
            <h1 @class([
                'mt-6 text-3xl font-semibold tracking-tight text-balance sm:text-5xl',
                'text-white' => $bannerUrl,
                'text-ink' => ! $bannerUrl,
            ])>{{ $page->title }}</h1>
        </div>
    </section>

    <div class="container-page max-w-4xl py-12 sm:py-16">
        <div class="prose-content sm:text-lg sm:leading-8 {{ $page->slug }}">{!! $page->getPageBody() !!}</div>
    </div>
@endsection
