import { Controller } from '@hotwired/stimulus';

/*
 * The time frames the hero figure can be read in. Like the rankings below, all of them are rendered
 * with the request - picking one only swaps which is visible, nothing is fetched.
 */
export default class extends Controller {
    static targets = ['tab', 'panel'];

    select(event) {
        const index = this.tabTargets.indexOf(event.currentTarget);

        if (index < 0) {
            return;
        }

        this.tabTargets.forEach((tab, position) => {
            tab.setAttribute('aria-selected', position === index ? 'true' : 'false');
        });

        this.panelTargets.forEach((panel, position) => {
            panel.hidden = position !== index;
        });
    }
}
