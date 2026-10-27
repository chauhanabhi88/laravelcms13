@props(['title' => null, 'description' => null, 'keywords' => null, 'image' => null, 'type' => 'website', 'canonical' => null])
{{-- Render inside @section('meta'). --}}
@php
    $description = $description ? \Illuminate\Support\Str::limit(trim(strip_tags($description)), 160) : null;
    $canonical ??= url()->current();
@endphp
@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
@if ($keywords)
    <meta name="keywords" content="{{ $keywords }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:url" content="{{ $canonical }}">
@if ($title)
    <meta property="og:title" content="{{ $title }}">
@endif
@if ($description)
    <meta property="og:description" content="{{ $description }}">
@endif
@if ($image)
    <meta property="og:image" content="{{ $image }}">
    <meta name="twitter:card" content="summary_large_image">
@endif
