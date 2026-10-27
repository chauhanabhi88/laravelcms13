@extends('theme::layouts.backend.master')

@php
    use Modules\Seller\Enums\SellerStatus;
    use Modules\Seller\Support\Phone;

    $isApplication = $context === 'application';
    $none = trans('seller::seller.labels.none');
    $bankBadge = fn ($account) => match ($account?->verification_status) {
        'verified' => 'success',
        'failed' => 'danger',
        'manual_review' => 'warning',
        default => 'info',
    };
    $date = fn ($value) => $value ? getFormatedDate($value, getGridDateFormat()).' '.$value->format('H:i') : $none;
@endphp

@section('title')
{{ trans($isApplication ? 'seller::seller.titles.application' : 'seller::seller.titles.seller') }}: {{ $seller->displayName() }}
@endsection

@section('content-header')
<div class="page-title-header row d-none d-sm-flex">
    <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-7">
            <h4 class="page-title">
                {{ $seller->displayName() }}
                <small class="text-muted">{{ $seller->code }}</small>
                <span class="badge badge-{{ $seller->status->badge() }} ml-2">{{ $seller->status->label() }}</span>
            </h4>
        </div>
        <div class="col-sm-5 btn-right">
            <div class="float-right">
                <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ $isApplication ? route('admin.seller_application.index', updateUrlParams()) : route('admin.seller.index', updateUrlParams()) }}')">{{ trans('seller::seller.buttons.back') }}</button>
                @can('admin.seller.edit')
                    <button class="btn btn-primary btn-fw" onclick="setLocation('{{ route('admin.seller.edit', updateUrlParams([$seller->id])) }}')">{{ trans('seller::seller.buttons.edit') }}</button>
                @endcan
            </div>
        </div>
    </div>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-lg-8">
        {{-- Business & tax --}}
        <div class="card card-info card-outline mb-4">
            <div class="card-header"><strong>{{ trans('seller::seller.titles.business') }} &amp; {{ trans('seller::seller.titles.tax') }}</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    @foreach ([
                        'store_name' => $seller->store_name,
                        'legal_name' => $seller->legal_name,
                        'business_type' => $seller->business_type ? trans('seller::seller.business_types.'.$seller->business_type) : null,
                        'pan' => $seller->maskedPan(),
                        'gstin' => $seller->gstin,
                        'gst_status' => $seller->gst_status ? trans('seller::seller.gst_statuses.'.$seller->gst_status) : null,
                        'gst_legal_name' => $seller->gst_legal_name,
                        'enrolment_id' => $seller->enrolment_id,
                        'state' => $seller->state?->name,
                        'email' => $seller->email,
                        'phone' => Phone::display($seller->phone),
                        'submitted_at' => $date($seller->submitted_at),
                        'verified_at' => $seller->verified_at ? $date($seller->verified_at).($seller->verifier ? ' · '.$seller->verifier->name : '') : null,
                        'payout_hold' => (int) $seller->payout_hold === (int) config('core.yes') ? trans('seller::seller.labels.yes') : trans('seller::seller.labels.no'),
                    ] as $label => $value)
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.'.$label) }}</dt>
                        <dd class="col-sm-8">{{ $value ?: $none }}</dd>
                    @endforeach
                    <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.agreement') }}</dt>
                    <dd class="col-sm-8">
                        @if ($seller->agreement_accepted_at)
                            {{ trans('seller::seller.labels.agreement_accepted', ['version' => $seller->agreement_version, 'date' => $date($seller->agreement_accepted_at), 'ip' => $seller->agreement_accepted_ip]) }}
                        @else
                            {{ trans('seller::seller.labels.agreement_pending') }}
                        @endif
                    </dd>
                    @if ($seller->status_reason)
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.status_reason') }}</dt>
                        <dd class="col-sm-8">{{ $seller->status_reason }}</dd>
                    @endif
                    @if ($seller->suspended_reason)
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.suspended_reason') }}</dt>
                        <dd class="col-sm-8">{{ $seller->suspended_reason }}</dd>
                    @endif
                    @if ($seller->store_slug && $seller->status === SellerStatus::Active)
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.store_slug') }}</dt>
                        <dd class="col-sm-8"><a href="{{ frontRoute('storefront.store.show', [$seller->store_slug]) }}" target="_blank" rel="noopener">{{ trans('seller::seller.labels.store_link') }}</a></dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Bank --}}
        <div class="card card-info card-outline mb-4">
            <div class="card-header"><strong>{{ trans('seller::seller.titles.bank') }}</strong></div>
            <div class="card-body">
                @if ($primaryBank)
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.bank_status') }}</dt>
                        <dd class="col-sm-8">
                            <span class="badge badge-{{ $bankBadge($primaryBank) }}">{{ $primaryBank->statusLabel() }}</span>
                            @if ($primaryBank->verification_method)
                                <small class="text-muted ml-1">{{ trans('seller::seller.bank_methods.'.$primaryBank->verification_method) }}</small>
                            @endif
                        </dd>
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.holder_name') }}</dt>
                        <dd class="col-sm-8">{{ $primaryBank->account_holder_name }}</dd>
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.penny_drop_name') }}</dt>
                        <dd class="col-sm-8">{{ $primaryBank->verified_name ?: $none }}</dd>
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.legal_name') }}</dt>
                        <dd class="col-sm-8">{{ $seller->legal_name ?: $none }}</dd>
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.name_match') }}</dt>
                        <dd class="col-sm-8">{{ $primaryBank->name_match_score !== null ? trans('seller::seller.labels.name_match_value', ['score' => $primaryBank->name_match_score]) : $none }}</dd>
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.account_number') }}</dt>
                        <dd class="col-sm-8">{{ $primaryBank->maskedNumber() }}</dd>
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.ifsc') }}</dt>
                        <dd class="col-sm-8">{{ $primaryBank->ifsc }} · {{ $primaryBank->bank_name }} {{ $primaryBank->branch }}</dd>
                        <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.account_type') }}</dt>
                        <dd class="col-sm-8">{{ trans('seller::seller.account_types.'.$primaryBank->account_type) }}</dd>
                        @if ($primaryBank->verification_note)
                            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.verification_note') }}</dt>
                            <dd class="col-sm-8">{{ $primaryBank->verification_note }}</dd>
                        @endif
                        @if ($primaryBank->chequeDocument)
                            <dt class="col-sm-4 text-muted font-weight-normal">{{ trans('seller::seller.labels.cheque') }}</dt>
                            <dd class="col-sm-8">
                                @can('admin.seller.kyc_view')
                                    <a href="{{ route('admin.seller.kyc_view', updateUrlParams([$seller->id, $primaryBank->chequeDocument->id])) }}" target="_blank" rel="noopener">{{ $primaryBank->chequeDocument->original_name }}</a>
                                @else
                                    {{ $primaryBank->chequeDocument->original_name }}
                                @endcan
                            </dd>
                        @endif
                    </dl>
                    @if (! $primaryBank->isVerified())
                        @can('admin.seller.bank_verify_manual')
                            <form method="POST" action="{{ route('admin.seller.bank_verify_manual', updateUrlParams([$seller->id, $primaryBank->id])) }}" class="mt-3 form-inline">
                                @csrf
                                <input type="text" name="reason" maxlength="500" class="form-control form-control-sm mr-2 flex-grow-1" placeholder="{{ trans('seller::seller.labels.note_placeholder') }}">
                                <button type="submit" class="btn btn-success btn-sm">{{ trans('seller::seller.buttons.verify_bank') }}</button>
                            </form>
                        @endcan
                    @endif
                @else
                    <p class="mb-0 text-muted">{{ trans('seller::seller.messages.no_bank') }}</p>
                @endif

                @php
                    $previousAccounts = $seller->bankAccounts->reject(fn ($account) => $primaryBank && $account->is($primaryBank));
                @endphp
                @if ($previousAccounts->isNotEmpty())
                    <h6 class="mt-4">{{ trans('seller::seller.titles.bank_history') }}</h6>
                    <ul class="mb-0 small">
                        @foreach ($previousAccounts as $account)
                            <li>{{ $account->ifsc }} · {{ $account->maskedNumber() }} · {{ $account->statusLabel() }} · {{ $date($account->created_at) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Documents --}}
        <div class="card card-info card-outline mb-4">
            <div class="card-header"><strong>{{ trans('seller::seller.titles.documents') }}</strong></div>
            <div class="card-body">
                @forelse ($seller->kycDocuments as $document)
                    <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                        <div>
                            <strong>{{ $document->typeLabel() }}</strong>
                            <div class="small text-muted">{{ $document->original_name }} · {{ number_format($document->size / 1024, 0) }} KB · {{ $date($document->created_at) }}</div>
                        </div>
                        @can('admin.seller.kyc_view')
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.seller.kyc_view', updateUrlParams([$seller->id, $document->id])) }}" target="_blank" rel="noopener">{{ trans('seller::seller.labels.view') }}</a>
                        @endcan
                    </div>
                @empty
                    <p class="mb-0 text-muted">{{ trans('seller::seller.messages.no_documents') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Warehouses --}}
        <div class="card card-info card-outline mb-4">
            <div class="card-header"><strong>{{ trans('seller::seller.titles.warehouses') }}</strong></div>
            <div class="card-body">
                @forelse ($seller->warehouses as $warehouse)
                    <div class="border-bottom py-2">
                        <strong>{{ $warehouse->name }}</strong>
                        @if ($warehouse->isDefault())
                            <span class="badge badge-info ml-1">{{ trans('seller::seller.labels.default') }}</span>
                        @endif
                        <div class="small">{{ $warehouse->fullAddress() }}</div>
                        <div class="small text-muted">{{ $warehouse->contact_name }} · {{ Phone::display($warehouse->contact_phone) }}</div>
                    </div>
                @empty
                    <p class="mb-0 text-muted">{{ trans('seller::seller.messages.no_warehouses') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Decisions --}}
        @php
            $canDecide = $seller->status === SellerStatus::PendingVerification;
        @endphp
        @if ($canDecide || $seller->hasStatus(SellerStatus::Active, SellerStatus::Suspended))
            <div class="card card-info card-outline mb-4">
                <div class="card-header"><strong>{{ trans('seller::seller.titles.decision') }}</strong></div>
                <div class="card-body">
                    @if ($canDecide)
                        @can('admin.seller_application.approve')
                            @include('seller::backend.seller.partials.decision-form', [
                                'route' => 'admin.seller_application.approve', 'label' => trans('seller::seller.buttons.approve'), 'button' => 'success',
                                'templates' => [], 'confirm' => trans('seller::seller.messages.confirm_approve'),
                            ])
                        @endcan
                        @can('admin.seller_application.request_changes')
                            @include('seller::backend.seller.partials.decision-form', [
                                'route' => 'admin.seller_application.request_changes', 'label' => trans('seller::seller.buttons.request_changes'), 'button' => 'warning',
                                'templates' => $reasonTemplates['request_changes'] ?? [], 'required' => true,
                            ])
                        @endcan
                        @can('admin.seller_application.reject')
                            @include('seller::backend.seller.partials.decision-form', [
                                'route' => 'admin.seller_application.reject', 'label' => trans('seller::seller.buttons.reject'), 'button' => 'danger',
                                'templates' => $reasonTemplates['reject'] ?? [], 'required' => true,
                            ])
                        @endcan
                    @elseif ($seller->status === SellerStatus::Active)
                        @can('admin.seller.suspend')
                            @include('seller::backend.seller.partials.decision-form', [
                                'route' => 'admin.seller.suspend', 'label' => trans('seller::seller.buttons.suspend'), 'button' => 'danger',
                                'templates' => $reasonTemplates['suspend'] ?? [], 'required' => true,
                            ])
                        @endcan
                    @else
                        @can('admin.seller.reactivate')
                            @include('seller::backend.seller.partials.decision-form', [
                                'route' => 'admin.seller.reactivate', 'label' => trans('seller::seller.buttons.reactivate'), 'button' => 'success', 'templates' => [],
                            ])
                        @endcan
                    @endif
                </div>
            </div>
        @endif

        {{-- Checklist --}}
        <div class="card card-info card-outline mb-4">
            <div class="card-header"><strong>{{ trans('seller::seller.titles.checklist') }}</strong></div>
            <ul class="list-group list-group-flush">
                @foreach ($completed as $step => $done)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ trans('seller::onboarding.steps.'.$step) }}</span>
                        <span class="badge badge-{{ $done ? 'success' : 'secondary' }}">{{ trans($done ? 'seller::seller.labels.complete' : 'seller::seller.labels.incomplete') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- History --}}
        <div class="card card-info card-outline mb-4">
            <div class="card-header"><strong>{{ trans('seller::seller.titles.history') }}</strong></div>
            <div class="card-body">
                @forelse ($seller->statusHistories->sortByDesc('id') as $history)
                    <div class="border-bottom pb-2 mb-2 small">
                        <div>
                            <strong>{{ $history->to_status ? trans('seller::seller.statuses.'.$history->to_status) : '' }}</strong>
                            @if ($history->from_status)
                                <span class="text-muted">← {{ trans('seller::seller.statuses.'.$history->from_status) }}</span>
                            @endif
                        </div>
                        <div class="text-muted">
                            {{ $date($history->created_at) }} ·
                            {{ trans('seller::seller.actor_types.'.$history->actor_type) }}@if ($history->actor_type === 'admin' && isset($adminNames[$history->actor_id])): {{ $adminNames[$history->actor_id] }}@endif
                        </div>
                        @if ($history->reason)
                            <div class="mt-1">{{ $history->reason }}</div>
                        @endif
                    </div>
                @empty
                    <p class="mb-0 text-muted">{{ trans('seller::seller.messages.no_history') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Logins --}}
        <div class="card card-info card-outline mb-4">
            <div class="card-header"><strong>{{ trans('seller::seller.titles.logins') }}</strong></div>
            <ul class="list-group list-group-flush">
                @foreach ($seller->users as $user)
                    <li class="list-group-item small">
                        <strong>{{ $user->name }}</strong> <span class="badge badge-light">{{ trans('seller::seller.roles.'.$user->role) }}</span>
                        <div class="text-muted">{{ $user->email }} · {{ Phone::display($user->phone) }}</div>
                        <div class="text-muted">{{ trans('seller::seller.labels.last_login') }}: {{ $date($user->last_login_at) }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@stop
