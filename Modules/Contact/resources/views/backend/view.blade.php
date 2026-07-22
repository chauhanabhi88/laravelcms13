@extends('theme::layouts.backend.master')

@section('title')
{{ trans("contact::contact.titles.view_contact") }} {{wordWrapper($contact->name, true)}}
@endsection

@section('content-header')
<div class="card">
  <div class="card-body">
    <div class="page-title-header row d-none d-sm-flex">
      <div class="page-header col-sm-12 d-flex pb-4 pt-2">
        <div class="col-sm-6">
          <h4 class="page-title">{{ trans("contact::contact.titles.view_contact") }} {{wordWrapper($contact->name, true)}}</h4>
        </div>
        <div class="col-sm-6 btn-right">
          <div class="float-right">
            <button type="button" class="btn btn-info" onclick="setLocation('{{ route('admin.contact.index',updateUrlParams()) }}')">Back</button>
          </div>
        </div>
      </div>
    </div>
    @stop

    @section('content')
    <div class="row">
      <div class="col-12">
        <div id="collection">
       
          {{ normalText("contact[name]","contact::contact.labels.name", $errors,$contact->name,['readonly'=>'readonly' ])}}

          {{ normalInputOfType("email","contact[email]",  "contact::contact.labels.email",  $errors,$contact->email, ['readonly'=>'readonly' ])}}

          {{ normalText("contact[contact_number]", "contact::contact.labels.contact_number", $errors,$contact->contact_number,['readonly'=>'readonly' ])}}

          {{ normalTextarea("contact[content]","contact::contact.labels.content", $errors,$contact->content,['readonly'=>'readonly' ])}}
        </div>
      </div>
    </div>
  </div>
</div>


@stop

@push('js-stack')

@endpush