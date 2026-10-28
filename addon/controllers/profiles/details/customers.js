import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { route } from '../../../utils/route-names';

export default class ProfilesDetailsCustomersController extends Controller {
    @service hostRouter;

    queryParams = ['page'];
    page = 1;

    @action open(uuid) {
        return this.hostRouter.transitionTo(route('customers.details'), uuid);
    }

    @action goTo(page) {
        return this.hostRouter.transitionTo({ queryParams: { page } });
    }
}
