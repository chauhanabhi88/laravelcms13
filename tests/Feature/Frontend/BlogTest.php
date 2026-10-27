<?php

use Modules\Blog\Models\BlogCategory;
use Modules\Blog\Models\BlogPost;
use Modules\Blog\Models\BlogPostComment;
use Modules\Customer\Models\Customer;

test('the blog lists only published posts', function () {
    $published = BlogPost::factory()->create(['title' => 'Visible Published Post']);
    BlogPost::factory()->disabled()->create(['title' => 'Hidden Disabled Post']);
    BlogPost::factory()->scheduled()->create(['title' => 'Hidden Future Post']);

    $this->get(frontRoute('blog.index'))
        ->assertSuccessful()
        ->assertSee($published->title)
        ->assertDontSee('Hidden Disabled Post')
        ->assertDontSee('Hidden Future Post');
});

test('the blog can be searched by title', function () {
    BlogPost::factory()->create(['title' => 'Zebra crossing guide']);
    BlogPost::factory()->create(['title' => 'Unrelated walrus story']);

    $this->get(frontRoute('blog.index', [['q' => 'Zebra']]))
        ->assertSuccessful()
        ->assertSee('Zebra crossing guide')
        ->assertDontSee('Unrelated walrus story');
});

test('the category page lists only posts in that category', function () {
    $category = BlogCategory::factory()->create();
    $inCategory = BlogPost::factory()->create(['title' => 'Post Inside Category']);
    $inCategory->categories()->attach($category);
    BlogPost::factory()->create(['title' => 'Post Outside Category']);

    $this->get(frontRoute('blog.category', [$category->slug]))
        ->assertSuccessful()
        ->assertSee('Post Inside Category')
        ->assertDontSee('Post Outside Category');
});

test('unknown and disabled categories are not found', function () {
    $disabled = BlogCategory::factory()->disabled()->create();

    $this->get(frontRoute('blog.category', ['does-not-exist']))->assertNotFound();
    $this->get(frontRoute('blog.category', [$disabled->slug]))->assertNotFound();
});

test('load more returns rendered cards and the next page url as json', function () {
    config(['blog.front.per_page' => 2]);
    BlogPost::factory()->count(3)->create();

    $this->getJson(frontRoute('blog.index'))
        ->assertSuccessful()
        ->assertJsonStructure(['html', 'next_page_url'])
        ->assertJsonPath('next_page_url', fn (?string $url) => str_contains((string) $url, 'page=2'));
});

test('a published post can be viewed', function () {
    $post = BlogPost::factory()->create(['title' => 'A Readable Post', 'content' => '<p>Body of the readable post</p>']);

    $this->get(frontRoute('blog.show', [$post->slug]))
        ->assertSuccessful()
        ->assertSee('A Readable Post')
        ->assertSee('Body of the readable post', false);
});

test('disabled, scheduled and unknown posts are not found', function () {
    $disabled = BlogPost::factory()->disabled()->create();
    $scheduled = BlogPost::factory()->scheduled()->create();

    $this->get(frontRoute('blog.show', [$disabled->slug]))->assertNotFound();
    $this->get(frontRoute('blog.show', [$scheduled->slug]))->assertNotFound();
    $this->get(frontRoute('blog.show', ['nope-not-here']))->assertNotFound();
});

test('only approved comments are shown on a post', function () {
    $post = BlogPost::factory()->create();
    BlogPostComment::factory()->approved()->for($post, 'post')->create(['comment' => 'An approved remark']);
    BlogPostComment::factory()->for($post, 'post')->create(['comment' => 'A pending remark']);

    $this->get(frontRoute('blog.show', [$post->slug]))
        ->assertSee('An approved remark')
        ->assertDontSee('A pending remark');
});

test('guests cannot comment and are sent to the customer login', function () {
    $post = BlogPost::factory()->create();

    $this->post(frontRoute('blog.comments.store', [$post->slug]), ['subject' => 'Hi', 'comment' => 'Hello there'])
        ->assertRedirect(frontRoute('customer.login'));

    expect(BlogPostComment::where('post_id', $post->id)->exists())->toBeFalse();
});

test('a logged in customer comment is stored as pending', function () {
    $post = BlogPost::factory()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->postJson(frontRoute('blog.comments.store', [$post->slug]), ['subject' => 'Great read', 'comment' => 'Thanks for writing this.'])
        ->assertCreated()
        ->assertJsonPath('message', trans('blog::blog_front.messages.comment_pending'));

    $comment = BlogPostComment::where('post_id', $post->id)->sole();

    expect($comment->customer_id)->toBe($customer->id)
        ->and((int) $comment->status)->toBe((int) config('blog.comment_status.pending'));
});

test('comments are validated', function (array $payload, string $field) {
    $post = BlogPost::factory()->create();

    $this->actingAs(Customer::factory()->create(), 'customer')
        ->postJson(frontRoute('blog.comments.store', [$post->slug]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing subject' => [['comment' => 'A valid comment body'], 'subject'],
    'missing comment' => [['subject' => 'Subject'], 'comment'],
    'comment too short' => [['subject' => 'Subject', 'comment' => 'abc'], 'comment'],
    'subject too long' => [['subject' => str_repeat('a', 151), 'comment' => 'A valid comment body'], 'subject'],
]);
