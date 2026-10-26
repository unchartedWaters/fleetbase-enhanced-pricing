import buildRoutes from 'ember-engines/routes';

// The admin page (plan: "Console changes in the order form"): profiles with their versions,
// subtotal fees and customers, the profile editor, one customer's overrides and cards, and the
// quote preview. A new profile has its own path, so no profile key can hide it.
export default buildRoutes(function () {
    this.route('new-profile');
    this.route('profiles', function () {
        this.route('details', { path: '/:key' }, function () {
            this.route('versions');
            this.route('subtotal-fees');
            this.route('customers');
        });
        this.route('edit', { path: '/:key/edit' });
    });
    this.route('customers', function () {
        this.route('details', { path: '/:uuid' });
    });
    this.route('preview');
});
