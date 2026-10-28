import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { route } from '../../../utils/route-names';

export default class ProfilesDetailsVersionsController extends Controller {
    @service hostRouter;

    @action view(number) {
        return this.hostRouter.transitionTo(route('profiles.details.index'), this.model.key, { queryParams: { version: number } });
    }
}
