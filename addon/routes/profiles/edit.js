import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { confirmLeaving } from '../../utils/unsaved';

export default class ProfilesEditRoute extends Route {
    @service pricingApi;
    @service intl;

    async model({ key }) {
        const [profile, form] = await Promise.all([this.pricingApi.get(`admin/profiles/${this.pricingApi.segment(key)}`), this.pricingApi.form()]);

        return { key, profile, form };
    }

    @action willTransition(transition) {
        confirmLeaving(this.controller, transition, this.intl);
    }
}
