/*
 * The listbox that hangs under a field: the search box on the start page and the repository box above
 * the chart are the same control, so the part that is the same lives here - the rows, the menu it
 * opens, and the keyboard that walks it.
 *
 * What a row says and what picking one means stays with the box that built it: this only knows that a
 * row was chosen and by which of the two ways.
 */
export function element(tag, className, text) {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (undefined !== text) {
        node.textContent = text;
    }

    return node;
}

export class Combobox {
    /**
     * @param input the field the menu belongs to
     * @param menu the list it is drawn in, identified so a row can be pointed at
     * @param pick called with the position of the row that was chosen
     */
    constructor(input, menu, pick) {
        this.input = input;
        this.menu = menu;
        this.pick = pick;
        this.length = 0;
        this.active = -1;
    }

    /**
     * Shows what the box found. Rows are `<li>`s it built itself; nothing found is a line saying so,
     * because a menu that closes on a typo looks like a box that stopped working.
     */
    show(rows, empty) {
        this.length = rows.length;
        this.menu.replaceChildren();

        if (0 === rows.length) {
            this.menu.append(element('li', 'combobox__empty', empty));
        }

        rows.forEach((row, index) => {
            row.id = `${this.menu.id}-${index}`;
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', 'false');
            // on mousedown the input has not lost focus yet, so picking never races the blur that closes
            row.addEventListener('mousedown', (event) => {
                event.preventDefault();
                this.pick(index);
            });

            this.menu.append(row);
        });

        // a new list is a list nothing is on yet, and what the field points at has to say so too
        this.highlight(-1);
        this.open();
    }

    open() {
        this.menu.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.menu.hidden = true;
        this.active = -1;
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
    }

    /**
     * The keys the menu answers. Everything else is typing and belongs to the field, which is what the
     * return value says.
     */
    navigate(event) {
        if ('Escape' === event.key) {
            this.close();

            return true;
        }

        if ('Enter' === event.key && this.active >= 0) {
            event.preventDefault();
            this.pick(this.active);

            return true;
        }

        if (('ArrowDown' !== event.key && 'ArrowUp' !== event.key) || 0 === this.length) {
            return false;
        }

        event.preventDefault();

        // the input itself is one of the stops, so arrowing past either end lands back in it
        const stops = this.length + 1;
        const step = 'ArrowDown' === event.key ? 1 : -1;

        this.highlight(((this.active + 1 + step + stops) % stops) - 1);

        return true;
    }

    /** The row the keyboard is on - only the keyboard: hovering is the pointer's own business. */
    highlight(index) {
        this.active = index;

        [...this.menu.children].forEach((row, position) => {
            row.classList.toggle('is-active', position === index);
            row.setAttribute('aria-selected', position === index ? 'true' : 'false');
        });

        if (index >= 0) {
            this.input.setAttribute('aria-activedescendant', this.menu.children[index].id);
            this.menu.children[index].scrollIntoView({ block: 'nearest' });
        } else {
            this.input.removeAttribute('aria-activedescendant');
        }
    }
}
