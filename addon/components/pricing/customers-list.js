import Component from '@glimmer/component';

// The customers of a profile, a page at a time, each with its override and card counts and
// whether it still fits the profile's current version.
export default class PricingCustomersListComponent extends Component {
    get page() {
        return this.args.page ?? { customers: [], total: 0, page: 1, per_page: 50 };
    }

    get from() {
        return this.page.total === 0 ? 0 : (this.page.page - 1) * this.page.per_page + 1;
    }

    get to() {
        return Math.min(this.page.page * this.page.per_page, this.page.total);
    }

    get hasPrevious() {
        return this.page.page > 1;
    }

    get hasNext() {
        return this.to < this.page.total;
    }

    get previous() {
        return this.page.page - 1;
    }

    get next() {
        return this.page.page + 1;
    }
}
