<?php

namespace Fleetbase\EnhancedPricing\Http\Controllers\Internal\v1;

use Fleetbase\EnhancedPricing\Admin\HolidayCalendars;
use Fleetbase\EnhancedPricing\Support\Authorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The holiday calendars the profile editor fills holiday lists from. Read-only bundled data.
 */
class HolidayCalendarController extends ConsoleController
{
    public function index(Request $request): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::SEE);
        if ($refused !== null) {
            return $refused;
        }

        return new JsonResponse(HolidayCalendars::catalog());
    }

    public function show(Request $request, string $country): JsonResponse
    {
        $refused = Authorizer::refusal($request, Authorizer::SEE);
        if ($refused !== null) {
            return $refused;
        }

        return $this->respond(fn () => HolidayCalendars::list($country, $request->query('region'), $request->query('years')));
    }
}
