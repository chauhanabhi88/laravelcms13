{{--
    Inbox list shared by the customer and seller pages.
    $items (InboxService::present rows), $notifications (paginator), $readRoute (route name of "read").
--}}
<ul class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-card">
    @foreach ($items as $item)
        <li @class(['flex gap-4 p-4 sm:p-5', 'bg-accent-soft/40' => ! $item['read']])>
            <span @class([
                'flex size-10 shrink-0 items-center justify-center rounded-full',
                'bg-accent-soft text-accent' => ! $item['read'],
                'bg-surface-alt text-muted' => $item['read'],
            ])>
                <x-theme::icon :name="$item['icon']" size="size-5" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                    <p class="text-sm font-semibold text-ink">
                        @if (! $item['read'])
                            <span class="sr-only">{{ trans('theme::frontend.bell.unread') }}:</span>
                        @endif
                        {{ $item['title'] }}
                    </p>
                    <time datetime="{{ $item['created_at'] }}" class="text-xs text-muted">{{ $item['time'] }}</time>
                </div>
                @if ($item['body'] !== '')
                    <p class="mt-1 text-sm text-ink-soft">{{ $item['body'] }}</p>
                @endif
                @if ($item['url'] !== '' || ! $item['read'])
                    <form method="POST" action="{{ frontRoute($readRoute, [$item['id']]) }}" class="mt-2">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-accent hover:underline">
                            {{ $item['url'] !== '' ? trans('notification::front.inbox.open') : trans('notification::front.inbox.mark_read') }}
                        </button>
                    </form>
                @endif
            </div>
        </li>
    @endforeach
</ul>

@if ($notifications->hasPages())
    <div class="mt-8">{{ $notifications->links() }}</div>
@endif
