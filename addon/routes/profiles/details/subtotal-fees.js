import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ProfilesDetailsSubtotalFeesRoute extends Route {
    @service pricingApi;

    async model() {
        const parent = this.modelFor('profiles.details');
        const schedule = await this.pricingApi.get('admin/dated-changes', { profile: parent.key });

        return { ...parent, schedule };
    }
}
