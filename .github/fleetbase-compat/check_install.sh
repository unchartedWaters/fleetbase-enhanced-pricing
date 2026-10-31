#!/usr/bin/env bash
# Checks that Fleetbase installed the extension: the package is in the API, every migration in
# server/migrations ran, every route in server/src/routes.php is registered, and
# fleetbase:create-permissions made the permissions and policies SETUP.md lists.
# Usage: check_install.sh <fleetbase checkout> <extension checkout> (with the compose stack up).
set -euo pipefail
fleetbase="$1"
extension="$2"
compose=(docker compose --project-directory "$fleetbase" -f "$fleetbase/docker-compose.yml" -f "$(dirname "$0")/compose.yml")
artisan() { "${compose[@]}" exec -T application php artisan "$@"; }
fail() { echo "FAIL: $*"; exit 1; }

"${compose[@]}" exec -T application composer show --no-ansi --format=json unchartedwaters/enhanced-pricing-api >/dev/null \
    || fail "the API does not have unchartedwaters/enhanced-pricing-api"
echo "ok   the API has unchartedwaters/enhanced-pricing-api"

status="$(artisan migrate:status --no-ansi)"
for file in "$extension"/server/migrations/*.php; do
    name="$(basename "$file" .php)"
    grep -E "^\s*${name}\b.*\bRan\b" <<<"$status" >/dev/null || fail "migration $name did not run"
done
if grep -qi pending <<<"$status"; then
    grep -i pending <<<"$status"
    fail "Fleetbase has pending migrations"
fi
echo "ok   every extension migration ran and none are pending"

routes="$(artisan route:list --no-ansi --path=enhanced-pricing)"
expected="$(grep -cE '\$router->(get|post|put|patch|delete)\(' "$extension/server/src/routes.php")"
found="$(grep -c 'enhanced-pricing/int/v1/' <<<"$routes" || true)"
[ "$found" = "$expected" ] || { echo "$routes"; fail "expected $expected enhanced-pricing routes, found $found"; }
echo "ok   all $expected enhanced-pricing routes are registered"

names="$("${compose[@]}" exec -T database mysql -N -uroot fleetbase -e \
    "SELECT name FROM permissions WHERE name LIKE 'enhanced-pricing %' UNION ALL SELECT name FROM policies WHERE name LIKE 'EnhancedPricing%'")"
for name in 'enhanced-pricing see extension' 'enhanced-pricing manage pricing-profile' \
    'enhanced-pricing manage rate-card' 'enhanced-pricing manage manual-charge' \
    EnhancedPricingManager EnhancedPricingRateCards; do
    grep -qxF "$name" <<<"$names" || { echo "$names"; fail "missing permission or policy: $name"; }
done
echo "ok   the extension's permissions and policies exist"
