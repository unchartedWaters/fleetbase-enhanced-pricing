<?php

namespace Fleetbase\EnhancedPricing\Http\Controllers\Internal\v1;

use Fleetbase\EnhancedPricing\Admin\Preview;
use Fleetbase\EnhancedPricing\Support\Authorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin page's quote preview. Nothing is saved.
 */
class PreviewController extends ConsoleController
{
    public function preview(Request $request): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::SEE);
        if ($refused !== null) {
            return $refused;
        }

        return $this->respond(function () use ($request): array {
            /** @var array<string, mixed> $input */
            $input = $request->only([
                'customer', 'profile', 'service_rate', 'pickup_at', 'timezone', 'distance_meters', 'row',
                'attributes', 'entities', 'manual_charges',
            ]);

            return (new Preview($this->company()))->price($input);
        });
    }
}
