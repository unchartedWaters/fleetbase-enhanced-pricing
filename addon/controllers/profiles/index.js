import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { route } from '../../utils/route-names';

export default class ProfilesIndexController extends Controller {
    @service hostRouter;

    @action open(key) {
        return this.hostRouter.transitionTo(route('profiles.details'), key);
    }

    @action create() {
        return this.hostRouter.transitionTo(route('new-profile'));
    }
}
