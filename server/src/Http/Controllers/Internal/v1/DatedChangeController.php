<?php

namespace Fleetbase\EnhancedPricing\Http\Controllers\Internal\v1;

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

    public function store(Request $request): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::PROFILES);
        if ($refused !== null) {
            return $refused;
        }

        return $this->respond(fn () => $this->changes()->add(
            self::text($request, 'profile'),
            self::text($request, 'path'),
            $request->input('value'),
            self::text($request, 'effective_from'),
            $this->user(),
        ), 201);
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
