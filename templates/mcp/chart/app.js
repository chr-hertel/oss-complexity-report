// The palette of the chart page: eight colours, and every pass through them stroked differently,
// so a ninth line shares its colour but not its look.
const SERIES_COLORS = ['#2f3a49', '#c05b4d', '#b58a3c', '#4e8c7d', '#4a6fa5', '#7c6ba0', '#7a9a4e', '#a0a8b4'];
const SERIES_DASHES = [[], [6, 3], [2, 3]];
const seriesColor = (index) => SERIES_COLORS[index % SERIES_COLORS.length];
const seriesDash = (index) => SERIES_DASHES[Math.floor(index / SERIES_COLORS.length) % SERIES_DASHES.length];
const GRID = '#e3e7ec';
const TICK = '#5a6675';

const state = { series: [], metrics: [], active: null, catalog: [] };
let chart = null;

const el = (id) => document.getElementById(id);
const metricOf = (slug) => state.catalog.find((entry) => entry.slug === slug);
const fmt = (value, slug) => {
    const decimals = (metricOf(slug || state.active) || {}).decimals || 0;
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
};

// The host delivers the complexity_chart tool result here; the pickers call the same hook with
// what they fetched themselves.
function render(model) {
    if (!model || typeof model !== 'object' || !Array.isArray(model.series)) return;
    state.series = model.series;
    state.metrics = model.metrics && model.metrics.length ? model.metrics : ['complexity'];
    if (Array.isArray(model.catalog) && model.catalog.length) state.catalog = model.catalog;
    if (!state.metrics.includes(state.active)) state.active = state.metrics[0];
    redraw();
}

function redraw() {
    drawChips();
    drawTabs();
    drawChart();
    drawFoot();
}

// --- The chips are the legend: a line per chip in the colour it is drawn in -------------------
function drawChips() {
    const chips = el('chips');
    chips.innerHTML = '';
    state.series.forEach((graph, index) => {
        const chip = document.createElement('li');
        chip.className = 'chip';
        const swatch = document.createElement('span');
        swatch.className = 'chip__swatch';
        swatch.style.background = seriesColor(index);
        const name = document.createElement('span');
        name.textContent = graph.name;
        const drop = document.createElement('button');
        drop.className = 'chip__drop';
        drop.type = 'button';
        drop.textContent = '×';
        drop.setAttribute('aria-label', 'Remove ' + graph.name);
        drop.addEventListener('click', () => {
            state.series.splice(index, 1);
            redraw();
        });
        chip.append(swatch, name, drop);
        chips.append(chip);
    });
}

// --- One tab per metric; the open one underlines the frame it draws in ------------------------
function drawTabs() {
    const tabs = el('metric-tabs');
    tabs.innerHTML = '';
    state.metrics.forEach((slug) => {
        const metric = metricOf(slug);
        const tab = document.createElement('button');
        tab.className = 'tab' + (slug === state.active ? ' is-active' : '');
        tab.type = 'button';
        tab.setAttribute('role', 'tab');
        tab.textContent = metric ? metric.label : slug;
        tab.addEventListener('click', () => {
            state.active = slug;
            drawTabs();
            drawChart();
            drawFoot();
        });
        if (state.metrics.length > 1) {
            const drop = document.createElement('span');
            drop.className = 'tab__drop';
            drop.textContent = '×';
            drop.addEventListener('click', (event) => {
                event.stopPropagation();
                state.metrics = state.metrics.filter((entry) => entry !== slug);
                if (state.active === slug) state.active = state.metrics[0];
                redraw();
            });
            tab.append(drop);
        }
        tabs.append(tab);
    });
}

function drawChart() {
    const empty = el('empty');
    if (typeof Chart === 'undefined') {
        empty.hidden = false;
        empty.textContent = 'The chart library could not be loaded in this host.';
        return;
    }
    empty.hidden = state.series.length > 0;
    if (!state.series.length) empty.textContent = 'No measured repository picked - add a line above.';

    const datasets = state.series.map((graph, index) => ({
        label: graph.name,
        data: graph.tags
            .filter((tag) => tag.values[state.active] !== null && tag.values[state.active] !== undefined)
            .map((tag) => ({ x: Date.parse(tag.date), y: tag.values[state.active], name: tag.name, date: tag.date })),
        borderColor: seriesColor(index),
        backgroundColor: seriesColor(index),
        borderDash: seriesDash(index),
        borderWidth: 1.5,
        pointRadius: 2,
        pointHoverRadius: 4,
        pointHitRadius: 8,
        tension: 0,
    }));

    if (chart) chart.destroy();
    chart = new Chart(el('canvas'), {
        type: 'line',
        data: { datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            // there is no chart.js legend at all: the chips on the top edge are the legend
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => items[0].raw.name + ' · ' + items[0].raw.date,
                        label: (context) => context.dataset.label + ': ' + fmt(context.raw.y),
                    },
                },
            },
            scales: {
                x: {
                    type: 'linear',
                    grid: { color: GRID },
                    ticks: {
                        color: TICK,
                        maxTicksLimit: 10,
                        callback: (value) => new Date(value).getFullYear(),
                    },
                },
                y: {
                    grid: { color: GRID },
                    ticks: { color: TICK, callback: (value) => fmt(value) },
                },
            },
        },
    });
}

