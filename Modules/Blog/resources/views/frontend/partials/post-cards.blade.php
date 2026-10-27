{{-- Grid items only; also returned as "html" for the load-more fetch. --}}
@foreach ($posts as $post)
    @include('blog::frontend.partials.post-card', ['post' => $post, 'lead' => ($leadFirst ?? false) && $loop->first])
@endforeach
