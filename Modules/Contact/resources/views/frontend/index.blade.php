@extends('theme::layouts.frontend.master')

@section('title')
    {!! trans('contact::contact_front.titles.contact_us') !!}
@endsection

@section('content')
    <livewire:contact::frontend.contact-form />
@stop