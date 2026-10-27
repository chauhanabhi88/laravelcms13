<?php

use Modules\Blog\Models\BlogCategory;
use Modules\Blog\Models\BlogPost;
use Modules\Blog\Models\BlogPostComment;
use Modules\Pages\Models\Pages;

test('posts are listed without a token', function () {
    $post = BlogPost::factory()->create(['title' => 'Api Listed Post']);
    BlogPost::factory()->disabled()->create(['title' => 'Api Hidden Post']);

    $response = $this->getJson('/api/v1/public/blog/posts?per_page=50')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [['id', 'slug', 'title', 'excerpt', 'image_url', 'author', 'published_at', 'is_featured', 'categories', 'url']],
            'links',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);

    $titles = collect($response->json('data'))->pluck('title');

    expect($titles)->toContain($post->title)->not->toContain('Api Hidden Post');
});

test('per_page is clamped', function () {
    $this->getJson('/api/v1/public/blog/posts?per_page=1000')->assertJsonPath('meta.per_page', 50);
    $this->getJson('/api/v1/public/blog/posts?per_page=0')->assertJsonPath('meta.per_page', 1);
});

test('a post can be fetched with its content', function () {
    $post = BlogPost::factory()->create(['content' => '<p>Api body</p>']);

    $this->getJson("/api/v1/public/blog/posts/{$post->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.slug', $post->slug)
        ->assertJsonPath('data.content', '<p>Api body</p>')
        ->assertJsonStructure(['data' => ['meta' => ['title', 'description', 'keywords']]]);
});

test('missing posts return the json error envelope', function () {
    $disabled = BlogPost::factory()->disabled()->create();

    $this->getJson("/api/v1/public/blog/posts/{$disabled->slug}")
        ->assertNotFound()
        ->assertExactJson(['success' => false, 'message' => trans('core::core.messages.route_not_found')]);
});

test('only approved comments are exposed, without customer emails', function () {
    $post = BlogPost::factory()->create();
    $approved = BlogPostComment::factory()->approved()->for($post, 'post')->create();
    BlogPostComment::factory()->for($post, 'post')->create();

    $response = $this->getJson("/api/v1/public/blog/posts/{$post->slug}/comments")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $approved->id);

    expect($response->getContent())->not->toContain($approved->customer->email);
});

test('categories include their published post count', function () {
    $category = BlogCategory::factory()->create();
    BlogPost::factory()->count(2)->create()->each(fn (BlogPost $post) => $post->categories()->attach($category));
    BlogPost::factory()->disabled()->create()->categories()->attach($category);

    $item = collect($this->getJson('/api/v1/public/blog/categories')->assertSuccessful()->json('data'))
        ->firstWhere('slug', $category->slug);

    expect($item['post_count'])->toBe(2);
});

test('cms pages are served and disabled ones are hidden', function () {
    $page = Pages::factory()->create(['title' => 'Api Page']);
    $disabled = Pages::factory()->disabled()->create();

    $this->getJson("/api/v1/public/pages/{$page->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Api Page');

    $this->getJson("/api/v1/public/pages/{$disabled->slug}")
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

test('unknown banner groups return an empty list', function () {
    $this->getJson('/api/v1/public/banners/no-such-group')
        ->assertSuccessful()
        ->assertExactJson(['success' => true, 'data' => []]);
});
