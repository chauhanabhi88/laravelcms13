<?php

use Modules\Blog\Models\BlogPost;
use Modules\Pages\Models\Pages;

test('the home page renders with featured posts', function () {
    $post = BlogPost::factory()->featured()->create(['title' => 'Featured On Home']);

    $this->get(frontRoute('homepage'))
        ->assertSuccessful()
        ->assertSee('Featured On Home');
});

test('an enabled cms page renders', function () {
    $page = Pages::factory()->create(['title' => 'Our Story', 'body' => '<p>Founded long ago</p>']);

    $this->get(frontRoute('page', [$page->slug]))
        ->assertSuccessful()
        ->assertSee('Our Story')
        ->assertSee('Founded long ago', false);
});

test('disabled and unknown cms pages are not found', function () {
    $disabled = Pages::factory()->disabled()->create();

    $this->get(frontRoute('page', [$disabled->slug]))->assertNotFound();
    $this->get(frontRoute('page', ['no-such-page-here']))->assertNotFound();
});

test('the blog route is not swallowed by the cms page catch-all', function () {
    $this->get(frontRoute('blog.index'))
        ->assertSuccessful()
        ->assertViewIs('blog::frontend.index');
});
