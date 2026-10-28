<?php

namespace Fleetbase\EnhancedPricing\Http\Controllers\Internal\v1;

use Fleetbase\EnhancedPricing\Admin\Customers;
use Fleetbase\EnhancedPricing\Support\Authorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin page's customers, with their overrides, cards and rows. Read-only.
 */
class CustomerController extends ConsoleController
{
    public function index(Request $request): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::PROFILES, Authorizer::RATE_CARDS);
        if ($refused !== null) {
            return $refused;
        }
        $page = $request->query('page');

        return $this->respond(fn () => (new Customers($this->company()))->list(
            self::query($request, 'profile'),
            is_string($page) && ctype_digit($page) ? (int) $page : 1,
        ));
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::PROFILES, Authorizer::RATE_CARDS);
        if ($refused !== null) {
            return $refused;
        }

        return $this->respond(fn () => (new Customers($this->company()))->show($uuid));
    }

    private static function query(Request $request, string $field): string
    {
        $value = $request->query($field);

        return is_string($value) ? $value : '';
    }
}
