<?php

namespace Fleetbase\EnhancedPricing\Http\Controllers\Internal\v1;

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Fleetbase\EnhancedPricing\Admin\Changes;
use Fleetbase\EnhancedPricing\Support\Authorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin page's dated changes: scheduling and cancelling a subtotal fee's global value, and
 * every stored change listed.
 */
class DatedChangeController extends ConsoleController
{
    public function index(Request $request): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::PROFILES);
        if ($refused !== null) {
            return $refused;
        }
        $profile = $request->query('profile');

        return $this->respond(fn () => $this->changes()->schedule(is_string($profile) ? $profile : ''));
    }

    /**
     * accept_large_change is true once the person has been shown the large-change refusal and confirmed it.
     */
    public function store(Request $request): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::PROFILES);
        if ($refused !== null) {
            return $refused;
        }

        return $this->respond(function () use ($request): array {
            $acceptLarge = $request->input('accept_large_change', false);
            if (is_bool($acceptLarge) === false) {
                throw AdminRefused::invalid('field-invalid', [], 'accept_large_change');
            }

            return $this->changes()->add(
                self::text($request, 'profile'),
                self::text($request, 'path'),
                $request->input('value'),
                self::text($request, 'effective_from'),
                $this->user(),
                $acceptLarge,
            );
        }, 201);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::PROFILES);
        if ($refused !== null) {
            return $refused;
        }

        return $this->respond(fn () => $this->changes()->cancel($uuid, $this->user()));
    }

    private function changes(): Changes
    {
        return new Changes($this->company());
    }
}
