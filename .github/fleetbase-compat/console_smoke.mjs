// Checks the Pricing page in the Fleetbase console built with the extension's engine.
//
// Usage: node console_smoke.mjs <console url> <credentials json> <output dir>
//
// Run after api_smoke.py, which signs up the user in <credentials json> and saves the profile
// "ci-example" (server/tests/Fixtures/example-profile.json). This signs in through the console's
// login form, opens the Pricing page's profile list and the profile, then prices the worked
// example on the quote preview page through its form, as a person would, and expects USD 40.33
// (server/tests/WorkedExamplesTest.php). It fails on an uncaught error from the engine or an
// enhanced-pricing request that fails. Screenshots and the browser console go to <output dir>.
// Needs Playwright (require('playwright')).
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const [base, credentialsPath, out] = process.argv.slice(2);
const { email, password } = JSON.parse(readFileSync(credentialsPath, 'utf8'));
mkdirSync(out, { recursive: true });

const log = [];
const problems = [];
const previewBodies = [];
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
page.setDefaultTimeout(60_000);

page.on('console', (message) => log.push(`[${message.type()}] ${message.text()}`));
page.on('pageerror', (error) => {
    const text = `${error.message}\n${error.stack ?? ''}`;
    log.push(`[pageerror] ${text}`);
    if (/enhanced-pricing/i.test(text)) {
        problems.push(`uncaught error from the engine: ${error.message}`);
    }
});
page.on('request', (request) => {
    if (request.url().includes('/enhanced-pricing/int/v1/admin/preview')) {
        previewBodies.push(request.postData());
    }
});
page.on('response', (response) => {
    if (response.url().includes('/enhanced-pricing/int/v1/') && response.status() >= 400) {
        problems.push(`${response.request().method()} ${response.url()} answered ${response.status()}`);
    }
});

let step = 'start';
const shot = (name) => page.screenshot({ path: `${out}/${name}.png`, fullPage: true });
const ok = (message) => console.log(`ok   ${message}`);

try {
    step = 'sign in';
    await page.goto(`${base}/auth/login`);
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('form button[type="submit"]').first().click();
    await page.waitForURL((url) => !url.pathname.startsWith('/auth') && !url.pathname.startsWith('/onboard'));
    ok('signed in to the console');

    step = 'profile list';
    await page.goto(`${base}/enhanced-pricing/profiles`);
    await page.getByText('Pricing profiles').first().waitFor();
    await page.getByText('CI example').first().waitFor();
    await shot('01-profiles');
    ok('the Pricing page lists the saved profile');

    step = 'profile';
    await page.goto(`${base}/enhanced-pricing/profiles/ci-example`);
    await page.getByText('CI example').first().waitFor();
    await page.getByText('Version 1 of 1').first().waitFor();
    await page.getByText('Scheduling timezone').first().waitFor();
    await shot('02-profile');
    ok('the profile opens on its first version');

    step = 'quote preview';
    await page.goto(`${base}/enhanced-pricing/preview`);
    const form = page.locator('[data-test-quote-preview]');
    await form.waitFor();
    await form.locator('[data-test-service-rate]').selectOption('distance');
    await form.locator('[data-test-pickup-date]').fill('2027-03-03');
    await form.locator('[data-test-pickup-time]').fill('10:00');
    await form.locator('[data-test-timezone]').fill('UTC');
    await form.locator('[data-test-timezone]').dispatchEvent('change');
    await form.locator('[data-test-distance]').fill('7');
    await form.locator('[data-test-entity="0"] [data-test-weight]').fill('42');
    await form.locator('[data-test-price]').click();
    const total = page.locator('[data-test-quote] [data-test-total]');
    await total.waitFor();
    await shot('03-preview');
    const totalText = (await total.innerText()).trim();
    if (!totalText.includes('40.33')) {
        problems.push(`the preview total is "${totalText}", expected 40.33`);
    } else {
        ok(`the quote preview prices the worked example: ${totalText}`);
    }
    const sent = previewBodies.length ? JSON.parse(previewBodies.at(-1)) : {};
    if (!String(sent.pickup_at ?? '').startsWith('2027-03-03T10:00') || sent.timezone !== 'UTC' || sent.distance_meters !== 7000) {
        problems.push(`the preview sent ${JSON.stringify(sent)}`);
    } else {
        ok('the preview sent the pickup as typed, the timezone and 7000 m');
    }
} catch (error) {
    problems.push(`${step}: ${error.message.split('\n')[0]}`);
    await shot(`failed-${step.replace(/\W+/g, '-')}`).catch(() => {});
} finally {
    writeFileSync(`${out}/browser-console.log`, log.join('\n') + '\n');
    await browser.close();
}

if (problems.length) {
    for (const problem of problems) {
        console.log(`FAIL: ${problem}`);
    }
    process.exit(1);
}
console.log('Console checks passed.');
