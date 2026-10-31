# The released Fleetbase API image with Enhanced Pricing installed as a Composer package
# (install-extension.sh). The build context's extension/ directory is the extension's npm
# tarball, unpacked: the files the Fleetbase registry serves to Composer. Only the extension, and
# anything it needs that Fleetbase doesn't already lock, is added; every package Fleetbase ships
# stays at its released version.
ARG FLEETBASE_VERSION=latest
FROM fleetbase/fleetbase-api:${FLEETBASE_VERSION}

COPY --chown=www-data:www-data extension /fleetbase/extensions/enhanced-pricing
COPY install-extension.sh /usr/local/bin/install-enhanced-pricing
RUN su www-data -s /bin/sh -c /usr/local/bin/install-enhanced-pricing
