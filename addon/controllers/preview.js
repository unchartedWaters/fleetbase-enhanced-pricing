import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class PreviewController extends Controller {
    @service pricingApi;
    @service hostRouter;

    queryParams = ['profile', 'customer'];
    profile = null;
    customer = null;

    @action chooseProfile(event) {
        return this.hostRouter.transitionTo({ queryParams: { profile: event.target.value, customer: null } });
    }

    @action loadCustomer(uuid) {
        return this.pricingApi.get(`admin/rate-cards/${this.pricingApi.segment(uuid)}`);
    }

    @action price(input) {
        return this.pricingApi.post('admin/preview', input);
    }
}
