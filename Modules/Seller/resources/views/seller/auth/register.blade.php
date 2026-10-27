@extends('theme::layouts.seller.auth')

@section('title', trans('seller::auth.register.title'))
@section('subtitle', trans('seller::auth.register.subtitle'))

@section('content')
    <form method="POST" action="{{ frontRoute('seller.register.store') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        <x-theme::input name="name" :label="trans('seller::auth.register.name')" autocomplete="name" required autofocus />
        <x-theme::input type="email" name="email" :label="trans('seller::auth.register.email')" autocomplete="email" required />

        <div>
            <x-theme::label for="phone" :required="true">{{ trans('seller::auth.register.phone') }}</x-theme::label>
            <div class="flex">
                <span class="flex h-11 items-center rounded-l-lg border border-r-0 border-line-strong bg-surface-alt px-3 text-sm text-ink-soft">+91</span>
                <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" inputmode="numeric" autocomplete="tel-national" maxlength="14" required
                    @if ($errors->has('phone_e164') || $errors->has('phone')) aria-invalid="true" aria-describedby="phone-error" @endif
                    @class([
                        'block h-11 w-full rounded-r-lg border bg-surface px-3.5 text-sm text-ink shadow-soft focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none',
                        'border-danger' => $errors->has('phone_e164') || $errors->has('phone'),
                        'border-line-strong' => ! ($errors->has('phone_e164') || $errors->has('phone')),
                    ])>
            </div>
            <p class="mt-1.5 text-xs text-muted">{{ trans('seller::auth.register.phone_hint') }}</p>
            <x-theme::field-error name="phone_e164" id="phone" />
            <x-theme::field-error name="phone" id="phone" />
        </div>

        <div>
            <x-theme::label for="password" :required="true">{{ trans('seller::auth.register.password') }}</x-theme::label>
            <x-theme::password-input :strength="true" autocomplete="new-password" required />
        </div>
        <div>
            <x-theme::label for="password_confirmation" :required="true">{{ trans('seller::auth.register.password_confirmation') }}</x-theme::label>
            <x-theme::password-input name="password_confirmation" autocomplete="new-password" required />
        </div>

        <div>
            <label class="flex items-start gap-2.5 text-sm text-ink-soft">
                <input type="checkbox" name="accept_terms" value="1" @checked(old('accept_terms')) required class="mt-0.5 size-4 shrink-0 rounded border-line-strong accent-accent">
                <span>{{ trans('seller::auth.register.accept_terms') }}</span>
            </label>
            <x-theme::field-error name="accept_terms" />
        </div>

        <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('seller::auth.register.submit') }}</x-theme::button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        {{ trans('seller::auth.register.have_account') }}
        <a href="{{ frontRoute('seller.login') }}" class="font-medium text-accent hover:text-accent-hover">{{ trans('seller::auth.register.login_link') }}</a>
    </p>
@endsection
