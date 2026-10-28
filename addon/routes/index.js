import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';
import { route } from '../utils/route-names';

export default class IndexRoute extends Route {
    @service hostRouter;

    beforeModel() {
        return this.hostRouter.transitionTo(route('profiles.index'));
    }
}
