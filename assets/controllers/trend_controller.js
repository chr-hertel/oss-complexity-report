import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';
import 'chartjs-adapter-moment';
import moment from 'moment';

/*
 * The time frames the hero figure can be read in, and the line it took to get there. Like the rankings
 * below, every window is rendered with the request - picking one swaps which figure is visible and
 * redraws the line from what the page already carries, so nothing is fetched.
 *
 * The line is the same statement as the number above it: a point is the average complexity of the
 * libraries that window compares, each of them standing at the last release it had by then. It starts
 * on the `from` of the figure and ends on its `to`, which is why it is computed where the figure is
 * (`TrendCalculator`) rather than assembled here.
 */

// The hero is the one place the report draws on its brand ground, so the chart is inked for it: the
// line is white and everything quiet a wash of it, where the tokens are ink on white.
const LINE = '#ffffff';
const FILL = 'rgba(255, 255, 255, 0.1)';
const TICK = 'rgba(255, 255, 255, 0.6)';
const GRID = 'rgba(255, 255, 255, 0.14)';
const AXIS = 'rgba(255, 255, 255, 0.22)';
const INK = '#101720';
const MONO = 'IBM Plex Mono';
const SANS = 'IBM Plex Sans';

export default class extends Controller {
    static targets = ['tab', 'panel', 'canvas'];
    static values = { series: Array };

    connect() {
        this.window = 0;
        this.draw();
    }

    disconnect() {
        this.chart?.destroy();
        this.chart = null;
    }

    select(event) {
        const index = this.tabTargets.indexOf(event.currentTarget);

        if (index < 0) {
            return;
        }

        this.tabTargets.forEach((tab, position) => {
            tab.setAttribute('aria-selected', position === index ? 'true' : 'false');
        });

        this.panelTargets.forEach((panel, position) => {
            panel.hidden = position !== index;
        });

        this.window = index;
        this.draw();
    }

    points() {
        return this.seriesValue[this.window]?.points ?? [];
    }

    draw() {
        if (!this.hasCanvasTarget) {
            return;
        }

        if (this.chart) {
            this.chart.data.datasets[0].data = this.points();
            this.chart.update();

            return;
        }

        this.chart = new Chart(this.canvasTarget, {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: 'Ø complexity',
                        data: this.points(),
                        borderColor: LINE,
                        backgroundColor: FILL,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        tension: 0.25,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                // one line, so what is being pointed at is a day of it rather than a point near the cursor
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: INK,
                        padding: 10,
                        cornerRadius: 6,
                        displayColors: false,
                        titleFont: { family: SANS, size: 12, weight: '600' },
                        bodyFont: { family: MONO, size: 12 },
                        callbacks: {
                            title: (items) => moment(items[0].raw.x, 'YYYY-MM-DD').format('LL'),
                            label: (item) => `Ø ${Number(item.raw.y).toFixed(2)}`,
                        },
                    },
                },
                scales: {
                    x: {
                        type: 'time',
                        time: { parser: 'YYYY-MM-DD' },
                        border: { color: AXIS },
                        grid: { display: false },
                        ticks: { color: TICK, font: { family: MONO, size: 11 }, maxTicksLimit: 8 },
                    },
                    y: {
                        border: { display: false },
                        grid: { color: GRID },
                        ticks: { color: TICK, font: { family: MONO, size: 11 }, padding: 8, maxTicksLimit: 5 },
                    },
                },
            },
        });
    }
}
