#!/usr/bin/env bash
# Tests decide.sh, which tells the workflow whether a scheduled run builds, with gh and docker
# stubbed: bash .github/fleetbase-compat/test_decide.sh
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
stubs="$(mktemp -d)"
trap 'rm -rf "$stubs"' EXIT

# gh api ... --jq FILTER: prints $ARTIFACTS_JSON through jq with the filter.
cat > "$stubs/gh" <<'STUB'
#!/usr/bin/env bash
filter=""
while [ $# -gt 0 ]; do
    if [ "$1" = --jq ]; then filter="$2"; shift; fi
    shift
done
jq -r "$filter" <<<"${ARTIFACTS_JSON}"
STUB
# docker manifest inspect IMAGE: succeeds when $IMAGE_PUBLISHED is yes.
cat > "$stubs/docker" <<'STUB'
#!/usr/bin/env bash
[ "${IMAGE_PUBLISHED}" = yes ]
STUB
chmod +x "$stubs/gh" "$stubs/docker"

none='{"artifacts": []}'
tested='{"artifacts": [{"expired": false, "workflow_run": {"head_branch": "some-branch"}}]}'
expired='{"artifacts": [{"expired": true, "workflow_run": {"head_branch": "main"}}]}'
failures=0

check() { # name expected event published_days_ago image artifacts
    local name="$1" expected="$2" event="$3" age="$4" image="$5" artifacts="$6" out status
    out="$(mktemp)"
    status=0
    PATH="$stubs:$PATH" EVENT="$event" VERSION=v9.9.9 GITHUB_REPOSITORY=o/r \
        PUBLISHED_AT="$(date -u -d "-${age} days" +%Y-%m-%dT%H:%M:%SZ)" \
        IMAGE_PUBLISHED="$image" ARTIFACTS_JSON="$artifacts" GITHUB_OUTPUT="$out" \
        bash "$here/decide.sh" >/dev/null 2>&1 || status=$?
    local got
    got="$(sed -n 's/^build=//p' "$out")"
    [ "$status" = 0 ] || got="error"
    rm -f "$out"
    if [ "$got" = "$expected" ]; then
        echo "ok   $name"
    else
        echo "FAIL $name: expected $expected, got $got"
        failures=$((failures + 1))
    fi
}

check 'schedule: a new release with its image builds' true schedule 1 yes "$none"
check 'schedule: a release already tested is skipped' false schedule 1 yes "$tested"
check 'schedule: a release whose results expired is not rebuilt' false schedule 30 yes "$expired"
check 'schedule: an untested release older than 14 days is skipped' false schedule 15 yes "$none"
check 'schedule: a new release without its image waits' false schedule 0 no "$none"
check 'manual: always builds, even when tested' true workflow_dispatch 300 yes "$tested"
check 'manual: a release without its image fails' error workflow_dispatch 0 no "$none"
check 'release tag: always builds' true push 300 yes "$tested"

[ "$failures" = 0 ] || exit 1
echo "decide.sh checks passed."
