@php 
    $page_title = (isset($page->meta_title) && !empty($page->meta_title)) ? $page->meta_title : $page->title;
@endphp

@extends('theme::layouts.frontend.master')

@section('title')
     {{ $page_title }}
@endsection

@section('meta')
    {{ $page->meta }}
    <meta name="{{ $page->meta_title }}" content="{{ $page->meta_description }}">
@endsection

@section('content-header')
@endsection
@section('content')
    <!-- Main content -->
    <main>
        <div class="content-wrapper {{ $page->slug }}">
            @if ($banner)
                <div class="banner-section">
                    <div class="box-image">
                        <!--<img src="skin/images/about-us/banner-about.png" alt="about-us">-->
                        @if( $banner['image'] != null && file_exists(public_path().'/storage/Banner/thumbnails/'.$banner['image']))
                            <img src="{{ URL::to('/') }}/storage/Banner/{{$banner['image']}}">
                        @else
                            <img src="{{ asset('modules/theme/frontend/images/placeholder/ph-for-product-362_200.png') }}">
                        @endif
                        <div class="box-text">
                            <h1>{{ $page->title }}</h1>
                        </div>
                    </div>
                </div>
            @endif
            <div class="container-wrapper section-wrapper">
                <div id="collection">
                    {!! $page->getPageBody() !!}
                 </div>
            </div>
        </div>
    </main>
    <!-- /.container-fluid -->
@stop
