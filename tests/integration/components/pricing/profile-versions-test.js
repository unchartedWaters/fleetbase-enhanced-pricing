import { module, test } from 'qunit';
import { render, click } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';
import { setupEngineRenderingTest, fixture } from 'dummy/tests/helpers/engine';

module('Integration | Component | pricing/profile-versions', (hooks) => {
    setupEngineRenderingTest(hooks);

    test('every version is listed newest first, the current one marked', async function (assert) {
        this.profile = fixture('profile');
        this.viewed = [];
        this.view = (number) => this.viewed.push(number);

        await render(hbs`<Pricing::ProfileVersions @profile={{this.profile}} @onView={{this.view}} />`);

        assert.dom('[data-test-version-row]').exists({ count: 2 });
        assert.dom('[data-test-version-row="2"]').includesText('Current');
        assert.dom('[data-test-version-row="2"]').includesText('User a');
        assert.dom('[data-test-version-row="1"]').doesNotIncludeText('Current');

        await click('[data-test-version-row="1"] [data-test-view]');

        assert.deepEqual(this.viewed, [1]);
    });
});
