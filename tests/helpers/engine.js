import { setupRenderingTest } from 'dummy/tests/helpers';
import fixtures from 'dummy/tests/helpers/console-fixtures';

// The engine's components resolve inside the engine. A rendering test runs in the dummy app, so
// it registers the extension's own components, helpers and services there, as the engine would.
// The engine's namespace also holds the components its addons re-export; those are left alone.
const PREFIX = '@unchartedwaters/enhanced-pricing-engine/';
const TYPES = { 'components/pricing/': 'component:pricing/', 'helpers/pricing-': 'helper:pricing-', 'services/pricing-': 'service:pricing-' };

export function setupEngineRenderingTest(hooks) {
    setupRenderingTest(hooks);

    hooks.beforeEach(function () {
        for (const name of Object.keys(window.requirejs.entries)) {
            for (const [folder, type] of Object.entries(TYPES)) {
                const start = `${PREFIX}${folder}`;
                if (name.startsWith(start)) {
                    this.owner.register(`${type}${name.slice(start.length)}`, window.require(name).default);
                }
            }
        }
    });
}

// A deep copy, so a test can change a fixture without changing it for the next test.
export function fixture(name) {
    return JSON.parse(JSON.stringify(fixtures[name]));
}
