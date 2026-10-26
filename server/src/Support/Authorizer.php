<?php

namespace Fleetbase\EnhancedPricing\Support;

use Fleetbase\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Checks the extension's permissions in each controller. Fleetbase's AuthorizationGuard checks
 * permissions only for resource controllers, and these controllers aren't, so each one asks
 * here first (plan, Endpoints).
 *
 * Fleet-Ops' managed users (drivers, customers and contacts) are refused on every console route:
 * a customer signed in to the portal has a console session too. An administrator passes, as
 * Fleetbase's own guard lets one pass. Anyone else needs one of the permissions asked for, the
 * extension's wildcard, or, for "see extension", any of the extension's manage permissions.
 */
final class Authorizer
{
    public const SERVICE = 'enhanced-pricing';

    public const SEE = 'see extension';

    public const PROFILES = 'manage pricing-profile';

    public const RATE_CARDS = 'manage rate-card';

    public const CHARGES = 'manage manual-charge';

    /** Fleetbase's User::MANAGED_TYPES. */
    public const MANAGED_TYPES = ['driver', 'customer', 'contact'];

    /**
     * @param list<string> $held  full permission names, such as "enhanced-pricing manage rate-card"
     * @param list<string> $anyOf permissions without the service, such as self::PROFILES
     */
    public static function allows(?string $userType, array $held, array $anyOf): bool
    {
        $managed = in_array($userType, [...self::MANAGED_TYPES, 'managed'], true);
        if ($userType === null || $anyOf === [] || $managed) {
            return false;
        }
        if ($userType === 'admin') {
            return true;
        }
        if (in_array(self::SEE, $anyOf, true)) {
            $anyOf = array_merge($anyOf, [self::PROFILES, self::RATE_CARDS, self::CHARGES]);
        }
        $wanted   = array_map(static fn (string $name): string => self::SERVICE . ' ' . $name, $anyOf);
        $wanted[] = self::SERVICE . ' *';

        return array_intersect($wanted, $held) !== [];
    }

    /**
     * The response refusing this request, or null when its user may go on.
     */
    public static function refusal(Request $request, string ...$anyOf): ?JsonResponse
    {
        $user = $request->user();
        if ($user instanceof User === false) {
            return new JsonResponse(['refused' => 'unauthenticated'], 401);
        }
        $type = match (true) {
            $user->isAdmin()                     => 'admin',
            $user->isType(self::MANAGED_TYPES)   => 'managed',
            default                              => 'user',
        };
        if (self::allows($type, self::held($user), array_values($anyOf))) {
            return null;
        }

        return new JsonResponse(['refused' => 'forbidden'], 403);
    }

    /**
     * The permission names a user holds in its current company, directly, through roles or
     * through policies. A user with no membership in the company holds none.
     *
     * @return list<string>
     */
    private static function held(User $user): array
    {
        $membership = $user->getCompanyUser();
        if ($membership === null) {
            return [];
        }
        $names       = [];
        $permissions = $membership->getAllPermissions();
        foreach (is_iterable($permissions) ? $permissions : [] as $permission) {
            if (is_object($permission) && is_string($permission->name ?? null)) {
                $names[] = $permission->name;
            }
        }

        return $names;
    }
}
