import { Controller } from '@hotwired/stimulus';

/*
 * The menu behind the button in the header - the product's only navigation. Bootstrap's dropdown used
 * to do this; the design system draws the menu itself, so this is what is left of it.
 */
export default class extends Controller {
    static targets = ['toggle', 'menu'];

    connect() {
        this.closeOnOutsideClick = (event) => {
            if (!this.element.contains(event.target)) {
                this.close();
            }
        };
        this.closeOnEscape = (event) => {
            if ('Escape' === event.key) {
                this.close();
                this.toggleTarget.focus();
            }
        };

        document.addEventListener('click', this.closeOnOutsideClick);
        document.addEventListener('keydown', this.closeOnEscape);
    }

    disconnect() {
        document.removeEventListener('click', this.closeOnOutsideClick);
        document.removeEventListener('keydown', this.closeOnEscape);
    }

    toggle() {
        this.menuTarget.hidden ? this.open() : this.close();
    }

    open() {
        this.menuTarget.hidden = false;
        this.toggleTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        if (!this.hasMenuTarget) {
            return;
        }

        this.menuTarget.hidden = true;
        this.toggleTarget.setAttribute('aria-expanded', 'false');
    }
}
