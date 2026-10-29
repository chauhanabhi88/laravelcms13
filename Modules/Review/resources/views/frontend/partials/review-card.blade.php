{{--
    One published review (plan M16 §7): rating, title, text clamped with "Read more", photos with
    a lightbox, "Verified Buyer · variant · date", the seller's reply, Helpful (n) and Report.
    Expects $review (images loaded), $voted, $customerId and $reportReasons. Helpful / Report
    post to JSON endpoints; the forms also work without JavaScript.
--}}
@php
    $card = \Modules\Review\Support\ReviewPresenter::card($review);
    $isOwn = $customerId !== null && $review->customer_id === (int) $customerId;
    $helpfulLabel = trans('review::front.card.helpful');
    $longBody = mb_strlen((string) $review->body) > 280;
@endphp
<article id="review-{{ $review->id }}" data-review="{{ $review->id }}" class="border-t border-line py-5 first:border-t-0 first:pt-0"
    x-data="{
        expanded: false,
        voted: {{ $voted ? 'true' : 'false' }},
        count: {{ (int) $review->helpful_count }},
        busy: false,
        reporting: false,
        reported: false,
        photo: null,
        photos: @js($card['photos']),
        async vote(url) {
            if (this.busy) return;
            this.busy = true;
            try {
                const data = await window.fetchJson(url, { method: 'POST' });
                this.voted = data.voted;
                this.count = data.helpful_count;
            } catch (error) {
                this.$store.toast.push(error.message, 'error');
            } finally {
                this.busy = false;
            }
        },
        async report(form) {
            if (this.busy) return;
            this.busy = true;
            try {
                const data = await window.fetchJson(form.action, { method: 'POST', body: new FormData(form) });
                this.reported = true;
                this.reporting = false;
                this.$store.toast.push(data.message, 'success');
            } catch (error) {
                this.$store.toast.push(error.message, 'error');
            } finally {
                this.busy = false;
            }
        },
    }">
    <div class="flex flex-wrap items-center gap-2">
        <x-theme::rating-badge :value="$review->rating" />
        @if ($review->title)
            <h3 class="text-sm font-semibold text-ink">{{ $review->title }}</h3>
        @endif
    </div>

    @if ($review->body)
        <p class="mt-2 text-sm leading-6 whitespace-pre-line text-ink-soft" @if ($longBody) x-bind:class="expanded ? '' : 'line-clamp-4'" @endif>{{ $review->body }}</p>
        @if ($longBody)
            <button type="button" class="mt-1 text-sm font-medium text-accent hover:underline" x-on:click="expanded = ! expanded"
                x-text="expanded ? @js(trans('review::front.card.read_less')) : @js(trans('review::front.card.read_more'))">{{ trans('review::front.card.read_more') }}</button>
        @endif
    @endif

    @if ($card['photos'] !== [])
        <ul class="mt-3 flex flex-wrap gap-2" aria-label="{{ trans('review::front.form.current_photos') }}">
            @foreach ($card['photos'] as $index => $photo)
                <li>
                    <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" x-on:click.prevent="photo = {{ $index }}"
                        class="block size-20 overflow-hidden rounded-chip border border-line bg-surface-alt focus-visible:outline-2 focus-visible:outline-accent">
                        <img src="{{ $photo['thumb'] ?? $photo['url'] }}" alt="{{ $photo['alt'] }}" loading="lazy" class="size-full object-cover">
                    </a>
                </li>
            @endforeach
        </ul>

        <template x-teleport="body">
            <div x-show="photo !== null" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4"
                role="dialog" aria-modal="true" aria-label="{{ trans('review::front.summary.photo_alt') }}"
                x-on:click.self="photo = null" x-on:keydown.escape.window="photo = null"
                x-on:keydown.arrow-right.window="if (photo !== null) photo = (photo + 1) % photos.length"
                x-on:keydown.arrow-left.window="if (photo !== null) photo = (photo + photos.length - 1) % photos.length">
                <img x-bind:src="photo !== null ? photos[photo].url : ''" x-bind:alt="photo !== null ? photos[photo].alt : ''" class="max-h-[85vh] max-w-full rounded-card object-contain">
                <button type="button" class="absolute top-4 right-4 rounded-full bg-black/60 p-2 text-white" x-on:click="photo = null" aria-label="{{ trans('review::front.card.close_photo') }}">
                    <x-theme::icon name="x" />
                </button>
                @if (count($card['photos']) > 1)
                    <button type="button" class="absolute left-3 rounded-full bg-black/60 p-2 text-white" x-on:click="photo = (photo + photos.length - 1) % photos.length" aria-label="{{ trans('review::front.card.previous_photo') }}">
                        <x-theme::icon name="chevron-left" />
                    </button>
                    <button type="button" class="absolute right-3 rounded-full bg-black/60 p-2 text-white" x-on:click="photo = (photo + 1) % photos.length" aria-label="{{ trans('review::front.card.next_photo') }}">
                        <x-theme::icon name="chevron-right" />
                    </button>
                @endif
            </div>
        </template>
    @endif

    <p class="mt-3 flex flex-wrap items-center gap-x-1.5 text-xs text-muted">
        <span class="font-medium text-ink-soft">{{ $card['author'] }}</span>
        <span aria-hidden="true">·</span>
        <span class="inline-flex items-center gap-1 text-success"><x-theme::icon name="check-circle" size="size-3.5" /> {{ trans('review::front.card.verified') }}</span>
        @if ($card['variant'])
            <span aria-hidden="true">·</span>
            <span>{{ $card['variant'] }}</span>
        @endif
        @if ($card['date'])
            <span aria-hidden="true">·</span>
            <time datetime="{{ $card['published_at'] }}">{{ $card['date'] }}</time>
        @endif
    </p>

    @if ($card['seller_reply'])
        <div class="mt-3 rounded-card border-l-4 border-accent bg-surface-alt px-3 py-2" data-seller-reply>
            <p class="text-xs font-semibold text-ink">{{ trans('review::front.card.seller_reply') }}@if ($card['seller_reply']['date']) <span class="font-normal text-muted">· {{ $card['seller_reply']['date'] }}</span>@endif</p>
            <p class="mt-1 text-sm leading-6 whitespace-pre-line text-ink-soft">{{ $card['seller_reply']['text'] }}</p>
        </div>
    @endif

    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs">
        @if ($isOwn)
            <span class="text-muted">{{ trans('review::front.card.own_review') }} · {{ trans_choice('review::front.card.helpful_count', (int) $review->helpful_count, ['count' => (int) $review->helpful_count]) }}</span>
        @elseif ($customerId !== null)
            <form method="POST" action="{{ frontRoute('review.vote', ['id' => $review->id]) }}" x-on:submit.prevent="vote($el.action)">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-chip border px-2.5 py-1 font-medium transition" data-vote
                    x-bind:class="voted ? 'border-accent bg-accent-soft text-accent' : 'border-line text-ink-soft hover:border-accent'" x-bind:aria-pressed="voted ? 'true' : 'false'" x-bind:disabled="busy">
                    <x-theme::icon name="thumbs-up" size="size-3.5" />
                    <span x-text="count > 0 ? @js($helpfulLabel) + ' (' + count + ')' : @js($helpfulLabel)">{{ trans_choice('review::front.card.helpful_count', (int) $review->helpful_count, ['count' => (int) $review->helpful_count]) }}</span>
                </button>
            </form>
            <button type="button" class="inline-flex items-center gap-1 text-muted hover:text-ink" x-on:click="reporting = ! reporting" x-show="! reported" data-report-toggle>
                <x-theme::icon name="flag" size="size-3.5" /> {{ trans('review::front.card.report') }}
            </button>
        @else
            <button type="button" class="inline-flex items-center gap-1.5 rounded-chip border border-line px-2.5 py-1 font-medium text-ink-soft hover:border-accent"
                x-on:click="$dispatch('open-drawer', 'customer-login')" title="{{ trans('review::front.card.sign_in_to_vote') }}">
                <x-theme::icon name="thumbs-up" size="size-3.5" />
                {{ trans_choice('review::front.card.helpful_count', (int) $review->helpful_count, ['count' => (int) $review->helpful_count]) }}
            </button>
        @endif
    </div>

    @if ($customerId !== null && ! $isOwn)
        <form method="POST" action="{{ frontRoute('review.report', ['id' => $review->id]) }}" x-show="reporting" x-cloak x-on:submit.prevent="report($el)"
            class="mt-3 space-y-3 rounded-card border border-line bg-surface-alt p-3" data-report-form>
            @csrf
            <p class="text-sm font-semibold text-ink">{{ trans('review::front.report.title') }}</p>
            <x-theme::select name="reason" :label="trans('review::front.report.reason')" :options="$reportReasons" required id="report-reason-{{ $review->id }}" />
            <x-theme::input name="comment" :label="trans('review::front.report.comment')" maxlength="255" id="report-comment-{{ $review->id }}" :with-old="false" />
            <div class="flex gap-2">
                <x-theme::button size="sm" variant="secondary" loading="busy">{{ trans('review::front.report.submit') }}</x-theme::button>
                <x-theme::button type="button" size="sm" variant="ghost" x-on:click="reporting = false">{{ trans('review::front.report.cancel') }}</x-theme::button>
            </div>
        </form>
    @endif
</article>
