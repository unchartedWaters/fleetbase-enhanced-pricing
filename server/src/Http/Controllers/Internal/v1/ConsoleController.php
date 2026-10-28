<?php

namespace Fleetbase\EnhancedPricing\Http\Controllers\Internal\v1;

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Session;

/**
 * What the console's controllers share: the session's company and user, as Fleetbase's
 * SetupFleetbaseSession sets them, and refusals sent as the body the console reads.
 */
abstract class ConsoleController extends Controller
{
    protected function company(): string
    {
        $company = Session::get('company');

        return is_string($company) ? $company : throw AdminRefused::notFound();
    }

    protected function user(): ?string
    {
        $user = Session::get('user');

        return is_string($user) ? $user : null;
    }

    /**
     * @param \Closure(): array<mixed> $work
     */
    protected function respond(\Closure $work, int $status = 200): JsonResponse
    {
        try {
            return new JsonResponse($work(), $status);
        } catch (AdminRefused $refused) {
            return new JsonResponse($refused->body, $refused->status);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected static function object(Request $request, string $field): array
    {
        $value = $request->input($field);
        if (is_array($value) === false || ($value !== [] && array_is_list($value))) {
            throw AdminRefused::invalid('field-invalid', [], $field);
        }
        $object = [];
        foreach ($value as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }

    protected static function text(Request $request, string $field): string
    {
        $value = $request->input($field);

        return is_string($value) && $value !== '' ? $value : throw AdminRefused::invalid('field-required', [], $field);
    }
}
