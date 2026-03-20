<?php

declare(strict_types=1);

/**
 * Example 02: Routing and Request — registering routes and handling requests.
 *
 * This example shows how to use the Router independently of a full Laravel
 * application. It demonstrates route registration (GET, POST, route groups,
 * resource routes) and request construction for testing purposes.
 *
 * Run with:
 *   php examples/02_routing_and_request.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

// ── 1. Bootstrap the router ──────────────────────────────────────────────────

$container = new Container();
$events    = new Dispatcher($container);
$router    = new Router($events, $container);

// ── 2. Register routes ───────────────────────────────────────────────────────

$router->get('/hello', function () {
    return 'Hello, World!';
});

$router->get('/users/{id}', function (int $id) {
    return json_encode(['id' => $id, 'name' => "User {$id}"]);
})->where('id', '[0-9]+');

$router->post('/users', function (Request $request) {
    $payload = $request->only(['name', 'email']);
    return json_encode(['created' => true, 'data' => $payload]);
});

// Route group with a URI prefix
$router->prefix('api')->group(function (Router $router) {
    $router->get('/status', function () {
        return json_encode(['status' => 'ok']);
    });
});

// ── 3. Dispatch synthetic requests ───────────────────────────────────────────

$requests = [
    Request::create('/hello', 'GET'),
    Request::create('/users/42', 'GET'),
    Request::create('/users', 'POST', ['name' => 'Alice', 'email' => 'a@example.com']),
    Request::create('/api/status', 'GET'),
];

foreach ($requests as $request) {
    $response = $router->dispatch($request);
    echo sprintf(
        "[%s] %s → %d: %s\n",
        $request->getMethod(),
        $request->getPathInfo(),
        $response->getStatusCode(),
        $response->getContent()
    );
}

// ── 4. Inspecting the Request object ─────────────────────────────────────────

$sample = Request::create(
    'https://example.com/users/5?tab=profile',
    'GET',
    [],
    [],
    [],
    ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
);

echo "\n--- Request inspection ---\n";
echo "Method : " . $sample->method() . "\n";
echo "Path   : " . $sample->path() . "\n";
echo "Segment: " . $sample->segment(2) . "\n";     // '5'
echo "Query  : " . json_encode($sample->query()) . "\n";
echo "Host   : " . $sample->host() . "\n";
echo "Ajax?  : " . ($sample->ajax() ? 'yes' : 'no') . "\n";
echo "Wants JSON? : " . ($sample->wantsJson() ? 'yes' : 'no') . "\n";
