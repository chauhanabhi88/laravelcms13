{{-- Wizard footer: Back to the previous step, primary submit. $submitLabel optional. --}}
<div class="mt-8 flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
    @if ($step > 1)
        <x-theme::button variant="ghost" :href="frontRoute('seller.onboarding.show', [$step - 1])">
            <x-theme::icon name="arrow-left" size="size-4" /> {{ trans('seller::onboarding.buttons.back') }}
        </x-theme::button>
    @else
        <span></span>
    @endif
    <x-theme::button size="lg" loading="loading">
        {{ $submitLabel ?? trans('seller::onboarding.buttons.save_continue') }}
        <x-theme::icon name="arrow-right" size="size-4" />
    </x-theme::button>
</div>
