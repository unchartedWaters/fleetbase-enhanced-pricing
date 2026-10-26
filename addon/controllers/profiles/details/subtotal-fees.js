import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class ProfilesDetailsSubtotalFeesController extends Controller {
    @service pricingApi;
    @service hostRouter;

    @action async schedule(change) {
        await this.pricingApi.post('admin/dated-changes', { profile: this.model.key, ...change });

        return this.hostRouter.refresh();
    }

    @action async cancel(uuid) {
        await this.pricingApi.delete(`admin/dated-changes/${this.pricingApi.segment(uuid)}`);

        return this.hostRouter.refresh();
    }
}
