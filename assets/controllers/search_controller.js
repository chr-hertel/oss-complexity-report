import { Controller } from '@hotwired/stimulus';

/*
 * The search box on the start page. It asks the server what an input means - a repository the report
 * already carries, or one that could be submitted - and never decides that itself: the rules for what
 * identifies a repository live in RepositoryIdentifier.
 *
 * Picking a match opens its page. Picking the "add" row, or submitting without picking anything, posts
 * the form to `submit`, which queues the repository and opens its page as well.
 */
const DEBOUNCE = 200;

function element(tag, className, text) {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (undefined !== text) {
        node.textContent = text;
    }

    return node;
}

const stars = (value) => (value < 1000 ? String(value) : String((value / 1000).toFixed(1)).replace(/\.0$/, '') + 'k');

export default class extends Controller {
    static targets = ['input', 'menu'];
    static values = { url: String };

    connect() {
        this.options = [];
        this.active = -1;
        this.requests = 0;
    }

    disconnect() {
        clearTimeout(this.timer);
        this.close();
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
        this.active = -1;
        this.menuTarget.replaceChildren();

        if (0 === this.options.length) {
            this.menuTarget.append(
                element('li', 'combobox__empty', 'Nothing found - paste a vendor/repository to add it.'),
            );
            this.open();

            return;
        }

        this.options.forEach((option, index) => {
            const node = element('li', 'combobox__option');

            node.id = `search-suggestion-${index}`;
            node.setAttribute('role', 'option');
            node.setAttribute('aria-selected', 'false');
            // on mousedown the input has not lost focus yet, so picking never races the blur that closes
            node.addEventListener('mousedown', (event) => {
                event.preventDefault();
                this.pick(index);
            });

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

            this.menuTarget.append(node);
        });

        this.open();
    }

    navigate(event) {
        if ('Escape' === event.key) {
            this.close();

            return;
        }

        if ('Enter' === event.key && this.active >= 0) {
            event.preventDefault();
            this.pick(this.active);

            return;
        }

        if ('ArrowDown' !== event.key && 'ArrowUp' !== event.key) {
            return;
        }

        if (0 === this.options.length) {
            return;
        }

        event.preventDefault();

        // the input itself is one of the stops, so arrowing past either end lands back in it
        const stops = this.options.length + 1;
        const step = 'ArrowDown' === event.key ? 1 : -1;

        this.highlight(((this.active + 1 + step + stops) % stops) - 1);
    }

    highlight(index) {
        this.active = index;

        [...this.menuTarget.children].forEach((node, position) => {
            node.classList.toggle('is-active', position === index);
            node.setAttribute('aria-selected', position === index ? 'true' : 'false');
        });

        if (index >= 0) {
            this.inputTarget.setAttribute('aria-activedescendant', `search-suggestion-${index}`);
            this.menuTarget.children[index].scrollIntoView({ block: 'nearest' });
        } else {
            this.inputTarget.removeAttribute('aria-activedescendant');
        }
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

    open() {
        this.menuTarget.hidden = false;
        this.inputTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.menuTarget.hidden = true;
        this.active = -1;
        this.inputTarget.setAttribute('aria-expanded', 'false');
        this.inputTarget.removeAttribute('aria-activedescendant');
    }
}
