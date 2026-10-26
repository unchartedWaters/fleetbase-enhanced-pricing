import Helper from '@ember/component/helper';
import { inject as service } from '@ember/service';

// A stored problem or pricing failure in words. Its key is under enhanced-pricing (profile,
// pricing, store, admin); an {expected} detail is a type code and a {kind} detail a kind code,
// each shown with its own text (translations/en-us.yaml).
export default class PricingMessageHelper extends Helper {
    @service intl;

    compute([message]) {
        if (!message || typeof message.key !== 'string') {
            return '';
        }
        const params = { ...message.params };
        for (const [name, folder] of [
            ['expected', 'types'],
            ['kind', 'kinds'],
        ]) {
            const key = `enhanced-pricing.${folder}.${params[name]}`;
            if (typeof params[name] === 'string' && this.intl.exists(key)) {
                params[name] = this.intl.t(key);
            }
        }
        for (const [name, value] of Object.entries(params)) {
            if (value !== null && typeof value === 'object') {
                params[name] = JSON.stringify(value);
            }
        }

        return this.intl.exists(message.key) ? this.intl.t(message.key, params) : message.key;
    }
}
