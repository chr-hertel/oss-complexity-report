import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';
import 'chartjs-adapter-moment';
import zoomPlugin from 'chartjs-plugin-zoom';
import moment from 'moment';
import select2 from 'select2';
import $ from 'jquery';

// select2's CommonJS build exports a factory rather than registering itself on
// jQuery. Webpack's interop happened to call it; Vite's does not, so importing
// it for its side effect alone leaves $.fn.select2 undefined.
select2(window, $);

// The eight series colours of the design system, assigned by position. The swatch in front of every
// chip in the select box is coloured by the same rule, see _components.scss - which only holds as long
// as the order of the datasets is the order of the chips, hence moveToEnd() and the full re-render.
const SERIES_COLORS = ['#2f3a49', '#c05b4d', '#b58a3c', '#4e8c7d', '#4a6fa5', '#7c6ba0', '#7a9a4e', '#a0a8b4'];
const GRID = '#e3e7ec';
const TICK = '#5a6675';
const INK = '#101720';
const MONO = 'IBM Plex Mono';
const SANS = 'IBM Plex Sans';

const ARROWS = { good: '↓', bad: '↑', flat: '→' };

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

const count = (value) => value.toLocaleString('en-US');
const decimal = (value, digits) =>
    value.toLocaleString('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits });

// complexity going down is an improvement, going up is a regression
const tone = (value) => (Math.abs(value) < 0.005 ? 'flat' : value < 0 ? 'good' : 'bad');
const sign = (value) => (value > 0 ? '+' : value < 0 ? '−' : '±');
const signed = (value, digits = 0) => sign(value) + decimal(Math.abs(value), digits);
const share = (part, total) => (total ? `${decimal((Math.abs(part) / total) * 100, 1)}%` : undefined);

function trend(value, digits, label) {
    const direction = tone(value);
    const node = element('span', `trend trend--chip trend--${direction}`);

    node.append(element('span', null, ARROWS[direction]), element('span', null, signed(value, digits)));

    if (label) {
        node.append(element('span', 'trend__label', label));
    }

    return node;
}

function row(label, value, hint) {
    const node = element('div', 'analysis__row');
    const definition = element('dd', 'analysis__value');

    if (hint) {
        definition.append(element('span', 'analysis__share', hint));
    }

    definition.append(value instanceof Node ? value : element('span', null, String(value)));
    node.append(element('dt', 'analysis__label', label), definition);

    return node;
}

function group(title, rows) {
    const node = element('div');
    const list = element('dl', 'analysis__list');

    rows.filter(Boolean).forEach((entry) => list.append(entry));
    node.append(element('div', 'analysis__title', title), list);

    return node;
}

export default class extends Controller {
    static targets = [
        'canvas',
        'select',
        'seriesCount',
        'release',
        'releaseRepository',
        'releaseName',
        'releaseMeta',
        'releaseSelect',
        'analysis',
    ];
    static values = { limit: { type: Number, default: 8 } };

    connect() {
        // what the page was rendered with; everything picked later is fetched from the JSON route once
        this.graphs = new Map();
        JSON.parse(this.element.dataset.repositories).forEach((graph) => {
            this.graphs.set(String(graph.id), graph);
        });

        this.renders = 0;
        this.initChart();
        this.initSelectBox();
        this.render();

        /*
         * Turbo snapshots the page for its back button while the page is still on screen - before this
         * controller disconnects, so the select box has to be torn down for the snapshot as well.
         * Otherwise coming back would restore select2's rendered widget and then draw a second one
         * next to it.
         */
        this.beforeCache = () => this.teardown();
        document.addEventListener('turbo:before-cache', this.beforeCache);
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.beforeCache);
        this.teardown();
    }

    teardown() {
        this.chart?.destroy();
        this.chart = null;

        const $select = $(this.selectTarget);

        $select.off();

        if ($select.data('select2')) {
            $select.select2('destroy');
        }
    }

    initChart() {
        Chart.register(zoomPlugin);

        this.chart = new Chart(this.canvasTarget, {
            type: 'line',
            data: { datasets: [] },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'nearest', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        align: 'start',
                        labels: {
                            boxWidth: 8,
                            boxHeight: 8,
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            color: TICK,
                            font: { family: MONO, size: 11 },
                            padding: 16,
                        },
                    },
                    tooltip: {
                        backgroundColor: INK,
                        padding: 10,
                        cornerRadius: 6,
                        displayColors: false,
                        titleFont: { family: SANS, size: 12, weight: '600' },
                        bodyFont: { family: MONO, size: 12 },
                        callbacks: {
                            title: (items) => `${items[0].dataset.label} ${items[0].raw.name}`,
                            label: (item) => `Ø Complexity: ${item.formattedValue}`,
                        },
                    },
                    zoom: {
                        pan: { enabled: true },
                        zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'xy' },
                    },
                },
                scales: {
                    x: {
                        type: 'time',
                        time: { tooltipFormat: 'll', parser: 'MM-DD-YYYY' },
                        border: { color: GRID },
                        grid: { display: false },
                        ticks: { color: TICK, font: { family: MONO, size: 11 } },
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: GRID },
                        ticks: { color: TICK, font: { family: MONO, size: 11 }, padding: 8 },
                    },
                },
            },
        });
    }

    initSelectBox() {
        const $select = $(this.selectTarget);

        $select.select2({ width: '100%', placeholder: 'Search analysed repositories' });
        $select.on('select2:select', this.moveToEnd);
        $select.on('select2:select select2:unselect', () => this.render(true));
    }

    /**
     * select2 leaves a picked option where it sits in the markup, so its chips would be in a different
     * order than the chart series - and the colour of a chip is the colour of its line.
     */
    moveToEnd(event) {
        const $option = $(event.params.data.element);

        $option.detach();
        $(this).append($option);
        $(this).trigger('change.select2');
    }

    async render(picked = false) {
        const run = ++this.renders;
        const ids = $(this.selectTarget).val() || [];

        if (picked) {
            this.syncUrl(ids);
        }

        const graphs = await Promise.all(ids.map((id) => this.load(id)));

        // a slower request must not overwrite what a later pick already drew
        if (run !== this.renders || !this.chart) {
            return;
        }

        this.chart.data.datasets = graphs.map((graph, index) => ({
            label: graph.name,
            data: graph.tags,
            fill: false,
            borderWidth: 1.75,
            pointRadius: 2.5,
            pointHoverRadius: 5,
            tension: 0.15,
            borderColor: SERIES_COLORS[index % SERIES_COLORS.length],
            backgroundColor: SERIES_COLORS[index % SERIES_COLORS.length],
        }));
        this.chart.update();

        if (this.hasSeriesCountTarget) {
            this.seriesCountTarget.textContent = `${graphs.length} of ${this.limitValue} series shown.`;
        }

        this.renderRelease(graphs[0]);
    }

    /**
     * A chart is worth sharing, so what is in it belongs in the address bar - the same query string the
     * page reads on load. Only after a pick: opening the default chart leaves its url alone.
     */
    syncUrl(ids) {
        const params = new URLSearchParams(window.location.search);

        if (ids.length > 0) {
            params.set('repositories', ids.join(','));
        } else {
            params.delete('repositories');
        }

        // a list of ids reads better with the commas it was written with than with %2C
        const query = params.toString().replace(/%2C/g, ',');

        window.history.replaceState({}, '', query ? `?${query}` : window.location.pathname);
    }

    async load(id) {
        const key = String(id);

        if (!this.graphs.has(key)) {
            // the id is the whole route, so a relative request keeps working under a deployed sub path
            const response = await fetch(key, { headers: { Accept: 'application/json' } });
            const option = this.selectTarget.querySelector(`option[value="${key}"]`);

            this.graphs.set(key, { id: Number(key), name: option.textContent.trim(), tags: await response.json() });
        }

        return this.graphs.get(key);
    }

    /**
     * The measurement below the chart follows the first series - that is the repository the page was
     * opened for, and the one whose line is drawn in the first colour.
     */
    renderRelease(graph) {
        if (!this.hasReleaseTarget) {
            return;
        }

        if (!graph || 0 === graph.tags.length) {
            this.releaseTarget.hidden = true;

            return;
        }

        this.releaseTarget.hidden = false;
        this.release = graph;
        this.releaseSelectTarget.replaceChildren();

        graph.tags.forEach((tag, index) => {
            const option = element('option', null, `${tag.name}  ·  Ø ${decimal(tag.y, 2)}`);

            option.value = String(index);
            // newest first, the way the report is read
            this.releaseSelectTarget.prepend(option);
        });

        this.showRelease(graph.tags.length - 1);
    }

    selectRelease(event) {
        this.showRelease(Number(event.currentTarget.value));
    }

    showRelease(index) {
        const tags = this.release.tags;
        const tag = tags[index];
        const previous = index > 0 ? tags[index - 1] : undefined;
        const first = tags[0];

        this.releaseSelectTarget.value = String(index);
        this.releaseRepositoryTarget.textContent = this.release.name;
        this.releaseNameTarget.textContent = tag.name;

        this.releaseMetaTarget.replaceChildren(
            element('span', null, `Measured with phploc on ${moment(tag.date, 'YYYY-MM-DD').format('LL')}`),
        );

        if (previous) {
            this.releaseMetaTarget.append(
                element('span', 'release-head__dot', '·'),
                element('span', null, 'Ø complexity'),
                trend(Math.round((tag.y - previous.y) * 100) / 100, 2, `vs ${previous.name}`),
            );
        }

        const complexities = tags.map((entry) => entry.y);

        const groups = [
            group('Release', [
                row('Released', moment(tag.date, 'YYYY-MM-DD').format('LL')),
                row('Lines of code (LOC)', count(tag.loc)),
                row('Ø cyclomatic complexity', decimal(tag.y, 2)),
            ]),
            previous
                ? group(`Compared to ${previous.name}`, [
                      row('Lines of code', signed(tag.loc - previous.loc), share(tag.loc - previous.loc, previous.loc)),
                      row('Ø complexity', trend(Math.round((tag.y - previous.y) * 100) / 100, 2)),
                  ])
                : null,
            tag !== first
                ? group(`Since ${first.name}`, [
                      row('Lines of code', signed(tag.loc - first.loc), share(tag.loc - first.loc, first.loc)),
                      row('Ø complexity', trend(Math.round((tag.y - first.y) * 100) / 100, 2)),
                  ])
                : null,
            group(`All ${tags.length} releases`, [
                row('Analysed releases', count(tags.length)),
                row('Lowest Ø complexity', decimal(Math.min(...complexities), 2)),
                row('Highest Ø complexity', decimal(Math.max(...complexities), 2)),
                row('First release', moment(first.date, 'YYYY-MM-DD').format('LL')),
            ]),
        ];

        this.analysisTarget.replaceChildren(...groups.filter(Boolean));
    }
}
