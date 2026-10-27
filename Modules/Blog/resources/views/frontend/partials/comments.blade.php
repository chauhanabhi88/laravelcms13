@php $customer = auth('customer')->user(); @endphp
<section id="comments" class="container-page mt-16 max-w-3xl scroll-mt-24" aria-labelledby="comments-heading">
    <h2 id="comments-heading" class="flex items-center gap-2 text-xl font-semibold tracking-tight text-ink">
        {{ trans('blog::blog_front.titles.comments') }}
        <span class="rounded-full bg-surface-alt px-2 py-0.5 text-sm font-medium text-muted">{{ $comments->count() }}</span>
    </h2>

    @if ($comments->isEmpty())
        <x-theme::empty-state class="mt-6 py-10!" icon="chat" :title="trans('blog::blog_front.messages.no_comments')" :text="trans('blog::blog_front.messages.no_comments_text')" />
    @else
        <ol class="mt-6 space-y-4">
            @foreach ($comments as $comment)
                @php $name = trim(($comment->customer?->first_name ?? '').' '.($comment->customer?->last_name ?? '')) ?: trans('blog::blog_front.labels.anonymous'); @endphp
                <li class="rounded-2xl border border-line bg-surface p-5 shadow-soft">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-full bg-surface-alt text-sm font-semibold text-ink-soft">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($name, 0, 1)) }}</span>
                        <div class="text-sm">
                            <p class="font-medium text-ink">{{ $name }}</p>
                            <time class="text-muted" datetime="{{ $comment->created_at?->toIso8601String() }}">{{ $comment->created_at?->diffForHumans() }}</time>
                        </div>
                    </div>
                    @if ($comment->subject)
                        <p class="mt-4 font-medium text-ink">{{ $comment->subject }}</p>
                    @endif
                    <p class="mt-1.5 text-sm leading-6 whitespace-pre-line text-ink-soft">{{ $comment->comment }}</p>
                </li>
            @endforeach
        </ol>
    @endif

    <div class="mt-10">
        @if ($customer)
            <x-theme::card padding="p-6">
                <h3 class="text-base font-semibold text-ink">{{ trans('blog::blog_front.titles.leave_comment') }}</h3>
                <form method="POST" action="{{ frontRoute('blog.comments.store', [$post->slug]) }}" class="mt-5 space-y-4" novalidate
                    x-data="ajaxForm()" x-on:submit.prevent="submit">
                    @csrf
                    <x-theme::input name="subject" :label="trans('blog::blog_front.labels.subject')" required ajax maxlength="150" />
                    <x-theme::textarea name="comment" :label="trans('blog::blog_front.labels.comment')" required ajax rows="4" maxlength="2000" />
                    <div class="flex justify-end">
                        <x-theme::button loading="loading">{{ trans('blog::blog_front.buttons.submit_comment') }}</x-theme::button>
                    </div>
                </form>
            </x-theme::card>
        @else
            <div class="flex flex-col items-start gap-4 rounded-2xl border border-line bg-surface-alt p-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-ink-soft">{{ trans('blog::blog_front.messages.login_to_comment') }}</p>
                <x-theme::button :href="frontRoute('customer.login').'?redirect='.urlencode(request()->fullUrl().'#comments')">{{ trans('blog::blog_front.buttons.login_to_comment') }}</x-theme::button>
            </div>
        @endif
    </div>
</section>
