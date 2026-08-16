import { Controller } from '@hotwired/stimulus';
import { Combobox } from '../combobox.js';
import { element } from '../dom.js';

/*
 * The box above the chart that says what it is drawn as. It is the repository box next to it, over a
 * list that is already in the page: a release was measured in fifty-four numbers, and nothing has to be
 * asked of the server to know their names - so this box searches what it was rendered with.
 *
 * That is also why a row says more than a name here. Nobody knows what `Logical lines outside classes
 * and functions` is before reading what it counts, so the menu carries the sentence the catalog holds
 * for every metric, under the section phploc prints it in.
 *
 * The select behind it is the state, not the widget - see repository_picker_controller.js. What is
 * picked shows up as a tab above the chart rather than as a chip in here: the metrics of a chart are
 * what it can be read in, and that list is the tab row, not a second copy of it next to the field.
 * So this box only ever adds - a tab is where a metric is switched to and taken out again.
 */
export default class extends Controller {
    static targets = ['select', 'input', 'menu'];

    connect() {
        this.combobox = new Combobox(this.inputTarget, this.menuTarget, (index) => this.pick(index));
        // turbo caches the page as it was left, so a box left open would come back open
        this.combobox.close();
        this.inputTarget.value = '';
    }

    disconnect() {
        this.combobox.close();
    }

    /** The whole box is one field, so clicking it aims for the only thing in it. */
    focus() {
        this.inputTarget.focus();
    }

    /**
     * Everything that was measured and is not drawn yet. What is typed is matched against the name, the
     * section and the sentence explaining the metric - `static` finds the static methods and the calls
     * on them, `branch` finds the complexities, which are the words their explanations are written in.
     */
    query() {
        const search = this.inputTarget.value.trim().toLowerCase();

        this.options = [...this.selectTarget.options].filter(
            (option) => !option.selected && this.haystack(option).includes(search),
        );

        this.combobox.show(
            this.options.map((option) => {
                const row = element('li', 'combobox__option');

                row.append(
                    element('span', 'combobox__name', option.text),
                    element('span', 'combobox__meta', option.dataset.group),
                    element('span', 'combobox__description', option.dataset.about),
                );

                return row;
            }),
            this.nothing('' !== search),
        );
    }

    haystack(option) {
        return `${option.text} ${option.dataset.group} ${option.dataset.about} ${option.value}`.toLowerCase();
    }

    nothing(searching) {
        return searching
            ? 'Nothing that was measured goes by that name.'
            : 'Every number of the measurement is already in this chart.';
    }

    navigate(event) {
        this.combobox.navigate(event);
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
        // the position of an option is the position of its tab, so a pick goes to the end of both
        this.selectTarget.append(option);
        // one pick is rarely the only one, so the box stays open on what is left of the measurement
        this.inputTarget.value = '';
        this.commit();
    }

    commit() {
        this.selectTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /** What the select says, whoever wrote to it - the box only ever shows what is not in the chart. */
    refresh() {
        // the menu is what is not in the chart, so taking a metric out puts it back on
        if (!this.menuTarget.hidden) {
            this.query();
        }
    }
}
