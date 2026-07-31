import { Controller } from '@hotwired/stimulus';
import { Combobox, element } from '../combobox.js';

/*
 * The box above the chart: which repositories it draws. It is the start page's combobox with more than
 * one answer - a chip per line, in the order the chart draws them, and a menu of everything the report
 * carries that is not in it yet.
 *
 * The select behind it is the state, not the widget: picking writes to it and dispatches its `change`,
 * so the chart is redrawn by one event whether a chip was added, removed or arrived with the page.
 */
export default class extends Controller {
    static targets = ['select', 'chips', 'input', 'menu'];
    static values = { limit: Number };

    connect() {
        this.combobox = new Combobox(this.inputTarget, this.menuTarget, (index) => this.pick(index));
        // turbo caches the page as it was left, so a box left open would come back open - a page
        // starts on its chart, not halfway through picking one
        this.combobox.close();
        this.inputTarget.value = '';
        this.renderChips();
    }

    disconnect() {
        this.combobox.close();
    }

    /** The whole box is one field, so clicking next to the chips aims for the only thing to type in. */
    focus() {
        this.inputTarget.focus();
    }

    /**
     * Everything the report carries that is not in the chart yet - the chips above are where a
     * repository is taken out again, so the menu never repeats one.
     */
    query() {
        const search = this.inputTarget.value.trim().toLowerCase();
        const full = this.selected().length >= this.limitValue;

        this.options = full
            ? []
            : [...this.selectTarget.options].filter(
                  (option) => !option.selected && option.value.toLowerCase().includes(search),
              );

        this.combobox.show(
            this.options.map((option) => {
                const row = element('li', 'combobox__option');

                row.append(element('span', 'combobox__name', option.value));

                return row;
            }),
            this.nothing(full, '' !== search),
        );
    }

    /**
     * An empty menu is three different answers, and saying the wrong one reads like a box that stopped
     * working: the chart is full, the report is in it already, or nothing goes by that name.
     */
    nothing(full, searching) {
        if (full) {
            return `The chart draws ${this.limitValue} lines at a time - remove one to add another.`;
        }

        return searching
            ? 'Nothing in the report matches that.'
            : 'Everything the report carries is already in this chart.';
    }

    navigate(event) {
        if (this.combobox.navigate(event)) {
            return;
        }

        // an empty field backspaces into the chips, the way a field full of them is emptied again
        if ('Backspace' === event.key && '' === this.inputTarget.value) {
            const chips = this.selected();

            if (chips.length > 0) {
                this.deselect(chips[chips.length - 1]);
            }
        }
    }

    close() {
        this.combobox.close();
    }

    pick(index) {
        const option = this.options[index];

        if (!option) {
            return;
        }

        option.selected = true;
        option.setAttribute('selected', 'selected');
        // the position of a chip is the colour of its line, so a pick goes to the end of both
        this.selectTarget.append(option);
        // one pick is rarely the only one, so the box stays open on what is left of the report
        this.inputTarget.value = '';
        this.commit();
    }

    remove(event) {
        const option = [...this.selectTarget.options].find((entry) => entry.value === event.params.name);

        if (option) {
            this.deselect(option);
        }
    }

    deselect(option) {
        option.selected = false;
        option.removeAttribute('selected');
        this.commit();
    }

    commit() {
        this.renderChips();

        // the menu is what is not in the chart, so taking a repository out puts it back on
        if (!this.menuTarget.hidden) {
            this.query();
        }

        this.selectTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    renderChips() {
        this.chipsTarget.replaceChildren(
            ...this.selected().map((option) => {
                const chip = element('li', 'combobox__chip');
                const remove = element('button', 'combobox__chip-remove', '×');

                remove.type = 'button';
                remove.dataset.action = 'repository-picker#remove';
                remove.dataset.repositoryPickerNameParam = option.value;
                remove.setAttribute('aria-label', `Remove ${option.value} from the chart`);

                chip.append(element('span', 'combobox__swatch'), element('span', null, option.value), remove);

                return chip;
            }),
        );
    }

    selected() {
        return [...this.selectTarget.selectedOptions];
    }
}
