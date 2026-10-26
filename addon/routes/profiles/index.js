import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ProfilesIndexRoute extends Route {
    @service pricingApi;

    model() {
        return this.pricingApi.get('admin/profiles');
    }
}
