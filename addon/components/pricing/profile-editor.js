import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { problemsWithin, withoutProblemsWithin } from '../../utils/profile-form';

// The profile editor: every element of a profile in forms drawn from the server's field spec
// (@form), each save a new version built on the one opened (@builtOn). @onSave takes
// { definition, built_on, accept_stale } (or { key, name, definition } for a new profile) and
// returns a promise; a refusal is the server's body:
// - invalid: every problem is listed and its field marked;
// - stale: the stored entries that no longer fit are named, and saving anyway sends exactly
//   the ones shown (decision 10), built on the same version as the save refused;
// - outdated: the newer version is named, and the person opens it (losing the draft) or replaces
//   it (sent without built_on).
export default class PricingProfileEditorComponent extends Component {
    @tracked draft;
    @tracked section = 'general';
    @tracked problems = [];
    @tracked stale = null;
    @tracked outdated = null;
    @tracked failure = null;
    @tracked confirming = null;
    @tracked saving = false;
    @tracked inputErrors = new Set();
    @tracked key = '';
    @tracked name = '';
    dirty = false;
    lastBuiltOn = null;

    constructor() {
        super(...arguments);
        this.draft = JSON.parse(JSON.stringify(this.args.definition ?? {}));
    }

    // The pages of the editor: a section, or the sections that share a group drawn as one page.
    get sections() {
        const sections = this.args.form?.sections ?? [];
        const listed = new Set(sections.filter((section) => section.list || section.map).map((section) => section.key));
        const pages = [];
        for (const section of sections) {
            const key = section.group ?? section.key;
            const page = pages.find((item) => item.key === key);
            if (page) {
                page.members.push(section);
            } else {
                pages.push({ ...section, key, grouped: Boolean(section.group), members: [section] });
            }
        }

        return pages.map((page) => {
            const count =
                page.key === 'general'
                    ? this.problems.filter((problem) => problem.path && !listed.has(String(problem.path).split('.')[0])).length
                    : page.members.reduce((sum, member) => sum + problemsWithin(this.problems, member.key).length, 0);

            return { ...page, problems: count, active: page.key === this.section };
        });
    }

    get current() {
        return this.sections.find((section) => section.key === this.section) ?? this.sections[0];
    }

    // Every section's key: the profile fields that have a page of their own, not general fields.
    get sectionKeys() {
        return (this.args.form?.sections ?? []).map((section) => section.key);
    }

    get env() {
        return {
            form: this.args.form,
            definition: this.draft,
            problems: this.problems,
            currency: this.draft.currency,
            readOnly: this.args.readOnly,
            isNew: this.args.isNew,
            onError: this.inputError,
            clearProblems: this.clearProblems,
        };
    }

    get cannotSave() {
        return this.saving || this.inputErrors.size > 0 || (this.args.isNew && (this.key.trim() === '' || this.name.trim() === ''));
    }

    change(next) {
        this.draft = next;
        this.markDirty();
    }

    markDirty() {
        if (!this.dirty) {
            this.dirty = true;
            this.args.onDirty?.(true);
        }
    }

    @action setSection(key) {
        this.section = key;
    }

    @action setGeneral(next) {
        this.change(next);
    }

    @action setSectionValue(key, value) {
        this.change({ ...this.draft, [key]: value });
    }

    @action inputError(path, failing) {
        const errors = new Set(this.inputErrors);
        if (failing) {
            errors.add(path);
        } else {
            errors.delete(path);
        }
        this.inputErrors = errors;
    }

    @action clearProblems(prefix) {
        this.problems = withoutProblemsWithin(this.problems, prefix);
    }

    @action selectProblem(path) {
        const first = String(path ?? '').split('.')[0];
        const section = (this.args.form?.sections ?? []).find((item) => item.key === first);
        this.section = section ? (section.group ?? section.key) : 'general';
    }

    @action typeKey(event) {
        this.key = event.target.value;
        this.markDirty();
    }

    @action typeName(event) {
        this.name = event.target.value;
        this.markDirty();
    }

    @action confirm(what) {
        this.confirming = what;
    }

    @action save() {
        return this.send({ builtOn: this.args.builtOn ?? null, acceptStale: [] });
    }

    @action saveStale() {
        return this.send({ builtOn: this.lastBuiltOn, acceptStale: this.stale.map((entry) => entry.uuid) });
    }

    @action replace() {
        return this.send({ builtOn: null, acceptStale: [] });
    }

    @action openNewer() {
        this.confirming = null;
        this.args.onOpenNewer?.();
    }

    async send({ builtOn, acceptStale }) {
        const payload = this.args.isNew ? { key: this.key.trim(), name: this.name.trim(), definition: this.draft } : { definition: this.draft, built_on: builtOn, accept_stale: acceptStale };
        this.lastBuiltOn = builtOn;
        this.saving = true;
        this.failure = null;
        this.confirming = null;
        try {
            await this.args.onSave(payload);
            this.problems = [];
            this.stale = null;
            this.outdated = null;
            this.dirty = false;
            this.args.onDirty?.(false);
        } catch (refusal) {
            this.refused(refusal ?? {});
        } finally {
            if (!this.isDestroying && !this.isDestroyed) {
                this.saving = false;
            }
        }
    }

    refused(refusal) {
        this.stale = null;
        this.outdated = null;
        switch (refusal.refused) {
            case 'invalid':
                this.problems = refusal.problems ?? [];
                break;
            case 'stale':
                this.problems = [];
                this.stale = refusal.stale ?? [];
                break;
            case 'outdated':
                this.problems = [];
                this.outdated = refusal.newer ?? {};
                break;
            default:
                this.failure = refusal;
        }
    }
}
