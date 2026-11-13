{{--
    The storefront logo (admin > Settings > Theme > Logo URL), or a text wordmark of the site name
    while no logo image exists. Extra attributes (class, x-show, loading…) land on the <img> or the
    wordmark. The class should carry the height (e.g. h-8): the wordmark scales its text to it.
--}}
@props(['url' => \Modules\Theme\Support\ThemeSettings::logoUrl(), 'name' => config('app.name')])

@if ($url)
    <img src="{{ $url }}" alt="{{ $name }}" {{ $attributes->class('w-auto') }}>
@else
    <span {{ $attributes->class('inline-flex items-center text-xl leading-none font-extrabold tracking-tight whitespace-nowrap text-accent') }}>{{ $name }}</span>
@endif
