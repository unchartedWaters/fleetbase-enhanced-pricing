// The editor's routes ask before leaving a draft with unsaved changes (design, Editor).
export function confirmLeaving(controller, transition, intl) {
    if (controller.dirty && !window.confirm(intl.t('enhanced-pricing.console.editor.leave'))) {
        transition.abort();

        return;
    }
    controller.dirty = false;
}
