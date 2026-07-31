import { Controller } from '@hotwired/stimulus';

/*
 * The placeholder a freshly submitted repository shows until a worker measured its first release. It
 * asks the repository's own json route now and then, and reloads once there is something to draw - the
 * analysis takes minutes, so the page has to survive being left open.
 */
export default class extends Controller {
    static values = { url: String, interval: { type: Number, default: 15000 } };

    connect() {
        this.timer = setInterval(() => this.check(), this.intervalValue);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    async check() {
        // a background tab does not need to poll; it checks again when it is looked at
        if (document.hidden) {
            return;
        }

        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                return;
            }

            const tags = await response.json();

            if (Array.isArray(tags) && tags.length > 0) {
                window.location.reload();
            }
        } catch {
            // the worker may still be starting, or the network blinked - the next tick tries again
        }
    }
}
