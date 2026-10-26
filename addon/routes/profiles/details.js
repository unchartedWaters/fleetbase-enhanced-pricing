import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ProfilesDetailsRoute extends Route {
    @service pricingApi;

    async model({ key }) {
        const [profile, form] = await Promise.all([this.pricingApi.get(`admin/profiles/${this.pricingApi.segment(key)}`), this.pricingApi.form()]);

        return { key, profile, form };
    }
}
