<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

pest()->extend(TestCase::class);

/** docs/openapi.yaml must describe exactly the routes the app serves. */
it('documents every API and document route, and nothing that does not exist', function () {
    $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));

    $documented = [];
    foreach ($spec['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                $documented[] = strtoupper($method).' '.$path;
            }
        }
    }

    $served = [];
    foreach (Route::getRoutes() as $route) {
        $uri = '/'.ltrim($route->uri(), '/');
        if (! preg_match('#^/(api|documents|sanctum)/#', $uri)) {
            continue;
        }
        // OpenAPI path parameters keep Laravel's {name} syntax.
        foreach ($route->methods() as $method) {
            if ($method !== 'HEAD') {
                $served[] = $method.' '.$uri;
            }
        }
    }

    sort($documented);
    sort($served);
    expect($documented)->toBe($served);
});

it('is a valid OpenAPI 3.1 document with resolvable component references', function () {
    $raw = file_get_contents(base_path('docs/openapi.yaml'));
    $spec = Yaml::parse($raw);

    expect($spec['openapi'])->toBe('3.1.0');

    preg_match_all("#'\\#/components/([a-zA-Z]+)/([A-Za-z_]+)'#", $raw, $refs, PREG_SET_ORDER);
    expect($refs)->not->toBeEmpty();
    foreach ($refs as [, $section, $name]) {
        expect($spec['components'][$section] ?? [])->toHaveKey($name);
    }
});
