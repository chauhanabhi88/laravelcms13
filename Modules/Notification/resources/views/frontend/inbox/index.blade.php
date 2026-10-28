@extends('customer::frontend.account.layout')

@section('title', trans('notification::front.inbox.title'))
@section('heading', trans('notification::front.inbox.title'))
@section('subheading', trans('notification::front.inbox.subtitle'))

@section('account')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">{{ trans_choice('notification::front.inbox.unread', $unreadCount, ['count' => $unreadCount]) }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <x-theme::button variant="ghost" size="sm" :href="frontRoute('customer.notification_preferences.edit')">
                <x-theme::icon name="settings" size="size-4" /> {{ trans('notification::front.inbox.settings') }}
            </x-theme::button>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ $readAllUrl }}">
                    @csrf
                    <x-theme::button variant="secondary" size="sm">{{ trans('notification::front.inbox.mark_all_read') }}</x-theme::button>
                </form>
            @endif
        </div>
    </div>

    @if ($items === [])
        <x-theme::empty-state icon="bell" :title="trans('notification::front.inbox.empty_title')" :text="trans('notification::front.inbox.empty_text')" />
    @else
        @include('notification::frontend.inbox.partials.list', ['readRoute' => 'customer.notifications.read'])
    @endif
@endsection
