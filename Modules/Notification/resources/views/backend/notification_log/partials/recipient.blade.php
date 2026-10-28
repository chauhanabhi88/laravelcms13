{{-- Recipient of a log row: "Customer: Meera K (#12)". $log->notifiable is eager loaded. --}}
@php
    $recipientType = match ($log->notifiable_type) {
        \Modules\Customer\Models\Customer::class => trans('notification::notification.audiences.customer'),
        \Modules\Seller\Models\SellerUser::class => trans('notification::notification.audiences.seller'),
        \Modules\User\Models\User::class => trans('notification::notification.audiences.admin'),
        default => null,
    };
    $recipient = $log->notifiable;
    $recipientName = $recipient === null ? null : app(\Modules\Notification\Support\RecipientResolver::class)->name($recipient);
@endphp
@if ($log->notifiable_id === null)
    <span class="text-muted">{{ trans('notification::notification_log.labels.test') }}</span>
@elseif ($recipient === null)
    <span class="text-muted">{{ trans('notification::notification_log.labels.deleted_recipient', ['id' => $log->notifiable_id]) }}</span>
@else
    {{ $recipientType ? $recipientType.': ' : '' }}{{ $recipientName ?: '-' }} (#{{ $log->notifiable_id }})
@endif
