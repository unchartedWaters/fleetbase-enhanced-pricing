import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ProfilesIndexRoute extends Route {
    @service pricingApi;

    async model() {
        const { profiles } = await this.pricingApi.get('admin/profiles');

        return profiles;
    }
}
