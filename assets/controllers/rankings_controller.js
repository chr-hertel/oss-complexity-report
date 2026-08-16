import { Controller } from '@hotwired/stimulus';

/*
 * The four orders the start page offers for its featured repositories. All of them are rendered, so
 * switching one on is a matter of hiding the others - no request, no reflow of the page below.
 *
 * The way into the chart belongs to the ranking rather than to the section above it: it draws what is
 * being read, which is a different set of repositories per tab. There is one button for all four, so
 * the tab carries where it goes and what it should say - and a ranking that came out empty carries
 * neither, which is how the button knows to step aside.
 */
export default class extends Controller {
    static targets = ['tab', 'panel', 'title', 'chart'];

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

        this.reflectChart(this.tabTargets[index]);
    }

    reflectChart(tab) {
        if (!this.hasChartTarget) {
            return;
        }

        const url = tab.dataset.chartUrl;

        this.chartTarget.hidden = !url;

        if (url) {
            this.chartTarget.href = url;
            this.chartTarget.textContent = tab.dataset.chartLabel;
        }
    }
}
