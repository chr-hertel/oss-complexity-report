import { Controller } from '@hotwired/stimulus';
import Swup from 'swup';
import SwupFadeTheme from '@swup/fade-theme';
import SwupFormsPlugin from '@swup/forms-plugin';

/*
 * Page transitions, previously provided by symfony/ux-swup. That bundle shipped
 * its controller as a file: npm dependency pointing into vendor/, which no
 * longer works once the assets are built by Vite instead of Encore.
 */
export default class extends Controller {
    static values = {
        containers: Array,
        mainElement: String,
    };

    connect() {
        const containers = this.containersValue.length > 0 ? this.containersValue : ['#swup'];
        const mainElement = this.hasMainElementValue ? this.mainElementValue : containers[0];

        this.swup = new Swup({
            containers,
            plugins: [new SwupFadeTheme({ mainElement }), new SwupFormsPlugin()],
        });
    }

    disconnect() {
        this.swup?.destroy();
        this.swup = null;
    }
}
