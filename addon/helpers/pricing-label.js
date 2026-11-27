import { helper } from '@ember/component/helper';

// A profile field's name as a label: "applies_to" is "Applies to". The names are the profile
// format's own (docs/profile-format.md), so they are shown as the format spells them.
export default helper(function pricingLabel([name, kind]) {
    // The format calls it `key`; people see it as the item's code. A fee's own `code` is the
    // billing code of its lines, so it is named that, apart from the item's code.
    let text = name === 'key' ? 'code' : String(name ?? '').replace(/_/g, ' ');
    if (name === 'code' && kind && kind !== 'product') {
        text = 'billing code';
    }

    return text.charAt(0).toUpperCase() + text.slice(1);
});
