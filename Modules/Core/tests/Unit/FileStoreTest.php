<?php

use Illuminate\Filesystem\Filesystem;
use Modules\Core\Cache\FileStore;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/core-filestore-'.uniqid();
    $this->files = new Filesystem;
    $this->store = new FileStore($this->files, $this->directory);
});

afterEach(function () {
    $this->files->deleteDirectory($this->directory);
});

/**
 * Two entity names whose sha1 shares the first four hex characters but differs
 * in the next four. They land in the same first-level directory, which is what
 * a shallower flush path would wipe wholesale.
 */
function collidingEntityNames(): array
{
    $seen = [];

    for ($i = 0; $i < 500000; $i++) {
        $name = 'Entity'.$i;
        $prefix = substr(sha1($name), 0, 4);

        if (! isset($seen[$prefix])) {
            $seen[$prefix] = $name;

            continue;
        }

        if (substr(sha1($seen[$prefix]), 4, 4) !== substr(sha1($name), 4, 4)) {
            return [$seen[$prefix], $name];
        }
    }

    throw new RuntimeException('No colliding entity names found.');
}

it('buckets an entity key into the directory moduleDirectory reports', function () {
    $key = 'modules -entity:Blog:all';

    expect($this->store->path($key))
        ->toStartWith($this->store->moduleDirectory($key).'/');
});

it('flushes only the requested entity when two entities share a bucket', function () {
    [$first, $second] = collidingEntityNames();

    $keyOne = "modules -entity:{$first}:all";
    $keyTwo = "modules -entity:{$second}:all";

    $this->store->put($keyOne, 'first-value', 600);
    $this->store->put($keyTwo, 'second-value', 600);

    $this->store->flushModuleCache("modules -entity:{$first}:");

    expect($this->store->get($keyOne))->toBeNull()
        ->and($this->store->get($keyTwo))->toBe('second-value');
});

it('refuses to treat a key without an entity segment as a module bucket', function () {
    expect($this->store->moduleDirectory('a plain key'))->toBeNull()
        ->and($this->store->flushModuleCache('a plain key'))->toBeFalse();
});

it('stores and reads back a value for a key with no entity segment', function () {
    $this->store->put('plain', ['a' => 1], 600);

    expect($this->store->get('plain'))->toBe(['a' => 1]);
});

it('reports false when flushing an entity that was never cached', function () {
    expect($this->store->flushModuleCache('modules -entity:NeverCached:'))->toBeFalse();
});
