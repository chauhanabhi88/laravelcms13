<?php

use Modules\Core\Support\Migrations\SchemaParser;

it('parses a simple column definition into a blueprint call', function () {
    $parser = new SchemaParser('title:string');

    expect($parser->render())->toContain("\$table->string('title'");
});

it('keeps a column length passed after a comma', function () {
    $parser = new SchemaParser('title:string,100');

    expect($parser->render())->toContain('100');
});

it('chains modifiers listed after the column type', function () {
    $parser = new SchemaParser('slug:string:nullable:unique');

    $rendered = $parser->render();

    expect($rendered)->toContain('->nullable()')
        ->and($rendered)->toContain('->unique()');
});

it('splits several columns on the ## delimiter', function () {
    $parser = new SchemaParser('title:string##body:text');

    $parsed = $parser->toArray();

    expect($parsed)->toHaveKeys(['title', 'body']);
});

it('expands the soft_delete custom attribute', function () {
    $parser = new SchemaParser('soft_delete');

    expect($parser->render())->toContain('softDeletes()');
});

it('drops each column in the down migration', function () {
    $parser = new SchemaParser('title:string');

    expect($parser->down())->toContain("dropColumn('title')");
});

it('returns no schemas when given null', function () {
    expect((new SchemaParser(null))->toArray())->toBe([]);
});

it('studly cases an entity name', function () {
    expect((new SchemaParser)->getEntityName('blog_post'))->toBe('BlogPost');
});
