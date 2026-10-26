import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ProfilesDetailsCustomersRoute extends Route {
    @service pricingApi;

    queryParams = { page: { refreshModel: true } };

    async model({ page }) {
        const parent = this.modelFor('profiles.details');
        const customers = await this.pricingApi.get('admin/rate-cards', { profile: parent.key, page: page ?? 1 });

        return { ...parent, customers };
    }
}
