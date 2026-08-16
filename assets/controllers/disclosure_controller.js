import { Controller } from '@hotwired/stimulus';

/*
 * Something the page says in one line until somebody asks for the rest of it: the accounts behind the
 * repositories at the end of the strip closing the start page, and the long version of what the
 * numbers are worth under the block explaining them. Both are worth having and neither is worth the
 * screenful it takes from what somebody came to read.
 *
 * Which way round it starts is the whole point of doing it here rather than in the template: the page
 * is rendered with the panel open and the button not there, so a browser that never runs this reads
 * everything the report has to say. Folding it away is the enhancement, so it is what the controller
 * does on connect.
 */
export default class extends Controller {
    static targets = ['toggle', 'panel'];

    connect() {
        this.panelTarget.hidden = true;
        this.toggleTarget.hidden = false;
        this.reflect();
    }

    toggle() {
        this.panelTarget.hidden = !this.panelTarget.hidden;
        this.reflect();
    }

    reflect() {
        this.toggleTarget.setAttribute('aria-expanded', this.panelTarget.hidden ? 'false' : 'true');
    }
}
