import { Controller } from '@hotwired/stimulus';
import { Combobox, element } from '../combobox.js';

/*
 * The search box on the start page. It asks the server what an input means - a repository the report
 * already carries, or one that could be submitted - and never decides that itself: the rules for what
 * identifies a repository live in RepositoryIdentifier.
 *
 * Picking a match opens its page. Picking the "add" row, or submitting without picking anything, posts
 * the form to `submit`, which queues the repository and opens its page as well.
 */
const DEBOUNCE = 200;

const stars = (value) => (value < 1000 ? String(value) : String((value / 1000).toFixed(1)).replace(/\.0$/, '') + 'k');

export default class extends Controller {
    static targets = ['input', 'menu'];
    static values = { url: String };

    connect() {
        this.options = [];
        this.combobox = new Combobox(this.inputTarget, this.menuTarget, (index) => this.pick(index));
        this.requests = 0;
    }

    disconnect() {
        clearTimeout(this.timer);
        this.combobox.close();
    }

    query() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.load(), DEBOUNCE);
    }

    async load() {
        const value = this.inputTarget.value.trim();
        const run = ++this.requests;

        if ('' === value) {
            this.close();

            return;
        }

        let result;

        try {
            const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            result = await response.json();
        } catch {
            // offline or a request that went nowhere - the box stays a plain input, which still submits
            return;
        }

        // a slower answer must not replace the suggestions for what is typed now
        if (run !== this.requests) {
            return;
        }

        this.render(result);
    }

    render(result) {
        this.options = [
            ...result.repositories.map((repository) => ({ type: 'open', repository })),
            ...(result.submittable ? [{ type: 'submit', name: result.submittable.name }] : []),
        ];

        this.combobox.show(
            this.options.map((option) => this.row(option)),
            'Nothing found - paste a vendor/repository to add it.',
        );
    }

    row(option) {
        const node = element('li', 'combobox__option');

        if ('open' === option.type) {
            const meta = element('span', 'combobox__meta');

            meta.append(element('i', 'fas fa-star'), element('span', null, stars(option.repository.stars)));

            if (!option.repository.analysed) {
                meta.append(element('span', 'combobox__badge', 'queued'));
            }

            node.append(element('span', 'combobox__name', option.repository.name), meta);

            if (option.repository.description) {
                node.append(element('span', 'combobox__description', option.repository.description));
            }
        } else {
            node.classList.add('combobox__option--add');
            node.append(
                element('span', 'combobox__name', option.name),
                element('span', 'combobox__meta', 'add to the report'),
            );
        }

        return node;
    }

    navigate(event) {
        this.combobox.navigate(event);
    }

    pick(index) {
        const option = this.options[index];

        if (!option) {
            return;
        }

        if ('open' === option.type) {
            window.location.assign(option.repository.url);

            return;
        }

        // hand the canonical `vendor/repository` to submit, not whatever url it was pasted as
        this.inputTarget.value = option.name;
        this.close();
        this.element.requestSubmit();
    }

    close() {
        this.combobox.close();
    }
}
