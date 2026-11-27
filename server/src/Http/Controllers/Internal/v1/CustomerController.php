<?php

namespace Fleetbase\EnhancedPricing\Http\Controllers\Internal\v1;

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Fleetbase\EnhancedPricing\Admin\Customers;
use Fleetbase\EnhancedPricing\Admin\RateGrid;
use Fleetbase\EnhancedPricing\Support\Authorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin page's customers, with their overrides, cards and rows, and the rate table grid's save,
 * import and export.
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

    public function saveRows(Request $request, string $uuid): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::RATE_CARDS);
        if ($refused !== null) {
            return $refused;
        }
        $token = $request->input('token');

        return $this->respond(fn () => (new RateGrid($this->company()))->save(
            $uuid,
            self::text($request, 'card'),
            self::text($request, 'table'),
            self::list($request, 'rows'),
            $request->boolean('replace'),
            is_string($token) ? $token : null,
        ));
    }

    public function importRows(Request $request, string $uuid): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::RATE_CARDS);
        if ($refused !== null) {
            return $refused;
        }

        return $this->respond(fn () => (new RateGrid($this->company()))->import(
            $uuid,
            self::text($request, 'card'),
            self::text($request, 'table'),
            self::text($request, 'filename'),
            self::text($request, 'content'),
            $request->boolean('replace'),
        ));
    }

    public function exportRows(Request $request, string $uuid): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::PROFILES, Authorizer::RATE_CARDS);
        if ($refused !== null) {
            return $refused;
        }

        return $this->respond(fn () => (new RateGrid($this->company()))->export(
            $uuid,
            self::query($request, 'card'),
            self::query($request, 'table'),
            self::query($request, 'format'),
        ));
    }

    /**
     * @return list<array<mixed>>
     */
    private static function list(Request $request, string $field): array
    {
        $value = $request->input($field);
        if (is_array($value) === false || array_is_list($value) === false) {
            throw AdminRefused::invalid('field-invalid', [], $field);
        }
        $rows = [];
        foreach ($value as $row) {
            if (is_array($row) === false) {
                throw AdminRefused::invalid('field-invalid', [], $field);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private static function query(Request $request, string $field): string
    {
        $value = $request->query($field);

        return is_string($value) ? $value : '';
    }
}
