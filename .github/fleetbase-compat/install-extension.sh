#!/bin/sh
# Runs inside the Fleetbase API image as www-data (see Dockerfile): adds the unpacked extension as a
# Composer path repository and requires it at the version in its composer.json, without dev
# packages. Nothing Fleetbase already locks is changed: if Fleetbase locks brick/math or
# fleetbase/core-api at a version the extension doesn't accept, this fails, as an install would.
set -eu
cd /fleetbase/api
export COMPOSER_HOME=/tmp/composer

# Drop the Fleetbase registry repository so the build doesn't depend on it and can't install a
# published copy of the extension instead of this one.
php -r '
    $file = "composer.json";
    $json = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $json["repositories"] = array_values(array_filter(
        $json["repositories"] ?? [],
        fn ($repository) => ($repository["url"] ?? "") !== "https://registry.fleetbase.io"
    ));
    file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
'
composer config repositories.enhanced-pricing \
    '{"type": "path", "url": "/fleetbase/extensions/enhanced-pricing", "options": {"symlink": false}}'

version="$(php -r 'echo json_decode(file_get_contents("/fleetbase/extensions/enhanced-pricing/composer.json"), true)["version"];')"
composer require --update-no-dev --no-interaction --no-scripts --no-progress --no-ansi "unchartedwaters/enhanced-pricing-api:${version}"
composer dump-autoload --optimize --no-dev --no-ansi
rm -rf /tmp/composer
