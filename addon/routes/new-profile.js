import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { confirmLeaving } from '../utils/unsaved';
import { newItem } from '../utils/profile-form';

export default class NewProfileRoute extends Route {
    @service pricingApi;
    @service intl;

    async model() {
        const form = await this.pricingApi.form();
        const definition = newItem(form, 'profile');
        for (const section of form.sections) {
            if (section.list) {
                definition[section.key] = [];
            } else if (section.map) {
                definition[section.key] = {};
            }
        }

        return { form, definition };
    }

    @action willTransition(transition) {
        confirmLeaving(this.controller, transition, this.intl);
    }
}
