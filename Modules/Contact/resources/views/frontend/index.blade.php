@extends('theme::layouts.frontend.master')

@section('title', trans('contact::contact_front.titles.contact_us'))

@section('meta')
    <x-theme::seo-meta :title="trans('contact::contact_front.titles.contact_us')" :description="trans('contact::contact_front.titles.intro')" />
@endsection

@section('content')
    <section class="border-b border-line bg-surface">
        <div class="container-page py-12 sm:py-16">
            <x-theme::breadcrumbs :items="[trans('contact::contact_front.titles.contact_us') => null]" />
            <h1 class="mt-6 text-3xl font-semibold tracking-tight text-ink sm:text-4xl">{{ trans('contact::contact_front.titles.contact_us') }}</h1>
            <p class="mt-3 max-w-2xl text-base text-muted">{{ trans('contact::contact_front.titles.intro') }}</p>
        </div>
    </section>

    <section class="container-page py-12 sm:py-16">
        <div class="grid gap-10 lg:grid-cols-5">
            <x-theme::card class="lg:col-span-3">
                <form method="POST" action="{{ frontRoute('contact.store') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
                    @csrf
                    {{-- Honeypot: hidden from people, irresistible to bots. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-theme::input name="name" :label="trans('contact::contact_front.labels.name')" autocomplete="name" required minlength="3" maxlength="255"
                            :value="auth('customer')->user() ? trim(auth('customer')->user()->first_name.' '.auth('customer')->user()->last_name) : null" />
                        <x-theme::input type="email" name="email" :label="trans('contact::contact_front.labels.email')" autocomplete="email" required
                            :value="auth('customer')->user()?->email" />
                    </div>
                    <x-theme::input type="tel" name="contact_number" :label="trans('contact::contact_front.labels.contact_number')" autocomplete="tel"
                        inputmode="numeric" optional pattern="[0-9]{10}" :value="auth('customer')->user()?->contact_number" />
                    <x-theme::textarea name="content" :label="trans('contact::contact_front.labels.enter_question')" required rows="6" maxlength="5000"
                        :placeholder="trans('contact::contact_front.labels.message_placeholder')" />

                    <div class="flex flex-col gap-4 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-muted">{{ trans('contact::contact_front.labels.response_time') }}</p>
                        <x-theme::button size="lg" loading="loading">
                            <x-theme::icon name="mail" size="size-4" /> {{ trans('contact::contact_front.buttons.save') }}
                        </x-theme::button>
                    </div>
                </form>
            </x-theme::card>

            <aside class="lg:col-span-2">
                <div class="rounded-2xl border border-line bg-surface-alt p-6 sm:p-8">
                    <h2 class="text-base font-semibold text-ink">{{ $info?->title ?: trans('contact::contact_front.titles.reach_us') }}</h2>
                    @if ($info?->content)
                        <div class="prose-content mt-4 text-sm! leading-6!">{!! $info->content !!}</div>
                    @else
                        <p class="mt-4 text-sm text-muted">{{ trans('contact::contact_front.labels.response_time') }}</p>
                    @endif
                </div>
            </aside>
        </div>
    </section>
@endsection
