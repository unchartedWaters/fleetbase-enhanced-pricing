import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { route } from '../utils/route-names';

export default class ApplicationController extends Controller {
    @service intl;

    get navigationItems() {
        return [
            {
                label: this.intl.t('enhanced-pricing.console.nav.profiles'),
                description: this.intl.t('enhanced-pricing.console.nav.profiles-help'),
                icon: 'tags',
                route: route('profiles.index'),
                keywords: ['pricing', 'rates', 'versions', 'customers', 'subtotal fees'],
            },
            {
                label: this.intl.t('enhanced-pricing.console.nav.preview'),
                description: this.intl.t('enhanced-pricing.console.nav.preview-help'),
                icon: 'calculator',
                route: route('preview'),
                keywords: ['quote', 'price', 'test'],
            },
        ];
    }
}
