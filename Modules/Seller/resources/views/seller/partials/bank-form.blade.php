{{--
    Bank account fields with IFSC autofill; used by onboarding step 3 and Settings › Bank.
    Needs $accountTypes and optionally $account (the saved primary account).
--}}
@php
    $account ??= null;
    $maxMb = (int) config('seller.kyc_max_file_mb');
    $ifscUrl = frontRoute('seller.ifsc.lookup', ['IFSC0000000']);
@endphp
<div class="space-y-5" x-data="{
        ifsc: @js(old('ifsc', $account?->ifsc ?? '')),
        bank: @js($account ? trim($account->bank_name.' · '.$account->branch, ' ·') : ''),
        lookupMessage: '',
        looking: false,
        async lookup() {
            this.ifsc = this.ifsc.toUpperCase().trim();
            this.bank = '';
            this.lookupMessage = '';
            if (! /^[A-Z]{4}0[A-Z0-9]{6}$/.test(this.ifsc)) { return; }
            this.looking = true;
            try {
                const response = await fetch(@js($ifscUrl).replace('IFSC0000000', this.ifsc), { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (data.found) { this.bank = [data.bank, data.branch].filter(Boolean).join(' · '); } else { this.lookupMessage = data.message || ''; }
            } catch (e) {
                this.lookupMessage = @js(trans('seller::onboarding.bank.lookup_unavailable'));
            } finally {
                this.looking = false;
            }
        },
    }">
    <x-theme::input name="account_holder_name" :value="$account?->account_holder_name" :label="trans('seller::onboarding.bank.holder_name')" maxlength="120" autocomplete="off" required />

    <div class="grid gap-5 sm:grid-cols-2">
        <x-theme::input name="account_number" :label="trans('seller::onboarding.bank.account_number')" inputmode="numeric" maxlength="18" autocomplete="off" required />
        <x-theme::input name="account_number_confirmation" :label="trans('seller::onboarding.bank.account_number_confirmation')" inputmode="numeric" maxlength="18" autocomplete="off" required x-on:paste.prevent />
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <x-theme::input name="ifsc" :label="trans('seller::onboarding.bank.ifsc')" :hint="trans('seller::onboarding.bank.ifsc_hint')" maxlength="11"
                autocomplete="off" class="uppercase tracking-wide" required x-model="ifsc" x-on:change="lookup()" x-on:blur="lookup()" />
            <p class="mt-1.5 text-xs text-muted" x-show="looking" x-cloak>{{ trans('seller::onboarding.bank.looking_up') }}</p>
            <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-success" x-show="bank" x-cloak>
                <x-theme::icon name="check-circle" size="size-3.5" /> <span x-text="bank"></span>
            </p>
            <p class="mt-1.5 text-xs text-danger" x-show="lookupMessage" x-cloak x-text="lookupMessage"></p>
        </div>
        <x-theme::select name="account_type" :value="$account?->account_type ?? 'current'" :options="$accountTypes" :label="trans('seller::onboarding.bank.account_type')" required />
    </div>

    <div>
        @if ($account?->chequeDocument)
            <p class="mb-2 flex items-center gap-2 text-sm text-ink-soft">
                <x-theme::icon name="document" size="size-4" />
                <a href="{{ frontRoute('seller.kyc.download', [$account->chequeDocument->id]) }}" target="_blank" rel="noopener" class="font-medium text-accent hover:text-accent-hover">
                    {{ trans('seller::onboarding.bank.cheque_on_file', ['name' => $account->chequeDocument->original_name]) }}
                </a>
            </p>
        @endif
        <x-theme::dropzone name="cancelled_cheque" :multiple="false" :max-files="1" :max-size-mb="$maxMb"
            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" :label="trans('seller::onboarding.bank.cheque')" />
        <p class="mt-1.5 text-xs text-muted">{{ trans('seller::onboarding.bank.cheque_hint', ['size' => $maxMb]) }}</p>
    </div>
</div>
