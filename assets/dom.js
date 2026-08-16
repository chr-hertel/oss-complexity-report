/*
 * One element, built. Everything the report renders in the browser - the rows of a combobox, the tabs
 * of the release analysis, sixty-two numbers of a measurement - is built rather than written into a
 * string, so this is the one line that would otherwise be written a hundred times.
 */
export function element(tag, className, text) {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (undefined !== text) {
        node.textContent = text;
    }

    return node;
}
