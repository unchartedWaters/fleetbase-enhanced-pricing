import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class CustomersDetailsRoute extends Route {
    @service pricingApi;

    model({ uuid }) {
        return this.pricingApi.get(`admin/rate-cards/${this.pricingApi.segment(uuid)}`);
    }
}
