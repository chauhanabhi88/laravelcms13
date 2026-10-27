@extends('theme::layouts.seller.master')

@section('title', $staff ? trans('seller::panel.staff.edit') : trans('seller::panel.staff.create'))

@section('content')
    @php
        $enabled = (int) config('core.enabled');
        $disabled = (int) config('core.disabled');
    @endphp
    <form method="POST" action="{{ $staff ? frontRoute('seller.staff.update', [$staff->id]) : frontRoute('seller.staff.store') }}"
        class="max-w-3xl space-y-5 rounded-card border border-line bg-surface p-5 shadow-card" x-data="{ loading: false }" x-on:submit="loading = true">
        @csrf
        @if ($staff)
            @method('PUT')
        @endif

        <x-theme::input name="name" :value="$staff?->name" :label="trans('seller::panel.staff.name')" maxlength="120" required />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-theme::input type="email" name="email" :value="$staff?->email" :label="trans('seller::panel.staff.email')" required />
            <x-theme::input type="tel" name="phone" :value="\Modules\Seller\Support\Phone::display($staff?->phone)" :label="trans('seller::panel.staff.phone')" inputmode="numeric" maxlength="14" required />
        </div>
        <x-theme::field-error name="phone_e164" />

        <fieldset>
            <legend class="mb-2 text-sm font-medium text-ink">{{ trans('seller::panel.staff.role') }}</legend>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach ($roles as $role => $label)
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-line-strong p-3 text-sm has-checked:border-accent has-checked:bg-accent-soft">
                        <input type="radio" name="role" value="{{ $role }}" @checked(old('role', $staff?->role) === $role) required class="mt-0.5 accent-accent">
                        <span>
                            <span class="block font-medium text-ink">{{ $label }}</span>
                            <span class="text-xs text-muted">{{ trans('seller::panel.staff.role_help.'.$role) }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-theme::field-error name="role" />
        </fieldset>

        <x-theme::select name="status" :value="$staff?->status ?? $enabled" :label="trans('seller::panel.staff.status')"
            :options="[$enabled => trans('seller::panel.staff.enabled'), $disabled => trans('seller::panel.staff.disabled')]" />

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-theme::label for="password" :required="! $staff">{{ trans('seller::panel.staff.password') }}</x-theme::label>
                <x-theme::password-input :strength="true" autocomplete="new-password" :required="! $staff" />
                @if ($staff)
                    <p class="mt-1.5 text-xs text-muted">{{ trans('seller::panel.staff.password_hint') }}</p>
                @endif
            </div>
            <div>
                <x-theme::label for="password_confirmation" :required="! $staff">{{ trans('seller::panel.staff.password_confirmation') }}</x-theme::label>
                <x-theme::password-input name="password_confirmation" autocomplete="new-password" :required="! $staff" />
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-line pt-5">
            <x-theme::button variant="ghost" :href="frontRoute('seller.staff.index')">{{ trans('seller::panel.common.cancel') }}</x-theme::button>
            <x-theme::button loading="loading">{{ trans('seller::panel.staff.save') }}</x-theme::button>
        </div>
    </form>
@endsection
