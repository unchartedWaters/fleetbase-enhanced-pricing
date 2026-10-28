import Service, { inject as service } from '@ember/service';

// The console's calls to the extension's admin endpoints (server/src/routes.php). A refusal is
// the server's body, which carries `refused` (not-found, invalid, outdated, stale, large-change,
// forbidden); fetch's rawError keeps the body but not the status. Anything else, such as a
// network failure, becomes { refused: 'error', message }.
export const NAMESPACE = 'enhanced-pricing/int/v1';

const OPTIONS = { namespace: NAMESPACE, rawError: true };

function refusal(error) {
    if (error && typeof error === 'object' && typeof error.refused === 'string') {
        return error;
    }

    const message = typeof error === 'string' ? error : (error?.message ?? error?.errors?.[0] ?? error?.error ?? null);

    return { refused: 'error', message: typeof message === 'string' ? message : null };
}

export default class PricingApiService extends Service {
    @service fetch;

    #form = null;

    // The editor's field spec (GET admin/form) is the same for every profile, so it is asked
    // for once. A failed request is asked again next time.
    form() {
        if (this.#form === null) {
            this.#form = this.get('admin/form').catch((refusal) => {
                this.#form = null;

                return Promise.reject(refusal);
            });
        }

        return this.#form;
    }

    segment(value) {
        return encodeURIComponent(String(value));
    }

    get(path, query = {}) {
        return this.fetch.get(path, query, { ...OPTIONS }).catch((error) => Promise.reject(refusal(error)));
    }

    post(path, data = {}) {
        return this.fetch.post(path, data, { ...OPTIONS }).catch((error) => Promise.reject(refusal(error)));
    }

    put(path, data = {}) {
        return this.fetch.put(path, data, { ...OPTIONS }).catch((error) => Promise.reject(refusal(error)));
    }

    delete(path, data = {}) {
        return this.fetch.delete(path, data, { ...OPTIONS }).catch((error) => Promise.reject(refusal(error)));
    }
}
