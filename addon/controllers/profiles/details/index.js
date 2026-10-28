import Controller from '@ember/controller';

export default class ProfilesDetailsIndexController extends Controller {
    queryParams = ['version'];
    version = null;

    get isOld() {
        return this.model.profile.version.number !== this.model.profile.current_version;
    }
}
