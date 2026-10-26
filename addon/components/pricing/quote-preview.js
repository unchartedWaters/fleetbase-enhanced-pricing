import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { zonedToUtc, distanceToMeters } from '../../utils/preview';

// The quote preview: a sample order priced by the real engine (POST admin/preview), for a
// customer with its overrides, rate card row and dated changes, or for the profile's own values.
// Nothing is saved. The pickup is typed in the order's timezone, the profile's scheduling
// timezone unless changed, and the distance in the profile's unit.
const WEIGHT_UNITS = ['kg', 'g', 'lb', 'oz'];
const LENGTH_UNITS = ['cm', 'mm', 'm', 'in', 'ft', 'yd'];

function blankEntity() {
    return { type: '', weight: '', weight_unit: 'kg', length: '', width: '', height: '', dimensions_unit: 'cm', attributes: {} };
}

function orNull(text) {
    return text === '' || text === undefined ? null : text;
}

export default class PricingQuotePreviewComponent extends Component {
    weightUnits = WEIGHT_UNITS;
    lengthUnits = LENGTH_UNITS;

    @tracked customer = '';
    @tracked customerDetails = null;
    @tracked row = '';
    @tracked serviceRate = '';
    @tracked date = '';
    @tracked time = '';
    @tracked timezone;
    @tracked distance = '';
    @tracked attributes = {};
    @tracked entities = [blankEntity()];
    @tracked charges = [];
    @tracked chargeErrors = new Set();
    @tracked result = null;
    @tracked refusal = null;
    @tracked pricing = false;

    constructor() {
        super(...arguments);
        this.timezone = this.args.definition?.scheduling_timezone ?? 'UTC';
        if (this.args.initialCustomer) {
            this.chooseCustomer({ target: { value: this.args.initialCustomer } });
        }
    }

    get definition() {
        return this.args.definition ?? {};
    }

    get currency() {
        return this.args.profile?.currency ?? this.definition.currency;
    }

    get distanceUnit() {
        return this.args.profile?.distance_unit ?? this.definition.distance_unit ?? 'km';
    }

    get serviceRates() {
        return (this.definition.service_rates ?? []).map((rate) => ({ value: rate.key, label: rate.name ? `${rate.name} (${rate.key})` : rate.key, rate }));
    }

    get chosenRate() {
        return (this.definition.service_rates ?? []).find((rate) => rate.key === this.serviceRate) ?? null;
    }

    get needsDistance() {
        return this.chosenRate?.method === 'per_meter';
    }

    get rows() {
        const table = this.chosenRate?.rate_table;
        if (!this.customerDetails || !table) {
            return [];
        }

        return this.customerDetails.cards.flatMap((card) =>
            card.rows.filter((row) => row.table_key === table).map((row) => ({ value: row.uuid, label: `${card.label} · ${row.key_values.join(' · ')}`, problems: row.problems }))
        );
    }

    get showRows() {
        return this.customer !== '' && Boolean(this.chosenRate?.rate_table);
    }

    get orderAttributes() {
        return (this.definition.attributes ?? []).filter((attribute) => attribute.scope !== 'entity');
    }

    get entityAttributes() {
        return (this.definition.attributes ?? []).filter((attribute) => attribute.scope === 'entity');
    }

    get entityTypes() {
        return (this.definition.entity_types ?? []).map((type) => ({ value: type.key, label: type.name ? `${type.name} (${type.key})` : type.key }));
    }

    get offeredCharges() {
        return (this.definition.manual_charges ?? []).filter((charge) => charge.offered !== false);
    }

    get distanceResult() {
        return distanceToMeters(this.distance, this.distanceUnit);
    }

    get cannotPrice() {
        return this.pricing || this.serviceRate === '' || (this.needsDistance && Boolean(this.distanceResult.error)) || this.chargeErrors.size > 0;
    }

    get entityRows() {
        return this.entities.map((entity, index) => ({ entity, index }));
    }

    get chargeRows() {
        return this.charges.map((charge, index) => ({
            charge,
            index,
            spec: this.offeredCharges.find((offered) => offered.key === charge.key),
        }));
    }

