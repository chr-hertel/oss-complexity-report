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
const day = (value) => moment(value, 'YYYY-MM-DD').format('LL');
const dot = () => element('span', 'release-head__dot', '·');

// the star count of the rankings, shortened the way the `stars` twig filter shortens it
function stars(total) {
    const node = element('span', 'metric metric--star');

    node.title = `${count(total)} stars on GitHub`;
    node.append(
        element('i', 'fas fa-star'),
        element('span', null, total < 1000 ? String(total) : `${(total / 1000).toFixed(1).replace(/\.0$/, '')}k`),
    );

    return node;
}

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
        'headline',
        'headlineLink',
        'canvas',
        'resetZoom',
        'select',
        'release',
        'releaseTabs',
        'releasePanel',
        'releaseRepository',
        'releaseName',
        'releaseMeta',
        'releaseSelect',
        'analysis',
    ];

    connect() {
        // a chart that is still being measured has no canvas and nothing to pick from - the page says so
        // on its own, and there is nothing here to keep up to date
        if (!this.hasCanvasTarget) {
            return;
        }

        // what the page was rendered with; everything picked later is fetched from the JSON route once
        this.graphs = new Map();
        JSON.parse(this.element.dataset.repositories).forEach((graph) => {
            this.graphs.set(graph.name, graph);
        });

        // `<site> - <headline>`, so what is in front of the last dash is the part that stays
        this.site = document.title.split(' - ').slice(0, -1).join(' - ');
        this.renders = 0;
        // the lines that can be read release by release, and which one of them is being read
        this.series = [];
        this.repository = null;
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
        if (!this.hasSelectTarget) {
            return;
        }

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
                // whatever the tooltip is pointing at is what a click reads below the chart, so the
                // point that answers the click is the one the pointer already named
                onClick: (event, elements) => this.pickPoint(elements),
                onHover: (event, elements) => {
                    this.canvasTarget.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                },
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
                        pan: { enabled: true, onPanComplete: () => this.reflectZoom() },
                        zoom: {
                            wheel: { enabled: true },
                            pinch: { enabled: true },
                            mode: 'xy',
                            onZoomComplete: () => this.reflectZoom(),
                        },
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

    /**
     * Zooming has no address bar to go back in, so the way out of it is a button - and one that is
     * only there while there is something to undo, since an untouched chart has nothing to reset.
     */
    reflectZoom() {
        if (this.hasResetZoomTarget) {
            this.resetZoomTarget.hidden = !this.chart?.isZoomedOrPanned();
        }
    }

    resetZoom() {
        this.chart?.resetZoom();
        this.reflectZoom();
    }

    /**
     * A point in the chart is a release, and the release analysis below is where a release is read -
     * so clicking one opens it there: its repository becomes the tab being read, and the point itself
     * the release, whichever line it belongs to.
     */
    pickPoint(elements) {
        const point = elements[0];

        if (!point || !this.hasReleaseTarget) {
            return;
        }

        this.showRepository(this.chart.data.datasets[point.datasetIndex].label, point.index);
        this.revealRelease();
    }

    /**
     * The chart fills the screen it is clicked in, so the answer to a click is usually below the fold -
     * scrolled to only then, since nothing should move under someone who can already see it.
     */
    revealRelease() {
        if (this.releaseTarget.getBoundingClientRect().top > window.innerHeight - 120) {
            this.releaseTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
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
        const slugs = $(this.selectTarget).val() || [];

        if (picked) {
            this.syncUrl(slugs);
        }

        const graphs = await Promise.all(slugs.map((slug) => this.load(slug)));

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
        this.reflectZoom();

        this.renderHeadline(slugs);
        this.renderTabs(graphs);
    }

    /**
     * What the page is called follows what is in the chart, by the same rule the server rendered it with
     * - the repository itself while it is the only one, a count as soon as there are more. A single
     * repository is a slug, and a slug is all a github.com address is, so the link follows too.
     */
    renderHeadline(slugs) {
        const single = 1 === slugs.length;

        this.headlineTarget.textContent = single
            ? slugs[0]
            : `${0 === slugs.length ? 'No' : slugs.length} repositories`;
        document.title = `${this.site} - ${this.headlineTarget.textContent}`;

        this.headlineLinkTarget.hidden = !single;

        if (single) {
            this.headlineLinkTarget.href = `https://github.com/${slugs[0]}`;
            this.headlineLinkTarget.title = `github.com/${slugs[0]}`;
        }
    }

    /**
     * A chart is worth sharing, so what is in it belongs in the address bar - the same query string the
     * page reads on load. Only after a pick: opening the default chart leaves its url alone.
     */
    syncUrl(slugs) {
        const params = new URLSearchParams(window.location.search);

        if (slugs.length > 0) {
            params.set('repositories', slugs.join(','));
        } else {
            params.delete('repositories');
        }

        // `symfony/console,laravel/framework` is what this was typed as, and what it should stay
        const query = params.toString().replace(/%2C/g, ',').replace(/%2F/g, '/');

        window.history.replaceState({}, '', query ? `?${query}` : window.location.pathname);
    }

    async load(slug) {
        if (!this.graphs.has(slug)) {
            // the slug is the whole route, so a relative request keeps working under a deployed sub path
            const response = await fetch(slug, { headers: { Accept: 'application/json' } });

            // the route answers with the line itself, the same shape the page was rendered with
            this.graphs.set(slug, await response.json());
        }

        return this.graphs.get(slug);
    }

    /**
     * The measurement below the chart is one tab per line: every repository in the chart can be read
     * release by release, not only the one the page was opened with. The tab carries the colour its
     * series is drawn in, so a line and its numbers are found by the same swatch.
     */
    renderTabs(graphs) {
        if (!this.hasReleaseTarget) {
            return;
        }

        this.series = graphs.filter((graph) => graph.tags.length > 0);
        this.releaseTarget.hidden = 0 === this.series.length;

        if (0 === this.series.length) {
            return;
        }

        this.releaseTabsTarget.replaceChildren(
            ...this.series.map((graph, index) => {
                const tab = element('button', 'tab');

                tab.type = 'button';
                tab.id = `release-tab-${index}`;
                tab.dataset.name = graph.name;
                tab.dataset.action = 'chart#selectRepository';
                tab.setAttribute('role', 'tab');
                tab.setAttribute('aria-selected', 'false');
                tab.append(element('span', 'tab__swatch'), element('span', null, graph.name));

                return tab;
            }),
        );

        // one line is not a choice
        this.releaseTabsTarget.hidden = this.series.length < 2;

        // whoever was being read stays it, as long as its line is still in the chart
        const read = this.series.some((graph) => graph.name === this.repository)
            ? this.repository
            : this.series[0].name;

        this.showRepository(read);
    }

    selectRepository(event) {
        this.showRepository(event.currentTarget.dataset.name);
    }

    /**
     * Reading a repository starts at its newest release, unless the reader said which one - clicking a
     * point in the chart does.
     */
    showRepository(name, index = null) {
        const graph = this.series.find((entry) => entry.name === name);

        if (!graph) {
            return;
        }

        this.repository = name;

        [...this.releaseTabsTarget.children].forEach((tab) => {
            const active = tab.dataset.name === name;

            tab.setAttribute('aria-selected', active ? 'true' : 'false');

            if (active) {
                this.releasePanelTarget.setAttribute('aria-labelledby', tab.id);
            }
        });

        this.release = graph;
        this.releaseSelectTarget.replaceChildren();

        graph.tags.forEach((tag, index) => {
            const option = element('option', null, `${tag.name}  ·  Ø ${decimal(tag.y, 2)}`);

            option.value = String(index);
            // newest first, the way the report is read
            this.releaseSelectTarget.prepend(option);
        });

        this.showRelease(null === index ? graph.tags.length - 1 : index);
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
        this.releaseNameTarget.textContent = tag.name;

        // where the release is read on github.com - the name of the repository is its address there
        this.releaseRepositoryTarget.href = this.release.url;
        this.releaseRepositoryTarget.title = this.release.url.replace('https://', '');
        this.releaseRepositoryTarget.replaceChildren(
            element('span', null, this.release.name),
            element('i', 'fas fa-arrow-up-right-from-square'),
        );

        /*
         * The date a release carries is the day it was tagged, not the day the report measured it - the
         * measurement happened whenever the repository was submitted or a nightly scan picked the release
         * up, which is not what anyone reading a release is after.
         */
        this.releaseMetaTarget.replaceChildren(element('span', null, `Released on ${day(tag.date)}`));

        if (previous) {
            this.releaseMetaTarget.append(
                dot(),
                element('span', null, 'Ø complexity'),
                trend(Math.round((tag.y - previous.y) * 100) / 100, 2, `vs ${previous.name}`),
            );
        }

        // what the repository behind the line is, next to the release being read from it
        this.releaseMetaTarget.append(
            dot(),
            stars(this.release.stars),
            dot(),
            element('span', null, `First release ${day(first.date)}`),
        );

        const complexities = tags.map((entry) => entry.y);

        const groups = [
            group('Release', [
                row('Released', day(tag.date)),
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
            // the first release is in the head, above every group - it says what the repository is
            group(`All ${tags.length} releases`, [
                row('Analysed releases', count(tags.length)),
                row('Lowest Ø complexity', decimal(Math.min(...complexities), 2)),
                row('Highest Ø complexity', decimal(Math.max(...complexities), 2)),
            ]),
        ];

        this.analysisTarget.replaceChildren(...groups.filter(Boolean));
    }
}
