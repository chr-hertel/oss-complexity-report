import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';
import 'chartjs-adapter-moment';
import zoomPlugin from 'chartjs-plugin-zoom';
import moment from 'moment';
import { element } from '../dom.js';
import { band, catalogFrom, change, direction, format, group, list, percent, row, share, value } from '../metrics.js';

// The eight series colours of the design system, assigned by position. The swatch in front of every
// chip in the picker is coloured by the same rule, see _components.scss - which only holds as long as
// the order of the datasets is the order of the chips, which is why a pick goes to the end of both.
const SERIES_COLORS = ['#2f3a49', '#c05b4d', '#b58a3c', '#4e8c7d', '#4a6fa5', '#7c6ba0', '#7a9a4e', '#a0a8b4'];

// The chart draws as many lines as are picked, and the palette is eight - so it starts over, and every
// pass through it is drawn differently: a ninth line shares its colour with the first but not its
// stroke. Two lines only look alike once there are more than 24 of them.
const SERIES_DASHES = [[], [6, 3], [2, 3]];
const stroke = (index) => ({
    borderColor: SERIES_COLORS[index % SERIES_COLORS.length],
    backgroundColor: SERIES_COLORS[index % SERIES_COLORS.length],
    borderDash: SERIES_DASHES[Math.floor(index / SERIES_COLORS.length) % SERIES_DASHES.length],
});
const GRID = '#e3e7ec';
const TICK = '#5a6675';
const INK = '#101720';
const MONO = 'IBM Plex Mono';
const SANS = 'IBM Plex Sans';

const count = (value) => value.toLocaleString('en-US');
const day = (value) => moment(value, 'YYYY-MM-DD').format('LL');

// the star count of the rankings, shortened the way the `stars` twig filter shortens it
const stars = (total) => (total < 1000 ? String(total) : `${(total / 1000).toFixed(1).replace(/\.0$/, '')}k`);

/*
 * One of the four figures a release comes down to, above the panel spelling it out. The note under a
 * value is what makes it a reading rather than a number: where a complexity stands on the scale, which
 * direction is the good one, what a size grew by, when the measuring started.
 */
function headline(label, measured, note, tone) {
    const node = element('div', 'headline-figures__cell');
    const figure = element('div', `headline-figures__value${tone ? ` headline-figures__value--${tone}` : ''}`);

    figure.append(measured instanceof Node ? measured : element('span', null, String(measured)));
    node.append(element('div', 'headline-figures__label', label), figure);

    if (note) {
        const caption = element('div', 'headline-figures__note');

        caption.append(note instanceof Node ? note : element('span', null, String(note)));
        node.append(caption);
    }

    return node;
}

export default class extends Controller {
    static targets = [
        'headline',
        'headlineLink',
        'canvas',
        'count',
        'resetZoom',
        'select',
        'metrics',
        'metricTabs',
        'metricAbout',
        'release',
        'releaseTabs',
        'releasePanel',
        'releaseLink',
        'releaseSelect',
        'headlineFigures',
        'analysis',
        'measurement',
        'measurementToggle',
        'raw',
        'rawTitle',
        'rawOutput',
    ];

    connect() {
        // a chart that is still being measured has no canvas and nothing to pick from - the page says so
        // on its own, and there is nothing here to keep up to date
        if (!this.hasCanvasTarget) {
            return;
        }

        // what every number of the report is called, formatted and worth - rendered into the page by
        // `MetricCatalog`, so nothing about a metric is written down twice
        this.catalog = catalogFrom(this.element.dataset.catalog);
        this.defaultMetric = [...this.catalog.values()].find((metric) => metric.default).slug;
        // what the chart can be read in, in the order it tabs them, and which of those tabs is open
        this.metrics = this.picked();
        this.active = this.metricsTarget.dataset.active;
        // what the page was rendered with; everything picked later is fetched from the JSON route once
        this.graphs = new Map();
        // the raw phploc output of every release that was opened, which never changes once measured
        this.raws = new Map();
        // and the same for the measurement behind it, read out through the catalog
        this.measurements = new Map();
        // Turbo keeps the page as it looks when it is left, and an open dialog would come back open -
        // and then not modal, since only showModal() puts one in the top layer
        this.closeRawBeforeCache = () => this.closeRaw();
        document.addEventListener('turbo:before-cache', this.closeRawBeforeCache);
        JSON.parse(this.element.dataset.repositories).forEach((graph) => {
            this.graphs.set(graph.name, graph);
        });

        // `<site> - <headline>`, so what is in front of the last dash is the part that stays
        this.site = document.title.split(' - ').slice(0, -1).join(' - ');
        this.renders = 0;
        // the lines that can be read release by release, and which one of them is being read
        this.series = [];
        this.repository = null;
        this.releaseIndex = null;
        this.measurementOpen = false;
        // the lines the chart was last drawn from, so switching a tab is a redraw of what is already here
        this.drawn = [];
        this.initChart();
        this.render();

        /*
         * A chart measures its own axis out of the width of its widest tick, and it draws before the
         * webfont it will be set in has arrived - so a scale of hundreds of thousands is laid out in the
         * fallback and comes back too narrow for its own numbers. Redrawing once the font is there is the
         * whole fix; it costs one update and only ever happens once.
         */
        document.fonts?.ready.then(() => this.chart?.update('none'));
    }

