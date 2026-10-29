<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Modules\Core\Support\Api\ApiCodes;
use Symfony\Component\Yaml\Yaml;

/**
 * Keeps Modules/Core/resources/api/openapi.yaml honest (plan M20 §6 task 7): it must parse, every $ref
 * must resolve, and the documented endpoints must be exactly the buyer routes that carry the v1
 * envelope - so an endpoint added without documentation, or a stale path, fails the build.
 */
function apiSpec(): array
{
    static $spec;

    return $spec ??= Yaml::parseFile(base_path('Modules/Core/resources/api/openapi.yaml'));
}

/**
 * "METHOD /path/{}" for every documented operation (parameter names ignored).
 *
 * @return list<string>
 */
function apiDocumentedOperations(): array
{
    $operations = [];

    foreach (apiSpec()['paths'] as $path => $item) {
        foreach (array_keys($item) as $method) {
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                $operations[] = strtoupper($method).' '.preg_replace('/\{[^}]+\}/', '{}', $path);
            }
        }
    }

    sort($operations);

    return $operations;
}

/**
 * "METHOD /path/{}" for every v1 route that carries the envelope middleware.
 *
 * @return list<string>
 */
function apiRoutedOperations(): array
{
    $operations = [];

    /** @var LaravelRoute $route */
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1/') || ! in_array('api.envelope', $route->gatherMiddleware(), true)) {
            continue;
        }

        // The pre-M20 placeholder that answers {type: success}; not part of the product API.
        if ($route->getName() === 'customer_api.index') {
            continue;
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $operations[] = $method.' '.preg_replace('/\{[^}]+\}/', '{}', substr($route->uri(), strlen('api/v1')));
        }
    }

    sort($operations);

    return array_values(array_unique($operations));
}

/**
 * @param  array<int|string, mixed>  $node
 * @return list<string>
 */
function apiSpecRefs(array $node): array
{
    $refs = [];

    foreach ($node as $key => $value) {
        if ($key === '$ref' && is_string($value)) {
            $refs[] = $value;
        } elseif (is_array($value)) {
            $refs = array_merge($refs, apiSpecRefs($value));
        }
    }

    return $refs;
}

it('is a parseable OpenAPI 3.1 document', function () {
    $spec = apiSpec();

    expect($spec['openapi'])->toStartWith('3.1')
        ->and($spec['info']['title'])->toBeString()
        ->and($spec['paths'])->not->toBeEmpty()
        ->and($spec['components']['securitySchemes'])->toHaveKeys(['bearerAuth', 'cartToken']);
});

it('resolves every $ref', function () {
    $spec = apiSpec();

    foreach (array_unique(apiSpecRefs($spec)) as $ref) {
        expect($ref)->toStartWith('#/');

        $node = $spec;
        foreach (explode('/', substr($ref, 2)) as $segment) {
            expect(array_key_exists($segment, $node))->toBeTrue("Unresolved \$ref {$ref}");
            $node = $node[$segment];
        }
    }
});

it('documents exactly the routes that carry the v1 envelope', function () {
    $documented = apiDocumentedOperations();
    $routed = apiRoutedOperations();

    expect(array_values(array_diff($routed, $documented)))->toBe([], 'Routes missing from openapi.yaml')
        ->and(array_values(array_diff($documented, $routed)))->toBe([], 'openapi.yaml paths without a route');
});

it('gives every operation at least one response and a summary', function () {
    foreach (apiSpec()['paths'] as $path => $item) {
        foreach ($item as $method => $operation) {
            if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }

            expect(array_keys($operation))->toContain('summary', 'responses');
            expect($operation['responses'])->not->toBeEmpty();
        }
    }
});

it('lists the same app-specific error codes as the catalogue', function () {
    expect(apiSpec()['components']['schemas']['ErrorCode']['enum'])->toBe(array_keys(ApiCodes::names()));
});
