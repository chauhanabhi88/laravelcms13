@extends('theme::layouts.seller.master')

@section('title', trans('notification::front.inbox.title'))

@section('page_actions')
    <div class="flex flex-wrap items-center gap-2">
        @if (Route::has('seller.notification_preferences.edit'))
            <x-theme::button variant="ghost" size="sm" :href="frontRoute('seller.notification_preferences.edit')">
                <x-theme::icon name="settings" size="size-4" /> {{ trans('notification::front.inbox.settings') }}
            </x-theme::button>
        @endif
        @if ($unreadCount > 0)
            <form method="POST" action="{{ $readAllUrl }}">
                @csrf
                <x-theme::button variant="secondary" size="sm">{{ trans('notification::front.inbox.mark_all_read') }}</x-theme::button>
            </form>
        @endif
    </div>
@endsection

@section('content')
    <p class="mb-4 text-sm text-muted">
        {{ trans('notification::front.inbox.seller_subtitle') }}
        {{ trans_choice('notification::front.inbox.unread', $unreadCount, ['count' => $unreadCount]) }}
    </p>

    @if ($items === [])
        <x-theme::empty-state icon="bell" :title="trans('notification::front.inbox.empty_title')" :text="trans('notification::front.inbox.empty_text')" />
    @else
        @include('notification::frontend.inbox.partials.list', ['readRoute' => 'seller.notifications.read'])
    @endif
@endsection
