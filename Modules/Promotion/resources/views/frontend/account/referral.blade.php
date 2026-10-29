@extends('customer::frontend.account.layout')

@section('title', trans('promotion::front.referral.title'))
@section('heading', trans('promotion::front.referral.title'))

@section('account')
    {{-- Refer & earn (FR-PRM-005): share link, friends who joined and the reward coupons earned. --}}
    <div class="space-y-5">
        @if (! $enabled)
            <x-theme::empty-state icon="share" :title="trans('promotion::front.referral.title')" :text="trans('promotion::front.referral.disabled')" />
        @else
            <section class="rounded-card border border-line bg-surface p-4 shadow-card sm:p-5" data-referral-code="{{ $code }}">
                <p class="text-sm text-ink-soft">{{ trans('promotion::front.referral.intro', ['amount' => $rewardAmount->format()]) }}</p>
                <p class="mt-4 text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('promotion::front.referral.your_code') }}</p>
                <p class="tabular mt-1 text-2xl font-bold tracking-widest text-ink">{{ $code }}</p>

                <p class="mt-4 text-xs font-semibold tracking-wide text-muted uppercase">{{ trans('promotion::front.referral.share_link') }}</p>
                <div class="mt-1 flex flex-wrap gap-2" x-data="{ copied: false }">
                    <input type="text" readonly value="{{ $shareUrl }}" aria-label="{{ trans('promotion::front.referral.share_link') }}"
                        class="h-10 min-w-0 flex-1 rounded-lg border border-line-strong bg-surface-alt px-3 text-sm text-ink">
                    <x-theme::button type="button" variant="secondary" x-on:click="navigator.clipboard?.writeText(@js($shareUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-show="! copied">{{ trans('promotion::front.referral.copy') }}</span>
                        <span x-show="copied" x-cloak>{{ trans('promotion::front.referral.copied') }}</span>
                    </x-theme::button>
                    <x-theme::button variant="secondary" :href="'https://wa.me/?text='.rawurlencode(trans('promotion::front.referral.share_text', ['url' => $shareUrl]))" target="_blank" rel="noopener">
                        <x-theme::icon name="whatsapp" size="size-4" /> {{ trans('promotion::front.referral.share_whatsapp') }}
                    </x-theme::button>
                </div>
            </section>

            <section class="rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
                <h2 class="text-base font-semibold text-ink">{{ trans('promotion::front.referral.friends') }}</h2>
                @if ($referrals->isEmpty())
                    <p class="mt-2 text-sm text-muted">{{ trans('promotion::front.referral.no_friends') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-line">
                        @foreach ($referrals as $referral)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm" data-referral="{{ $referral->id }}">
                                <span class="text-ink">{{ \Illuminate\Support\Str::limit((string) ($referral->referred?->first_name ?: '—'), 1, '***') }} · {{ $referral->created_at?->format('d M Y') }}</span>
                                <x-theme::chip :variant="$referral->status === 'rewarded' ? 'success' : 'neutral'">{{ trans('promotion::front.referral.statuses.'.$referral->status) }}</x-theme::chip>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if ($rewards->isNotEmpty() || $enabled)
            <section class="rounded-card border border-line bg-surface p-4 shadow-card sm:p-5">
                <h2 class="text-base font-semibold text-ink">{{ trans('promotion::front.referral.rewards') }}</h2>
                @if ($rewards->isEmpty())
                    <p class="mt-2 text-sm text-muted">{{ trans('promotion::front.referral.no_rewards') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-line">
                        @foreach ($rewards as $reward)
                            @php
                                $rewardState = $reward->used_count > 0 ? 'used' : ($reward->ends_at !== null && $reward->ends_at->isPast() ? 'expired' : 'active');
                            @endphp
                            <li class="flex items-center justify-between gap-3 py-2 text-sm" data-reward-coupon="{{ $reward->code }}">
                                <span>
                                    <span class="font-semibold tracking-wide text-ink">{{ $reward->code }}</span>
                                    <span class="text-muted">· {{ $reward->benefitText() }}@if ($reward->ends_at) · {{ trans('promotion::front.box.valid_till', ['date' => $reward->ends_at->format('d M Y')]) }}@endif</span>
                                </span>
                                <x-theme::chip :variant="$rewardState === 'active' ? 'success' : 'neutral'">{{ trans('promotion::front.referral.coupon_status.'.$rewardState) }}</x-theme::chip>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>
@endsection
