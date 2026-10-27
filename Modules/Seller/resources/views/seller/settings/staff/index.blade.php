@extends('theme::layouts.seller.master')

@section('title', trans('seller::panel.staff.title'))

@section('page_actions')
    <x-theme::button size="sm" :href="frontRoute('seller.staff.create')">
        <x-theme::icon name="plus" size="size-4" /> {{ trans('seller::panel.staff.add') }}
    </x-theme::button>
@endsection

@section('content')
    <p class="mb-4 text-sm text-muted">{{ trans('seller::panel.staff.intro') }}</p>

    <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                <tr>
                    <th scope="col" class="px-4 py-3">{{ trans('seller::panel.staff.name') }}</th>
                    <th scope="col" class="px-4 py-3">{{ trans('seller::panel.staff.role') }}</th>
                    <th scope="col" class="px-4 py-3">{{ trans('seller::panel.staff.status') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ trans('seller::panel.common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($users as $staffUser)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium text-ink">{{ $staffUser->name }}</span>
                            @if ($staffUser->id === $currentUserId)
                                <x-theme::chip class="ml-1">{{ trans('seller::panel.staff.you') }}</x-theme::chip>
                            @endif
                            <span class="block text-muted">{{ $staffUser->email }} · {{ \Modules\Seller\Support\Phone::display($staffUser->phone) }}</span>
                        </td>
                        <td class="px-4 py-3 text-ink-soft">{{ trans('seller::seller.roles.'.$staffUser->role) }}</td>
                        <td class="px-4 py-3">
                            <x-theme::chip :variant="$staffUser->isEnabled() ? 'success' : 'neutral'">
                                {{ $staffUser->isEnabled() ? trans('seller::panel.staff.enabled') : trans('seller::panel.staff.disabled') }}
                            </x-theme::chip>
                        </td>
                        <td class="px-4 py-3">
                            @unless ($staffUser->isOwner())
                                <div class="flex justify-end gap-2">
                                    <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.staff.edit', [$staffUser->id])">{{ trans('seller::panel.common.edit') }}</x-theme::button>
                                    <form method="POST" action="{{ frontRoute('seller.staff.destroy', [$staffUser->id]) }}"
                                        x-data x-on:submit="if (! confirm(@js(trans('seller::panel.staff.delete_confirm')))) $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <x-theme::button variant="ghost" size="sm" class="text-danger">{{ trans('seller::panel.staff.delete') }}</x-theme::button>
                                    </form>
                                </div>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