    @action async chooseCustomer(event) {
        this.customer = event.target.value;
        this.customerDetails = null;
        this.row = '';
        if (this.customer !== '' && this.args.onLoadCustomer) {
            const chosen = this.customer;
            try {
                const details = await this.args.onLoadCustomer(chosen);
                if (this.customer === chosen) {
                    this.customerDetails = details;
                }
            } catch (refusal) {
                this.refusal = refusal ?? {};
            }
        }
    }

    @action chooseRow(event) {
        this.row = event.target.value;
    }

    @action chooseServiceRate(event) {
        this.serviceRate = event.target.value;
        this.row = '';
    }

    @action setText(name, event) {
        this[name] = event.target.value;
    }

    @action setAttribute(attribute, event) {
        this.attributes = { ...this.attributes, [attribute.key]: this.attributeValue(attribute, event) };
    }

    attributeValue(attribute, event) {
        if (attribute.type === 'boolean') {
            return event.target.checked;
        }
        const text = event.target.value;

        return text === '' ? null : text;
    }

    changeEntity(index, changes) {
        this.entities = this.entities.map((entity, position) => (position === index ? { ...entity, ...changes } : entity));
    }

    @action setEntity(index, name, event) {
        this.changeEntity(index, { [name]: event.target.value });
    }

    @action setEntityAttribute(index, attribute, event) {
        const entity = this.entities[index];
        this.changeEntity(index, { attributes: { ...entity.attributes, [attribute.key]: this.attributeValue(attribute, event) } });
    }

    @action addEntity() {
        this.entities = [...this.entities, blankEntity()];
    }

    @action removeEntity(index) {
        this.entities = this.entities.filter((_, position) => position !== index);
    }

    @action addCharge(event) {
        const key = event.target.value;
        if (key !== '') {
            this.charges = [...this.charges, { key, quantity: '', amount: null }];
            event.target.value = '';
        }
    }

    @action setChargeQuantity(index, event) {
        this.charges = this.charges.map((charge, position) => (position === index ? { ...charge, quantity: event.target.value } : charge));
    }

    @action setChargeAmount(index, amount) {
        this.charges = this.charges.map((charge, position) => (position === index ? { ...charge, amount } : charge));
    }

    @action chargeError(index, failing) {
        const errors = new Set(this.chargeErrors);
        if (failing) {
            errors.add(index);
        } else {
            errors.delete(index);
        }
        this.chargeErrors = errors;
    }

    @action removeCharge(index) {
        this.charges = this.charges.filter((_, position) => position !== index);
        this.chargeErrors = new Set();
    }

    request() {
        const input = this.customer === '' ? { profile: this.args.profile?.key } : { customer: this.customer };
        if (this.customer !== '' && this.row !== '') {
            input.row = this.row;
        }
        input.service_rate = this.serviceRate;
        input.pickup_at = zonedToUtc(this.date, this.time, this.timezone);
        input.timezone = this.timezone;
        if (this.needsDistance && this.distanceResult.value !== null) {
            input.distance_meters = this.distanceResult.value;
        }
        input.attributes = Object.fromEntries(Object.entries(this.attributes).filter(([, value]) => value !== null));
        input.entities = this.entities.map((entity) => ({
            type: orNull(entity.type),
            weight: orNull(entity.weight),
            weight_unit: entity.weight_unit,
            length: orNull(entity.length),
            width: orNull(entity.width),
            height: orNull(entity.height),
            dimensions_unit: entity.dimensions_unit,
            attributes: Object.fromEntries(Object.entries(entity.attributes).filter(([, value]) => value !== null)),
        }));
        input.manual_charges = this.charges.map((charge) => ({ key: charge.key, quantity: orNull(charge.quantity), amount: charge.amount }));

        return input;
    }

    @action submit(event) {
        event.preventDefault();

        return this.price();
    }

    @action async price() {
        this.pricing = true;
        this.refusal = null;
        this.result = null;
        try {
            this.result = await this.args.onPrice(this.request());
        } catch (refusal) {
            this.refusal = refusal ?? {};
        } finally {
            if (!this.isDestroying && !this.isDestroyed) {
                this.pricing = false;
            }
        }
    }
}
