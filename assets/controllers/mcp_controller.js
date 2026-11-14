import { Controller } from '@hotwired/stimulus';

/*
 * The pill pinned to the bottom left of every page and the dialog it opens: how to add the MCP server
 * of the report to a client. The dialog behaves like the one the raw output is shown in - a click next
 * to it closes it like Escape does, and it is closed before Turbo caches the page, since one that came
 * back open would come back not modal.
 */
export default class extends Controller {
    static targets = ['dialog', 'source'];

    connect() {
        this.closeBeforeCache = () => this.close();
        document.addEventListener('turbo:before-cache', this.closeBeforeCache);
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.closeBeforeCache);
    }

    open() {
        this.dialogTarget.showModal();
    }

    close() {
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    dismiss(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    /**
     * Copies the snippet the button stands next to, and says so on the button for a moment.
     */
    async copy(event) {
        const button = event.currentTarget;
        const source = this.sourceTargets.find((target) => target.parentElement === button.parentElement);

        try {
            await navigator.clipboard.writeText(source.textContent.trim());
            button.textContent = 'Copied';
        } catch {
            button.textContent = 'Select it';
        }

        clearTimeout(button.copyReset);
        button.copyReset = setTimeout(() => {
            button.textContent = 'Copy';
        }, 1600);
    }
}
