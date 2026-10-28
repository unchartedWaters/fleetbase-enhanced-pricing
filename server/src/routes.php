<?php

use Illuminate\Support\Facades\Route;

/*
| The console's endpoints, under /enhanced-pricing/int/v1, behind Fleetbase's console session.
| Fleetbase checks no permissions for these controllers, so each action asks Authorizer first.
*/
Route::prefix('enhanced-pricing/int/v1')
    ->namespace('Fleetbase\EnhancedPricing\Http\Controllers\Internal\v1')
    ->middleware(['fleetbase.protected'])
    ->group(function (Illuminate\Routing\Router $router) {
        $router->get('admin/form', 'ProfileController@form');
        $router->get('admin/profiles', 'ProfileController@index');
        $router->post('admin/profiles', 'ProfileController@store');
        $router->get('admin/profiles/{key}', 'ProfileController@show');
        $router->put('admin/profiles/{key}', 'ProfileController@update');
        $router->get('admin/rate-cards', 'CustomerController@index');
        $router->get('admin/rate-cards/{uuid}', 'CustomerController@show');
        $router->get('admin/dated-changes', 'DatedChangeController@index');
        $router->post('admin/dated-changes', 'DatedChangeController@store');
        $router->delete('admin/dated-changes/{uuid}', 'DatedChangeController@destroy');
        $router->post('admin/preview', 'PreviewController@preview');
    });
