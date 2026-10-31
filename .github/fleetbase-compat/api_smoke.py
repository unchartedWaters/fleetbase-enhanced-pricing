"""Checks the Enhanced Pricing extension through a running Fleetbase API.

Usage: python3 api_smoke.py <api base url> <extension checkout> <credentials out file>

Run after api/deploy.sh. It signs up the first user (who becomes the administrator), then uses
the extension's console endpoints the way the Pricing page does: the form, saving a profile, a
refused save, the profile list and a quote preview, whose lines must match the worked example in
server/tests/WorkedExamplesTest.php. It writes the user's email and password for the console
check. Python's standard library only.
"""

import json
import secrets
import sys
import urllib.error
import urllib.request

PREFIX = "/enhanced-pricing/int/v1/admin"


def call(base, method, path, body=None, token=None):
    data = None if body is None else json.dumps(body).encode()
    request = urllib.request.Request(base + path, data=data, method=method)
    request.add_header("Accept", "application/json")
    request.add_header("Content-Type", "application/json")
    if token:
        request.add_header("Authorization", "Bearer " + token)
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            status, raw = response.status, response.read()
    except urllib.error.HTTPError as error:
        status, raw = error.code, error.read()
    try:
        return status, json.loads(raw or b"null")
    except ValueError:
        return status, raw.decode(errors="replace")[:2000]


def expect(condition, message, detail=None):
    if not condition:
        print("FAIL: " + message)
        if detail is not None:
            print(json.dumps(detail, indent=2)[:4000] if not isinstance(detail, str) else detail)
        sys.exit(1)
    print("ok   " + message)


def main():
    base, repo, credentials_path = sys.argv[1].rstrip("/"), sys.argv[2], sys.argv[3]
    with open(repo + "/server/tests/Fixtures/example-profile.json") as handle:
        definition = json.load(handle)

    status, body = call(base, "GET", PREFIX + "/profiles")
    expect(status == 401, "the profile list refuses a request without a session", [status, body])

    email = "pricing-ci@unchartedwaters.dev"
    password = secrets.token_urlsafe(18) + "Aa1!"
    status, body = call(base, "POST", "/int/v1/onboard/create-account", {
        "name": "Pricing CI",
        "email": email,
        "phone": "+14155552671",
        "password": password,
        "password_confirmation": password,
        "organization_name": "Pricing CI Co",
    })
    token = body.get("token") if isinstance(body, dict) else None
    expect(status == 200 and token, "the first sign-up returns an administrator's token", [status, body])
    with open(credentials_path, "w") as handle:
        json.dump({"email": email, "password": password}, handle)

    status, body = call(base, "GET", PREFIX + "/form", token=token)
    expect(status == 200 and isinstance(body, dict) and body, "the profile form loads", [status, body])

    status, body = call(base, "POST", PREFIX + "/profiles", {
        "key": "ci-example", "name": "CI example", "definition": {"currency": "USD"},
    }, token=token)
    expect(status == 422, "an incomplete profile is refused", [status, body])

    status, body = call(base, "POST", PREFIX + "/profiles", {
        "key": "ci-example", "name": "CI example", "definition": definition,
    }, token=token)
    expect(status == 201 and isinstance(body, dict) and body.get("key") == "ci-example",
           "the example profile is saved", [status, body])

    status, body = call(base, "GET", PREFIX + "/profiles", token=token)
    keys = json.dumps(body)
    expect(status == 200 and '"ci-example"' in keys, "the profile list shows the saved profile", [status, body])

    status, body = call(base, "GET", PREFIX + "/profiles/ci-example", token=token)
    expect(status == 200, "the saved profile opens", [status, body])

    # WorkedExamplesTest: 'a distance order raised to the minimum distance'.
    status, body = call(base, "POST", PREFIX + "/preview", {
        "profile": "ci-example",
        "service_rate": "distance",
        "pickup_at": "2027-03-03T10:00:00",
        "timezone": "UTC",
        "distance_meters": 7000,
        "attributes": {},
        "entities": [{"type": None, "weight": 42, "weight_unit": "kg"}],
        "manual_charges": [],
    }, token=token)
    quote = body.get("quote") if isinstance(body, dict) else None
    expect(status == 200 and isinstance(quote, dict), "the preview returns a quote", [status, body])
    lines = [[line["product"], line["name"], line["quantity"], line["unit_amount"], line["amount"]]
             for line in quote["lines"]]
    expect(lines == [
        ["DISTANCE", "Distance (10 km)", 1, 3500, 3500],
        ["PARCEL", "Parcel fee (12 kg)", 1, 480, 480],
        ["PERCENT", "Percentage subtotal fee (11% of 4.80)", 1, 53, 53],
    ] and quote["total"] == 4033 and quote["currency"] == "USD",
        "the quote matches the worked example (USD 40.33)", quote)

    print("API checks passed.")


main()
