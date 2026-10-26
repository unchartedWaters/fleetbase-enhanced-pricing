import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

// A profile's overview: any version, read by section. Without ?version, the current one.
export default class ProfilesDetailsIndexRoute extends Route {
    @service pricingApi;

    queryParams = { version: { refreshModel: true } };

    async model({ version }) {
        const parent = this.modelFor('profiles.details');
        if (!version || Number(version) === parent.profile.current_version) {
            return parent;
        }
        const profile = await this.pricingApi.get(`admin/profiles/${this.pricingApi.segment(parent.key)}`, { version });

        return { ...parent, profile };
    }
}
