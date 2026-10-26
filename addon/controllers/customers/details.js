import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { route } from '../../utils/route-names';

export default class CustomersDetailsController extends Controller {
    @service hostRouter;

    @action openProfile() {
        return this.hostRouter.transitionTo(route('profiles.details.customers'), this.model.profile.key);
    }

    @action preview() {
        return this.hostRouter.transitionTo(route('preview'), { queryParams: { profile: this.model.profile.key, customer: this.model.uuid } });
    }
}
