<?php

namespace Fleetbase\EnhancedPricing\Http\Controllers\Internal\v1;

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Fleetbase\EnhancedPricing\Admin\ProfileForm;
use Fleetbase\EnhancedPricing\Admin\Profiles;
use Fleetbase\EnhancedPricing\Support\Authorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin page's profiles and the profile editor.
 */
class ProfileController extends ConsoleController
{
    public function form(Request $request): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::SEE);
        if ($refused !== null) {
            return $refused;
        }

        return new JsonResponse(ProfileForm::spec());
    }

    public function index(Request $request): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::PROFILES, Authorizer::RATE_CARDS);
        if ($refused !== null) {
            return $refused;
        }

        return $this->respond(fn () => ['profiles' => $this->profiles()->list()]);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::PROFILES, Authorizer::RATE_CARDS);
        if ($refused !== null) {
            return $refused;
        }
        $version = $request->query('version');

        return $this->respond(fn () => $this->profiles()->show(
            $key,
            is_string($version) && ctype_digit($version) ? (int) $version : null,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::PROFILES);
        if ($refused !== null) {
            return $refused;
        }

        return $this->respond(fn () => $this->profiles()->create(
            self::text($request, 'key'),
            self::text($request, 'name'),
            self::object($request, 'definition'),
            $this->user(),
        ), 201);
    }

    /**
     * built_on is the version the editor opened; without it the save replaces the newest version.
     * accept_stale lists the stale entries the person was shown and accepted.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::PROFILES);
        if ($refused !== null) {
            return $refused;
        }

        return $this->respond(function () use ($request, $key): array {
            $builtOn = $request->input('built_on');
            $accept  = $request->input('accept_stale', []);
            if (($builtOn !== null && is_int($builtOn) === false) || is_array($accept) === false) {
                throw AdminRefused::invalid('field-invalid', [], is_array($accept) ? 'built_on' : 'accept_stale');
            }

            return $this->profiles()->save(
                $key,
                self::object($request, 'definition'),
                $builtOn,
                array_values(array_filter($accept, 'is_string')),
                $this->user(),
            );
        });
    }

    private function profiles(): Profiles
    {
        return new Profiles($this->company());
    }
}