function drawFoot() {
    const metric = metricOf(state.active);
    el('about').textContent = metric ? metric.about : '';
    const releases = state.series.reduce((total, graph) => total + graph.tags.length, 0);
    el('count').textContent = state.series.length + (state.series.length === 1 ? ' repository' : ' repositories')
        + ' · ' + releases.toLocaleString('en-US') + (releases === 1 ? ' release' : ' releases');
}

// --- Menus: rows under a field, picked on mousedown before the blur can close them ------------
function openMenu(menu, rows, onPick) {
    menu.innerHTML = '';
    menu._active = -1;
    menu._onPick = onPick;
    rows.forEach((row) => {
        const item = document.createElement('li');
        item.className = 'menu__row';
        item._value = typeof row === 'string' ? row : row.slug;
        if (typeof row === 'string') {
            item.textContent = row;
        } else {
            item.textContent = row.label;
            const about = document.createElement('small');
            about.textContent = row.group + ' · ' + row.about;
            item.append(about);
        }
        item.addEventListener('mousedown', (event) => {
            event.preventDefault();
            onPick(item._value);
        });
        menu.append(item);
    });
    menu.hidden = rows.length === 0;
}

// Only the keyboard highlights a row - hovering is the pointer's own business.
function highlight(menu, index) {
    const items = [...menu.children];
    if (!items.length) return;
    menu._active = (index + items.length) % items.length;
    items.forEach((item, i) => item.classList.toggle('is-active', i === menu._active));
    items[menu._active].scrollIntoView({ block: 'nearest' });
}

function menuKeydown(menu) {
    return (event) => {
        if (menu.hidden) return;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            highlight(menu, menu._active + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            highlight(menu, menu._active - 1);
        } else if (event.key === 'Enter' && menu._active >= 0) {
            event.preventDefault();
            menu._onPick(menu.children[menu._active]._value);
        } else if (event.key === 'Escape') {
            menu.hidden = true;
        }
    };
}

// --- The repository picker asks the server what an input means ---------------------------------
let searchTimer = null;
const repoInput = el('repo-input');
const repoMenu = el('repo-menu');
repoInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const query = repoInput.value.trim();
    if (query.length < 2) { repoMenu.hidden = true; return; }
    searchTimer = setTimeout(async () => {
        try {
            const result = await callTool('chart_repository_search', { query });
            const names = extractModel(result) || [];
            const drawn = state.series.map((graph) => graph.name.toLowerCase());
            openMenu(repoMenu, names.filter((name) => !drawn.includes(name.toLowerCase())), addRepository);
        } catch { repoMenu.hidden = true; }
    }, 200);
});
repoInput.addEventListener('blur', () => { repoMenu.hidden = true; });
repoInput.addEventListener('keydown', menuKeydown(repoMenu));

async function addRepository(slug) {
    repoInput.value = '';
    repoMenu.hidden = true;
    try {
        const result = await callTool('chart_line', { repository: slug, metrics: state.metrics.join(',') });
        const graph = extractModel(result);
        if (graph && graph.name) {
            state.series.push(graph);
            redraw();
        }
    } catch { /* an unknown repository stays out, like a slug the page drops */ }
}

// --- The metric adder filters the catalog it already has: name, section and explanation --------
const metricInput = el('metric-input');
const metricMenu = el('metric-menu');
function metricRows() {
    const query = metricInput.value.trim().toLowerCase();
    return state.catalog
        .filter((metric) => !state.metrics.includes(metric.slug))
        .filter((metric) => !query
            || metric.label.toLowerCase().includes(query)
            || metric.groupLabel.toLowerCase().includes(query)
            || metric.about.toLowerCase().includes(query)
            || metric.slug.includes(query))
        .slice(0, 12)
        .map((metric) => ({ slug: metric.slug, label: metric.label, group: metric.groupLabel, about: metric.about }));
}
metricInput.addEventListener('input', () => openMenu(metricMenu, metricRows(), addMetric));
metricInput.addEventListener('focus', () => openMenu(metricMenu, metricRows(), addMetric));
metricInput.addEventListener('blur', () => { metricMenu.hidden = true; });
metricInput.addEventListener('keydown', menuKeydown(metricMenu));

// Every metric of a chart travels with every release, so a line already on the page is re-asked
// for the number it is missing - and the added tab opens, since picking a number means wanting
// to see it.
async function addMetric(slug) {
    metricInput.value = '';
    metricMenu.hidden = true;
    const metrics = [...state.metrics, slug];
    try {
        const lines = await Promise.all(state.series.map(async (graph) => {
            const result = await callTool('chart_line', { repository: graph.name, metrics: metrics.join(',') });
            return extractModel(result) || graph;
        }));
        state.series = lines;
        state.metrics = metrics;
        state.active = slug;
        redraw();
    } catch { /* the chart stays as it is */ }
}
