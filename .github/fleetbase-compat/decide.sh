#!/usr/bin/env bash
# Decides whether this run of fleetbase-compat.yml builds, and writes build=true or build=false
# to $GITHUB_OUTPUT. Tested by test_decide.sh.
#
# A scheduled run builds only a new Fleetbase release: the latest release, published in the last
# 14 days, that no run has tested yet (no unexpired fleetbase-<version>-results artifact, from any
# branch), once its API image is on Docker Hub. Results are kept 90 days, so the 14-day limit
# stops a release from being rebuilt when its results expire. A manual run or this extension's
# release tag always builds, and fails if the image is not on Docker Hub.
#
# Environment: EVENT (github.event_name), VERSION (the release tag), PUBLISHED_AT (its
# published_at), DIGEST (the API image digest, empty when Docker Hub does not have it),
# GITHUB_REPOSITORY, GITHUB_OUTPUT, and GH_TOKEN for gh.
set -euo pipefail

skip() {
    echo "::notice::$1"
    echo "build=false" >> "$GITHUB_OUTPUT"
    exit 0
}

if [ "${EVENT}" = schedule ]; then
    age_days=$(( ($(date -u +%s) - $(date -u -d "${PUBLISHED_AT}" +%s)) / 86400 ))
    if [ "${age_days}" -ge 14 ]; then
        skip "Fleetbase ${VERSION} came out ${age_days} days ago; no new release to test."
    fi
    tested="$(gh api "repos/${GITHUB_REPOSITORY}/actions/artifacts?name=fleetbase-${VERSION}-results&per_page=100" \
        --jq '[.artifacts[] | select(.expired == false)] | length')"
    if [ "${tested}" != 0 ]; then
        skip "Fleetbase ${VERSION} has already been tested."
    fi
fi

if [ -z "${DIGEST:-}" ]; then
    if [ "${EVENT}" = schedule ]; then
        skip "fleetbase/fleetbase-api:${VERSION} isn't on Docker Hub yet; the next scheduled run tries again."
    fi
    echo "::error::fleetbase/fleetbase-api:${VERSION} isn't on Docker Hub."
    exit 1
fi

echo "build=true" >> "$GITHUB_OUTPUT"
