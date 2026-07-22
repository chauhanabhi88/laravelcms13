@php
$customerAddress = $customer->address;
@endphp

@if(isset($customerAddress) && !empty($customerAddress) && count($customerAddress) > 0)
<div class="row">
  @foreach($customerAddress as $address)
  <div class="col-md-6">
    <div class="accordion md-accordion" id="accordionEx" role="tablist" aria-multiselectable="true">
      <div class="card address-card card-default">
        <div class="card-header collapsed" data-toggle="collapse" data-target="#address{{ $address->id }}" aria-expanded="false">
          <a class="btn-tool">
            <h5 class="ttl-filter">{{$address->tag}}
              @if($address->is_default_address == \config('customer.is_default_address.yes'))
              <i class="default-mark text-sm">{{trans("customer::customer.labels.default")}}</i>
              @endif
            </h5>
          </a>
          <div class="card-tools">
            <button type="button" class="btn btn-tool filterAccordian collapsed" data-toggle="collapse" data-target="#address{{ $address->id }}" aria-expanded="false"></button>
          </div>
        </div>
        <div class="card-body collapse" id="address{{$address->id}}">
          <dl>
            <dt>{{trans("customer::customer.labels.street_name")}}</dt>
            <dd>{{$address->street_name}}</dd>
            <dt>{{trans("customer::customer.labels.building")}}</dt>
            <dd>{{$address->building}}</dd>
            <dt>{{trans("customer::customer.labels.unit_no")}}</dt>
            <dd>{{$address->unit_no}}</dd>
            <dt>{{trans("customer::customer.labels.postal_code")}}</dt>
            <dd>{{$address->postal_code}}</dd>
          </dl>
          <button class="edit_address btn btn-primary btn-fw" data-id="{{$address->id}}" onclick="editAddress({{$address->id}})" type="button">{{trans("customer::customer.buttons.edit")}}</button>
          @if($address->is_default_address !== config('customer.is_default_address.yes'))
          <button type="button" class="btn btn-danger btn-fw" data-toggle="modal" data-target="#modal-delete-confirmation" data-message="{{ trans('core::core.modal.mass-delete-confirmation-message') }}" data-action-target="{{ route('admin.address.delete', updateUrlParams([$address->id])) }}">{{trans("customer::customer.buttons.remove")}}</button>
          @endif
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>
@else
<div class="card address-card">
  <div class="card-body">
    {{trans('customer::customer.messages.no_address')}}
  </div>
</div>
@endif