import { MenuItem } from '@fleetbase/ember-core/contracts';

// The console's header item for the admin page. Its id is the extension's name, because the
// header shows an item only to users who can "{id} see extension" (ember-ui smart-nav-menu), and
// the extension's permissions are named enhanced-pricing (server/src/Auth/Schemas). The order
// form fields and the order details panel arrive with the order inputs (plan, step 7).
export default {
    setupExtension(app, universe) {
        const menuService = universe.getService('universe/menu-service');

        menuService.registerHeaderMenuItem(
            new MenuItem({
                id: 'enhanced-pricing',
                slug: 'enhanced-pricing',
                title: 'Pricing',
                route: 'console.enhanced-pricing',
                icon: 'tags',
                priority: 5,
                description: 'Pricing profiles, customer rate cards and overrides, subtotal fees and quote previews.',
                shortcuts: [
                    { id: 'enhanced-pricing-profiles', title: 'Pricing profiles', icon: 'tags', route: 'console.enhanced-pricing.profiles' },
                    { id: 'enhanced-pricing-preview', title: 'Quote preview', icon: 'calculator', route: 'console.enhanced-pricing.preview' },
                ],
            })
        );
    },
};
