<?php

use Modules\Contact\Models\Contact;

test('the contact page renders', function () {
    $this->get(frontRoute('contact.index'))->assertSuccessful();
});

test('the old contact url redirects permanently', function () {
    $this->get('/contact/info')->assertStatus(301)->assertRedirect(frontRoute('contact.index'));
});

test('a visitor can send an enquiry', function () {
    $this->post(frontRoute('contact.store'), [
        'name' => 'Jane Visitor',
        'email' => 'jane@example.test',
        'contact_number' => '5551234567',
        'content' => 'Do you ship internationally?',
    ])->assertRedirect(frontRoute('contact.index'))->assertSessionHas('success');

    expect(Contact::where('email', 'jane@example.test')->exists())->toBeTrue();
});

test('enquiries are validated', function (array $payload, string $field) {
    $this->from(frontRoute('contact.index'))
        ->post(frontRoute('contact.store'), $payload + ['name' => 'Jane Visitor', 'email' => 'jane@example.test', 'content' => 'Hello'])
        ->assertSessionHasErrors($field);
})->with([
    'invalid email' => [['email' => 'nope'], 'email'],
    'short name' => [['name' => 'Jo'], 'name'],
    'missing message' => [['content' => ''], 'content'],
    'bad phone' => [['contact_number' => '12ab'], 'contact_number'],
]);

test('the honeypot rejects bots', function () {
    $this->post(frontRoute('contact.store'), [
        'name' => 'Spam Bot',
        'email' => 'bot@example.test',
        'content' => 'Buy now',
        'website' => 'http://spam.example',
    ])->assertSessionHasErrors('website');

    expect(Contact::where('email', 'bot@example.test')->exists())->toBeFalse();
});
