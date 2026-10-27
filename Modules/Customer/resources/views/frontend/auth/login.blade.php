@extends('customer::frontend.auth.layout')

@section('title', trans('theme::frontend.account.login'))
@section('heading', trans('customer::customer_front.titles.login'))
@section('subheading', trans('customer::customer_front.titles.login_text'))

@section('form')
    <form method="POST" action="{{ frontRoute('customer.loginpost') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        <x-theme::input type="email" :name="config('customer.login_column')" :label="trans('customer::customer_front.labels.email')"
            autocomplete="email" required autofocus />

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="text-sm font-medium text-ink">{{ trans('customer::customer_front.labels.password') }}<span class="text-danger" aria-hidden="true"> *</span></label>
                <a href="{{ frontRoute('customer.forgot') }}" class="text-sm font-medium text-accent hover:text-accent-hover">{{ trans('customer::customer_front.buttons.forgot') }}</a>
            </div>
            <x-theme::password-input required />
        </div>

        <label class="flex items-center gap-2.5 text-sm text-ink-soft">
            <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 rounded border-line-strong accent-accent">
            {{ trans('customer::customer_front.labels.remember') }}
        </label>

        <x-theme::button class="w-full" size="lg" loading="loading">{{ trans('customer::customer_front.buttons.login') }}</x-theme::button>
    </form>

    <div class="mt-6 border-t border-line pt-5 text-center text-sm">
        <a href="{{ frontRoute('customer.login') }}" class="inline-flex items-center gap-1.5 font-medium text-accent hover:text-accent-hover">
            <x-theme::icon name="smartphone" size="size-4" /> {{ trans('customer::customer_front.otp.title') }}
        </a>
    </div>
@endsection

@section('footer')
    {{ trans('customer::customer_front.messages.no_account') }}
    <a href="{{ frontRoute('customer.signup') }}" class="font-medium text-accent hover:text-accent-hover">{{ trans('customer::customer_front.buttons.signup') }}</a>
@endsection
