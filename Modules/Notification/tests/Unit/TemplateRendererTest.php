<?php

use Modules\Notification\Support\TemplateRenderer;

test('placeholders are filled, with or without spaces, and unknown ones render empty', function () {
    $renderer = new TemplateRenderer;

    expect($renderer->render('Order {{order_no}} for {{ name }}{{missing}}.', ['order_no' => 'ORD-1', 'name' => 'Meera']))
        ->toBe('Order ORD-1 for Meera.')
        ->and($renderer->render('{{count}} / {{flag}} / {{list}}', ['count' => 3, 'flag' => true, 'list' => ['x']]))->toBe('3 / 1 / ')
        ->and($renderer->placeholders('{{a}} {{ b }} {{a}}'))->toBe(['a', 'b']);
});

test('HTML rendering escapes every value', function () {
    $html = (new TemplateRenderer)->render('<p>Hi {{name}}</p>', ['name' => '<script>alert("x")</script>'], escapeHtml: true);

    expect($html)->toBe('<p>Hi &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;</p>');
});

test('text rendering strips tags and collapses spaces', function () {
    expect((new TemplateRenderer)->renderText('<b>Hi</b>   {{name}} ', ['name' => 'Meera']))->toBe('Hi Meera');
});

test('SMS text is cut to two GSM-7 segments of 306 characters', function () {
    $renderer = new TemplateRenderer;
    $long = str_repeat('a', 400);

    $cut = $renderer->limitSms($long, 2);

    expect(TemplateRenderer::smsLength($cut))->toBe(306)
        ->and($cut)->toEndWith('...')
        ->and($renderer->limitSms(str_repeat('b', 300), 2))->toBe(str_repeat('b', 300))
        ->and(TemplateRenderer::smsLength($renderer->limitSms($long, 1)))->toBe(160);
});

test('GSM extension characters count twice and unicode text uses 67-character parts', function () {
    $renderer = new TemplateRenderer;

    expect(TemplateRenderer::isGsm7('Price {€}'))->toBeTrue()
        ->and(TemplateRenderer::smsLength('{€}'))->toBe(6)
        ->and(TemplateRenderer::isGsm7('आपका ऑर्डर'))->toBeFalse();

    $hindi = str_repeat('क', 200);
    $cut = $renderer->limitSms($hindi, 2);

    expect(TemplateRenderer::smsLength($cut))->toBe(134)
        ->and($renderer->limitSms(str_repeat('क', 70), 1))->toBe(str_repeat('क', 70));
});
