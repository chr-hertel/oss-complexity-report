import { Controller } from '@hotwired/stimulus';

/*
 * Reloads a page that is waiting for data. Analysing a repository happens in a worker, so its releases
 * appear without anything happening in the browser - a visitor who just submitted would stare at a
 * status that never changes.
 */
export default class extends Controller {
    static values = {
        interval: { type: Number, default: 30000 },
    };

    connect() {
        this.timer = window.setInterval(() => {
            // a forgotten tab reloads nothing while it is in the background, it catches up when looked at
            if (!document.hidden) {
                window.location.reload();
            }
        }, this.intervalValue);
    }

    disconnect() {
        window.clearInterval(this.timer);
    }
}