    disconnect() {
        this.chart?.destroy();
        this.chart = null;

        if (this.closeRawBeforeCache) {
            document.removeEventListener('turbo:before-cache', this.closeRawBeforeCache);
        }
    }

    initChart() {
        Chart.register(zoomPlugin);

        this.chart = new Chart(this.canvasTarget, {
            type: 'line',
            data: { datasets: [] },
            options: {
                maintainAspectRatio: false,
                /*
                 * No animation. A line rising out of the floor is a thing the chart does, not a thing the
                 * data does - fifteen years of releases were not on their way up, they were there when
                 * the page was. It is the same statement the tabs above it make: switching one is a
                 * redraw of numbers the page already has, and a chart catching up with itself reads as
                 * if it were fetching something.
                 */
                animation: false,
                interaction: { mode: 'nearest', intersect: false },
                // whatever the tooltip is pointing at is what a click reads below the chart, so the
                // point that answers the click is the one the pointer already named
                onClick: (event, elements) => this.pickPoint(elements),
                onHover: (event, elements) => {
                    this.canvasTarget.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                },
                plugins: {
                    /*
                     * The chips on the top edge of the panel are the legend: a line per chip, in the
                     * colour it is drawn in, and they are there whether the chart holds five lines or
                     * fifty. A second one under the chart would say the same thing twice and take the
                     * height the chart is drawn in to do it.
                     */
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: INK,
                        padding: 10,
                        cornerRadius: 6,
                        displayColors: false,
                        titleFont: { family: SANS, size: 12, weight: '600' },
                        bodyFont: { family: MONO, size: 12 },
                        callbacks: {
                            title: (items) => `${items[0].dataset.label} ${items[0].raw.name}`,
                            // whichever number the lines are drawn as, named the way its tab names it
                            label: (item) =>
                                `${this.primary().label}: ${format(this.primary(), item.raw.values[this.active])}`,
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
                        ticks: {
                            color: TICK,
                            font: { family: MONO, size: 11 },
                            padding: 8,
                            // the axis is written the way its metric is written - three decimals for a
                            // complexity per line, none for a count of half a million
                            callback: (tick) => format(this.primary(), tick),
                        },
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

    /**
     * The picker on the top edge of the panel is a box of its own and says what it holds by changing the
     * select it is a widget for - which is also what the chart was rendered with, so adding a line and
     * opening the page on it are the same thing to everything below.
     */
    repick() {
        this.render(true);
    }

    /**
     * The same for the row under it: however a metric got into the chart or out of it - typed into the
     * box, dropped from its tab, picked out of the measurement table under the chart - it is one event.
     */
    remetric() {
        this.render(true);
    }

    /**
     * What the select behind the tab row says the chart can be read in, in the order it tabs them.
     */
    picked() {
        return this.hasMetricsTarget
            ? [...this.metricsTarget.selectedOptions]
                  .map((option) => option.value)
                  .filter((slug) => this.catalog.has(slug))
            : [];
    }

    metricOf(slug) {
        return this.catalog.get(slug);
    }

    /**
     * The metric the chart is open on: what its lines are drawn as, what a release is listed under in
     * the select below it, and what the figures over the release analysis are read in.
     */
    primary() {
        return this.metricOf(this.active);
    }

    /**
     * Drawing a metric that is not drawn yet, from somewhere other than the box - the measurement table
     * under the chart. It writes to the same select, so the box and the chart both hear about it.
     */
    drawMetric(slug) {
        const option = [...this.metricsTarget.options].find((entry) => entry.value === slug);

        if (!option || option.selected) {
            return;
        }

        option.selected = true;
        option.setAttribute('selected', 'selected');
        // a pick goes to the end, which is where its tab goes - and the tab that was just added is the
        // one the chart opens on, see render()
        this.metricsTarget.append(option);
        this.metricsTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Switching a tab. The years stay where the reader left them - that is the whole reason every metric
     * is drawn in the same frame - while a zoom on the other axis goes, because it belonged to the number
     * that was being read and says nothing about the next one.
     */
    selectMetric(slug) {
        if (slug === this.active || !this.metrics.includes(slug)) {
            return;
        }

        this.active = slug;
        this.years = this.chart.isZoomedOrPanned()
            ? { min: this.chart.scales.x.min, max: this.chart.scales.x.max }
            : null;
        this.chart.resetZoom('none');
        this.syncUrl(this.drawn.map((graph) => graph.name));
        this.draw();
        this.renderMetricTabs();
        // the release analysis is read in what the chart shows, so it follows the tab
        this.showRepository(this.repository, this.releaseIndex);
    }

    /**
     * Taking a metric out of the chart - from its tab, which is where it is switched to as well. A chart
     * is always drawn as something, so the last tab carries no button at all.
     */
    dropMetric(slug) {
        const option = [...this.metricsTarget.options].find((entry) => entry.value === slug);

        if (!option || this.metrics.length < 2) {
            return;
        }

        option.selected = false;
        option.removeAttribute('selected');
        this.metricsTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    async render(picked = false) {
        const run = ++this.renders;
        const slugs = this.hasSelectTarget ? [...this.selectTarget.selectedOptions].map((option) => option.value) : [];
        const before = this.metrics;

        this.metrics = this.picked();

        /*
         * Adding a metric is asking to see it - whether it was typed into the box or clicked in the
         * measurement table, what was just added is what the chart opens on. It is the same rule as
         * further down for a metric that was taken out: a tab row answers for the tab that is open.
         */
        const added = this.metrics.filter((slug) => !before.includes(slug));

        if (added.length > 0) {
            this.active = added[added.length - 1];
        }

        if (picked) {
            this.syncUrl(slugs);
        }

        const graphs = await Promise.all(slugs.map((slug) => this.load(slug)));

        // a slower request must not overwrite what a later pick already drew
        if (run !== this.renders || !this.chart) {
            return;
        }

        // a metric can be taken out of the chart while its tab is the one open
        if (!this.metrics.includes(this.active)) {
            this.active = this.metrics[0];
        }

        this.drawn = graphs;
        this.draw();
        this.renderMetricTabs();
        this.renderHeadline(slugs);
        this.renderCount(graphs);
        this.renderTabs(graphs);
    }

    /**
     * The lines, drawn as whatever the open tab reads them in. Every metric of the chart travels with
     * every release, so this is the whole of switching one: no request, and the same points.
     */
    draw() {
        this.metricAboutTarget.textContent = this.primary().about;

        this.chart.data.datasets = this.drawn.map((graph, index) => ({
            label: graph.name,
            // the releases themselves, whichever of their numbers is being drawn - so a point stays the
            // release it is, and clicking one still opens it in the analysis below
            data: graph.tags,
            parsing: { xAxisKey: 'x', yAxisKey: `values.${this.active}` },
            fill: false,
            borderWidth: 1.75,
            pointRadius: 2.5,
            pointHoverRadius: 5,
            tension: 0.15,
            ...stroke(index),
        }));

        this.chart.update();

        // a tab switch keeps the years it was left on - see selectMetric()
        if (this.years) {
            this.chart.zoomScale('x', this.years, 'none');
            this.years = null;
        }

        this.reflectZoom();
    }

    /**
     * The metrics of this chart, as the tabs they are read through - the same underline tabs the release
     * analysis picks a repository with, since it is the same act: one thing at a time, in one place. They
     * stand on the hairline the chart hangs under, so the tab that is open underlines the frame it draws
     * in.
     *
     * Unlike the repositories below, a single one of them is still a tab: with nothing to switch to it
     * is not a choice but a label, and it is the only place the chart says which number it is drawing.
     */
    renderMetricTabs() {
        if (!this.hasMetricTabsTarget) {
            return;
        }

        this.metricTabsTarget.replaceChildren(
            ...this.metrics.map((slug) => {
                const metric = this.metricOf(slug);
                const tab = element('button', 'tab');

                tab.type = 'button';
                tab.title = metric.about;
                tab.setAttribute('role', 'tab');
                tab.setAttribute('aria-controls', 'chart-canvas');
                tab.setAttribute('aria-selected', slug === this.active ? 'true' : 'false');
                tab.append(element('span', null, metric.label));
                tab.addEventListener('click', () => this.selectMetric(slug));

                // a chart is always drawn as something, so the last tab is not one that can be closed
                if (this.metrics.length > 1) {
                    const drop = element('button', 'tab__drop', '×');

                    drop.type = 'button';
                    drop.setAttribute('aria-label', `Take ${metric.label} out of this chart`);
                    drop.addEventListener('mousedown', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        this.dropMetric(slug);
                    });
                    tab.append(drop);
                }

                return tab;
            }),
        );
    }

    /**
     * What the chart is made of, on the strip under it - which is the one place the page counts what it
     * drew rather than what it was opened with, since a line can be added and taken out without it.
     */
    renderCount(graphs) {
        if (!this.hasCountTarget) {
            return;
        }

        const releases = graphs.reduce((total, graph) => total + graph.tags.length, 0);

        this.countTarget.textContent = [
            `${count(graphs.length)} ${1 === graphs.length ? 'repository' : 'repositories'}`,
            `${count(releases)} ${1 === releases ? 'release' : 'releases'}`,
        ].join(' · ');
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
     * page reads on load, repositories and metrics alike. Only after a pick: opening the default chart
     * leaves its url alone.
     */
    syncUrl(slugs) {
        const params = new URLSearchParams(window.location.search);

        if (slugs.length > 0) {
            params.set('repositories', slugs.join(','));
        } else {
            params.delete('repositories');
        }

        // the chart the report is about carries no metric at all
        if (1 === this.metrics.length && this.defaultMetric === this.metrics[0]) {
            params.delete('metrics');
        } else {
            params.set('metrics', this.metrics.join(','));
        }

        // and which tab is open is only worth writing down when it is not the one a link opens on
        if (this.active === this.metrics[0]) {
            params.delete('metric');
        } else {
            params.set('metric', this.active);
        }

        // `symfony/console,laravel/framework` is what this was typed as, and what it should stay
        const query = params.toString().replace(/%2C/g, ',').replace(/%2F/g, '/');

        window.history.replaceState({}, '', query ? `?${query}` : window.location.pathname);
    }

    /**
     * A line of the chart, in the numbers the chart is currently drawn as. A repository that was never
     * picked before is fetched whole; one that is already drawn is asked only for the metric that was
     * just added, and what it already carries is kept.
     */
    async load(slug) {
        const known = this.graphs.get(slug);
        const missing = this.metrics.filter((metric) => !known || !known.metrics.includes(metric));

        if (known && 0 === missing.length) {
            return known;
        }

        // the slug is the whole route, so a relative request keeps working under a deployed sub path
        const response = await fetch(`${slug}?metrics=${(known ? missing : this.metrics).join(',')}`, {
            headers: { Accept: 'application/json' },
        });
        // the route answers with the line itself, the same shape the page was rendered with
        const graph = await response.json();

        if (known) {
            const carried = new Map(known.tags.map((tag) => [tag.name, tag.values]));

            // the answer is the newest release list, so it leads and what was already read joins it
            graph.tags.forEach((tag) => Object.assign(tag.values, carried.get(tag.name)));
            graph.metrics = [...new Set([...known.metrics, ...graph.metrics])];
        }

        this.graphs.set(slug, graph);

        return graph;
    }

    /**
     * The measurement below the chart is one tab per line: every repository in the chart can be read
     * release by release, not only the one the page was opened with. The tab carries the colour its
     * series is drawn in, so a line and its numbers are found by the same swatch.
     *
     * A single line is still a tab, the way a single metric is: with nothing to switch to it is not a
     * choice but a label, and the rail would otherwise say nothing about whose release is being read.
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

        // whoever was being read stays it, as long as its line is still in the chart
        const read = this.series.some((graph) => graph.name === this.repository)
            ? this.repository
            : this.series[0].name;

        this.showRepository(read, this.releaseIndex);
    }

    selectRepository(event) {
        this.showRepository(event.currentTarget.dataset.name);
    }

    /**
     * Reading a repository starts at its newest release, unless the reader said which one - clicking a
     * point in the chart does, and so does a redraw of the release that was already open.
     */
    showRepository(name, index = null) {
        const graph = this.series.find((entry) => entry.name === name);

        if (!graph) {
            return;
        }

        const metric = this.primary();

        this.repository = name;

        [...this.releaseTabsTarget.children].forEach((tab) => {
            const active = tab.dataset.name === name;

            tab.setAttribute('aria-selected', active ? 'true' : 'false');

            if (active) {
                this.releasePanelTarget.setAttribute('aria-labelledby', tab.id);
            }
        });

        this.release = graph;

        // where the repository being read is read on github.com - the headline used to carry this link,
        // and the release rail is where what is being read is now said
        if (this.hasReleaseLinkTarget) {
            this.releaseLinkTarget.href = graph.url;
            this.releaseLinkTarget.title = graph.url.replace('https://', '');
            this.releaseLinkTarget.setAttribute('aria-label', `${graph.name} on GitHub`);
        }

        this.releaseSelectTarget.replaceChildren();

        graph.tags.forEach((tag, position) => {
            // the release, and what it stands at in whatever the chart is open on
            const option = element('option', null, `${tag.name}  ·  ${format(metric, tag.values[metric.slug])}`);

            option.value = String(position);
            // newest first, the way the report is read
            this.releaseSelectTarget.prepend(option);
        });

        this.showRelease(null === index || index >= graph.tags.length ? graph.tags.length - 1 : index);
    }

    selectRelease(event) {
        this.showRelease(Number(event.currentTarget.value));
    }

    showRelease(index) {
        const tags = this.release.tags;
        const tag = tags[index];
        const previous = index > 0 ? tags[index - 1] : undefined;
        const first = tags[0];
        const metric = this.primary();

        // what a redraw comes back to, so switching a tab does not jump the reader to the newest release
        this.releaseIndex = index;
        this.releaseSelectTarget.value = String(index);

        this.renderFigures(tag, previous, first, tags);

        this.analysisTarget.replaceChildren(
            ...[
                group('This release', [
                    row('Released', day(tag.date)),
                    ...this.metrics.map((slug) => {
                        const drawn = this.metricOf(slug);

                        return row(drawn.label, value(drawn, tag.values[slug]));
                    }),
                ]),
                tag !== first ? this.comparison(`Since ${first.name}`, tag, first) : null,
                // where this release stands among the others, and what the repository behind them is
                group(`All ${tags.length} releases`, [
                    ...this.extremes(metric, tags),
                    row('Stars on GitHub', stars(this.release.stars)),
                ]),
            ].filter(Boolean),
        );

        // the release the measurement and the raw output are read for
        this.tag = tag;
        this.reflectMeasurement();
    }

    /**
     * What the release comes down to, before the panel below spells it out - the first three of them read
     * in whichever metric the chart is open on, since that is the one being looked at. The other tabs
     * answer the same three questions by being switched to.
     *
     * The date a release carries is the day it was tagged, not the day the report measured it - that
     * happened whenever the repository was submitted or a nightly scan picked the release up, which is
     * not what anyone reading a release is after.
     */
    renderFigures(tag, previous, first, tags) {
        const metric = this.primary();
        const measured = tag.values[metric.slug];
        const step = previous ? this.delta(metric, tag, previous) : null;
        const since = tag === first ? null : this.delta(metric, tag, first);

        this.headlineFiguresTarget.replaceChildren(
            headline(
                metric.label,
                value(metric, measured),
                // the dot in front of a complexity carries its band as a colour, so the note names it;
                // every other number is a count, and what it is read against is when it was counted
                metric.level && null !== measured && undefined !== measured ? band(measured) : day(tag.date),
            ),
            null === step
                ? headline('vs the release before', '–', 'nothing was measured before it')
                : headline(
                      `vs ${previous.name}`,
                      this.movement(metric, step),
                      // only where the report has an opinion about a direction is a colour worth
                      // explaining; everywhere else the reading is how much of the number moved
                      'lower' === metric.direction
                          ? 'down is an improvement'
                          : (this.portion(step, previous.values[metric.slug]) ?? 'as phploc counts it'),
                      direction(metric, step),
                  ),
            null === since
                ? headline('Since the first release', '–', 'this is the first one')
                : headline(
                      `Since ${first.name}`,
                      this.movement(metric, since),
                      this.portion(since, first.values[metric.slug]) ?? day(first.date),
                      direction(metric, since),
                  ),
            headline('Analysed releases', count(tags.length), `first one ${day(first.date)}`),
        );
    }

    /**
     * A change as a plain figure. The arrow and the chip belong to a row of the panel below, where a
     * number is one line of a list; up here the figure is the whole cell and its colour says as much.
     */
    movement(metric, delta) {
        return `${delta > 0 ? '+' : delta < 0 ? '−' : '±'}${format(metric, Math.abs(delta))}`;
    }

    /**
     * How much of the number that change was. A share is written as a magnitude wherever it stands next
     * to what it is a share of - here it stands under a figure that is itself a change, so it carries
     * the same sign, or it would read as a rise under a fall.
     */
    portion(delta, whole) {
        const measured = share(delta, whole);

        return undefined === measured ? undefined : `${delta < 0 ? '−' : '+'}${measured}`;
    }

    /**
     * Where this release stands among the others, in the metric the chart is open on - with the release
     * that holds the record next to it, since that is what makes it a place rather than a number.
     */
    extremes(metric, tags) {
        const measured = tags
            .map((tag) => tag.values[metric.slug])
            .filter((entry) => null !== entry && undefined !== entry);

        if (0 === measured.length) {
            return [];
        }

        const lowest = Math.min(...measured);
        const highest = Math.max(...measured);
        // only the first letter, so `Lines of code (LOC)` keeps the acronym it carries
        const named = metric.label.charAt(0).toLowerCase() + metric.label.slice(1);
        const held = (by) => tags.find((tag) => tag.values[metric.slug] === by)?.name;

        return [
            row(`Lowest ${named}`, value(metric, lowest), held(lowest)),
            row(`Highest ${named}`, value(metric, highest), held(highest)),
        ];
    }

    /**
     * The release against another one, in every number the chart is drawn as - a percentage next to it
     * wherever there is something to be a percentage of.
     */
    comparison(title, tag, other) {
        return group(
            title,
            this.metrics.map((slug) => {
                const metric = this.metricOf(slug);
                const delta = this.delta(metric, tag, other);

                return null === delta
                    ? row(metric.label, '—')
                    : row(metric.label, change(metric, delta), share(delta, other.values[slug]));
            }),
        );
    }

    delta(metric, tag, other) {
        const measured = tag.values[metric.slug];
        const before = other.values[metric.slug];

        if (null === measured || undefined === measured || null === before || undefined === before) {
            return null;
        }

        return Number((measured - before).toFixed(metric.decimals));
    }

    /**
     * Everything phploc counted for this release, which is sixty-two numbers of which the chart draws a
     * handful. It is fetched for the release that is open rather than carried by the page, and kept
     * afterwards - a measurement does not change once it was taken.
     */
    toggleMeasurement() {
        this.measurementOpen = !this.measurementOpen;
        this.reflectMeasurement();
    }

    reflectMeasurement() {
        if (!this.hasMeasurementTarget) {
            return;
        }

        this.measurementToggleTarget.setAttribute('aria-expanded', this.measurementOpen ? 'true' : 'false');
        this.measurementTarget.hidden = !this.measurementOpen;

        if (this.measurementOpen) {
            this.renderMeasurement();
        }
    }

    async renderMeasurement() {
        const release = this.release;
        const tag = this.tag;
        // the metrics are part of it: what the chart already draws is marked in the table, so picking
        // one out of the table is a row of it changing
        const key = `${release.name}@${tag.name}|${this.metrics.join(',')}`;

        if (this.measurementKey === key) {
            return;
        }

        this.measurementKey = key;
        this.measurementTarget.replaceChildren(element('p', 'measurement__note', 'Reading the measurement…'));

        const measurement = await this.loadMeasurement(release.name, tag.name);

        // reading is not instant, and another release can be opened meanwhile
        if (this.tag !== tag || !this.measurementOpen) {
            return;
        }

        if (!measurement) {
            this.measurementKey = null;
            this.measurementTarget.replaceChildren(
                element('p', 'measurement__note', 'The measurement of this release could not be read.'),
            );

            return;
        }

        this.measurementTarget.replaceChildren(...this.measurementGroups(measurement));
    }

    /**
     * The measurement in the sections phploc prints it in, every number under the one it is a part of,
     * and against the release before it - which is the whole difference between this and the raw
     * output next to it: the same numbers, read rather than reprinted.
     */
    measurementGroups(measurement) {
        const sections = new Map();

        this.catalog.forEach((metric) => {
            if (!sections.has(metric.group)) {
                sections.set(metric.group, { label: metric.groupLabel, about: metric.groupAbout, rows: [] });
            }

            sections.get(metric.group).rows.push(this.measurementRow(metric, measurement));
        });

        return [...sections.values()].map((section) => {
            const node = element('div', 'measurement__group');

            node.append(
                element('div', 'analysis__title', section.label),
                element('p', 'measurement__about', section.about),
                list(section.rows),
            );

            return node;
        });
    }

    measurementRow(metric, measurement) {
        const measured = measurement.values[metric.slug] ?? null;
        const before = measurement.previous?.values[metric.slug] ?? null;
        const label = element('dt', 'analysis__label');
        const pick = element('button', 'measurement__pick', metric.label);
        const definition = element('span', 'measurement__numbers');

        pick.type = 'button';
        pick.title = `${metric.about} - click to draw it`;
        pick.disabled = this.metrics.includes(metric.slug);
        pick.addEventListener('click', () => this.drawMetric(metric.slug));
        label.style.paddingLeft = `calc(var(--space-4) * ${metric.depth})`;
        label.append(pick);

        if (null !== measured && null !== before) {
            definition.append(change(metric, Number((measured - before).toFixed(metric.decimals))));
        }

        definition.prepend(value(metric, measured));

        return row(label, definition, percent(measurement.shares[metric.slug]));
    }

    async loadMeasurement(name, tag) {
        const key = `${name}@${tag}`;

        if (!this.measurements.has(key)) {
            // relative, like the routes above, so it keeps working under a deployed sub path
            const path = `${name}/${encodeURIComponent(tag)}/metrics`;

            try {
                const response = await fetch(path, { headers: { Accept: 'application/json' } });

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                this.measurements.set(key, await response.json());
            } catch {
                // not cached: a failure is worth trying again, unlike a measurement
                return null;
            }
        }

        return this.measurements.get(key);
    }

    /**
     * The whole measurement of the release being read, as the phploc command line prints it. Fetched
     * when it is asked for rather than carried by the page - a chart holds hundreds of releases and this
     * is opened for one - and kept afterwards, since what a tag measured as does not change again.
     */
    async openRaw() {
        const release = this.release;
        const tag = this.tag;

        this.rawTitleTarget.textContent = `${release.name} ${tag.name}`;
        this.rawOutputTarget.textContent = 'Reading the measurement…';
        this.rawTarget.showModal();

        const output = await this.loadRaw(release.name, tag.name);

        // reading is not instant, and the dialog can be closed and reopened on another release meanwhile
        if (this.tag === tag && this.rawTarget.open) {
            this.rawOutputTarget.textContent = output;
        }
    }

    async loadRaw(name, tag) {
        const key = `${name}@${tag}`;

        if (!this.raws.has(key)) {
            // relative, like the JSON route above, so it keeps working under a deployed sub path
            const path = `${name}/${encodeURIComponent(tag)}/raw`;

            try {
                const response = await fetch(path, { headers: { Accept: 'text/plain' } });

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                this.raws.set(key, await response.text());
            } catch {
                // not cached: a failure is worth trying again, unlike a measurement
                return 'The measurement of this release could not be read.';
            }
        }

        return this.raws.get(key);
    }

    /**
     * A modal dialog fills its backdrop with itself, so a click that lands on the dialog element rather
     * than on anything in it is a click next to it - which closes it, the way Escape does.
     */
    dismissRaw(event) {
        if (event.target === this.rawTarget) {
            this.closeRaw();
        }
    }

    closeRaw() {
        if (this.hasRawTarget && this.rawTarget.open) {
            this.rawTarget.close();
        }
    }
}
