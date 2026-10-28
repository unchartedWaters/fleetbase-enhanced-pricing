import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { route } from '../utils/route-names';

export default class NewProfileController extends Controller {
    @service pricingApi;
    @service hostRouter;
    @service notifications;
    @service intl;

    dirty = false;

    @action setDirty(dirty) {
        this.dirty = dirty;
    }

    @action async save(payload) {
        const created = await this.pricingApi.post('admin/profiles', payload);
        this.dirty = false;
        this.notifications.success(this.intl.t('enhanced-pricing.console.editor.created', { name: created.name }));

        return this.hostRouter.transitionTo(route('profiles.details.index'), created.key);
    }

    @action cancel() {
        return this.hostRouter.transitionTo(route('profiles.index'));
    }
}
