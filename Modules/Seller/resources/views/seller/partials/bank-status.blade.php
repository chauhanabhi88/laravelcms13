{{--
    Verification state of a bank account. While a penny drop is pending it polls $pollUrl
    every $interval seconds (up to $timeout) and reloads once the bank has answered.
--}}
@php
    $chip = match ($account->verification_status) {
        'verified' => 'success',
        'failed' => 'danger',
        'manual_review' => 'deal',
        default => 'accent',
    };
@endphp
<div class="rounded-lg border border-line bg-surface-alt p-4"
    @if ($account->isPending() && ($pollUrl ?? null))
        x-data="{
            elapsed: 0,
            timedOut: false,
            init() {
                const timer = setInterval(async () => {
                    this.elapsed += {{ (int) ($interval ?? 5) }};
                    if (this.elapsed > {{ (int) ($timeout ?? 60) }}) { this.timedOut = true; clearInterval(timer); return; }
                    try {
                        const response = await fetch(@js($pollUrl), { headers: { Accept: 'application/json' } });
                        const data = await response.json();
                        if (data.done) { clearInterval(timer); window.location.reload(); }
                    } catch (e) {}
                }, {{ (int) ($interval ?? 5) * 1000 }});
            },
        }"
    @endif>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-medium text-ink">
            {{ trans('seller::onboarding.bank.saved_account', ['bank' => $account->bank_name ?: $account->ifsc, 'number' => $account->maskedNumber()]) }}
        </p>
        <x-theme::chip :variant="$chip">
            @if ($account->isPending())
                <x-theme::icon name="spinner" size="size-3.5" />
            @endif
            {{ $account->statusLabel() }}
        </x-theme::chip>
    </div>
    @if ($account->isPending())
        <p class="mt-2 text-sm text-muted" @if ($pollUrl ?? null) x-show="! timedOut" @endif>{{ trans('seller::onboarding.bank.verifying') }}</p>
        @if ($pollUrl ?? null)
            <p class="mt-2 text-sm text-muted" x-show="timedOut" x-cloak>{{ trans('seller::onboarding.bank.still_verifying') }}</p>
        @endif
    @endif
    @if ($account->verified_name)
        <p class="mt-2 text-sm text-ink-soft">{{ trans('seller::onboarding.bank.name_at_bank', ['name' => $account->verified_name]) }}</p>
    @endif
    @if ($account->verification_note)
        <p class="mt-2 text-sm text-ink-soft">{{ $account->verification_note }}</p>
    @endif
</div>
