import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { route } from '../../utils/route-names';

export default class ProfilesDetailsController extends Controller {
    @service hostRouter;

    @action edit() {
        return this.hostRouter.transitionTo(route('profiles.edit'), this.model.key);
    }

    @action preview() {
        return this.hostRouter.transitionTo(route('preview'), { queryParams: { profile: this.model.key, customer: null } });
    }
}
