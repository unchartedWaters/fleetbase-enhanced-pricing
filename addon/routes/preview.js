import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

// The quote preview for one profile (?profile, the first one by default), with its customers to
// choose from: the first page of them, and the one asked for (?customer) when it is further on.
export default class PreviewRoute extends Route {
    @service pricingApi;

    queryParams = { profile: { refreshModel: true }, customer: { refreshModel: true } };

    async model({ profile, customer }) {
        const profiles = await this.pricingApi.get('admin/profiles');
        const key = profiles.some((item) => item.key === profile) ? profile : profiles[0]?.key;
        if (!key) {
            return { profiles, key: null };
        }
        const segment = this.pricingApi.segment(key);
        const [shown, page] = await Promise.all([this.pricingApi.get(`admin/profiles/${segment}`), this.pricingApi.get('admin/rate-cards', { profile: key, page: 1 })]);
        const customers = [...page.customers];
        if (customer && !customers.some((item) => item.uuid === customer)) {
            const details = await this.pricingApi.get(`admin/rate-cards/${this.pricingApi.segment(customer)}`);
            if (details.profile.key === key) {
                customers.unshift({ uuid: details.uuid, name: details.name });
            }
        }

        return { profiles, key, profile: shown, customers, customer: customers.some((item) => item.uuid === customer) ? customer : null, more: page.total > customers.length };
    }
}
