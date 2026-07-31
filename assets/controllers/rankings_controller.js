import { Controller } from '@hotwired/stimulus';

/*
 * The four orders the start page offers for its featured repositories. All of them are rendered, so
 * switching one on is a matter of hiding the others - no request, no reflow of the page below.
 */
export default class extends Controller {
    static targets = ['tab', 'panel', 'title'];

    select(event) {
        this.show(this.tabTargets.indexOf(event.currentTarget));
    }

    show(index) {
        if (index < 0) {
            return;
        }

        this.tabTargets.forEach((tab, position) => {
            tab.setAttribute('aria-selected', position === index ? 'true' : 'false');
        });

        this.panelTargets.forEach((panel, position) => {
            panel.hidden = position !== index;
        });

        if (this.hasTitleTarget) {
            this.titleTarget.textContent = this.tabTargets[index].dataset.title;
        }
    }
}
