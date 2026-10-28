import Route from '@ember/routing/route';

export default class ProfilesDetailsVersionsRoute extends Route {
    model() {
        return this.modelFor('profiles.details');
    }
}
