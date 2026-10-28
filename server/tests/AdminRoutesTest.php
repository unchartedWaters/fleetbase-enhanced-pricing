<?php

use Fleetbase\EnhancedPricing\Providers\EnhancedPricingServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;

// The console's endpoints: each sits behind Fleetbase's console session middleware, and each
// action asks the authorizer first, since Fleetbase checks no permissions for these controllers.

/**
 * @return array<string, array{middleware: list<string>, action: string}>
 */
function adminRoutes(): array
{
    $container = new Container();
    Container::setInstance($container);
    $router = new Router(new Dispatcher($container), $container);
    $container->instance('router', $router);
    $container->instance('config', new Illuminate\Config\Repository([]));
    Facade::setFacadeApplication($container);
    Facade::clearResolvedInstances();
    Route::swap($router);

    require __DIR__ . '/../src/routes.php';

    $routes = [];
    foreach ($router->getRoutes()->getRoutes() as $route) {
        foreach ($route->methods() as $method) {
            if ($method !== 'HEAD') {
                $routes["$method /" . $route->uri()] = [
                    'middleware' => array_values($route->gatherMiddleware()),
                    'action'     => $route->getActionName(),
                ];
            }
        }
    }
    ksort($routes);

    return $routes;
}

test('the console endpoints are the admin page\'s, behind the console session', function () {
    $routes = adminRoutes();

    expect(array_keys($routes))->toBe([
        'DELETE /enhanced-pricing/int/v1/admin/dated-changes/{uuid}',
        'GET /enhanced-pricing/int/v1/admin/dated-changes',
        'GET /enhanced-pricing/int/v1/admin/form',
        'GET /enhanced-pricing/int/v1/admin/profiles',
        'GET /enhanced-pricing/int/v1/admin/profiles/{key}',
        'GET /enhanced-pricing/int/v1/admin/rate-cards',
        'GET /enhanced-pricing/int/v1/admin/rate-cards/{uuid}',
        'POST /enhanced-pricing/int/v1/admin/dated-changes',
        'POST /enhanced-pricing/int/v1/admin/preview',
        'POST /enhanced-pricing/int/v1/admin/profiles',
        'PUT /enhanced-pricing/int/v1/admin/profiles/{key}',
    ]);
    foreach ($routes as $name => $route) {
        expect($route['middleware'])->toBe(['fleetbase.protected'], $name);
    }
});

test('every console action asks the authorizer before anything else', function () {
    foreach (adminRoutes() as $name => $route) {
        [$class, $method] = explode('@', $route['action']);
        $reflection       = new ReflectionMethod($class, $method);
        $lines            = file((string) $reflection->getFileName());
        $body             = implode('', array_slice($lines, $reflection->getStartLine(), 4));

        expect($body)->toMatch('/\$refused = Authorizer::refusal\(\$request, /', $name);
    }
});

test('the provider loads the routes', function () {
    $source = (string) file_get_contents((new ReflectionClass(EnhancedPricingServiceProvider::class))->getFileName());

    expect($source)->toContain("\$this->loadRoutesFrom(__DIR__ . '/../routes.php');");
});
