import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { route } from '../../utils/route-names';

// Saves a new version built on the one opened. Opening the newer version reloads the editor and
// drops the draft, which the editor has confirmed.
export default class ProfilesEditController extends Controller {
    @service pricingApi;
    @service hostRouter;
    @service notifications;
    @service intl;

    dirty = false;

    @action setDirty(dirty) {
        this.dirty = dirty;
    }

    @action async save(payload) {
        const saved = await this.pricingApi.put(`admin/profiles/${this.pricingApi.segment(this.model.key)}`, payload);
        this.dirty = false;
        this.notifications.success(this.intl.t('enhanced-pricing.console.editor.saved', { number: saved.current_version }));

        return this.hostRouter.transitionTo(route('profiles.details.index'), this.model.key, { queryParams: { version: null } });
    }

    @action openNewer() {
        this.dirty = false;

        return this.hostRouter.refresh();
    }

    @action cancel() {
        return this.hostRouter.transitionTo(route('profiles.details.index'), this.model.key);
    }
}
