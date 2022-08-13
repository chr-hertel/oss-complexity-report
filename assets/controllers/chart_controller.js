import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';
import 'chartjs-adapter-moment';
import zoomPlugin from 'chartjs-plugin-zoom';
import 'select2';
import $ from 'jquery';

export default class extends Controller {
    chartColors = {
        black: 'rgb(45,45,45)',
        red: 'rgb(255, 99, 132)',
        orange: 'rgb(255, 159, 64)',
        yellow: 'rgb(255, 205, 86)',
        green: 'rgb(75, 192, 192)',
        blue: 'rgb(54, 162, 235)',
        purple: 'rgb(153, 102, 255)',
        grey: 'rgb(201, 203, 207)',
    };
    colorNames = Object.keys(this.chartColors);
    chart;
    chartConfig;

    connect() {
        this.initChart(
            JSON.parse(this.element.dataset.libraries),
        );
        this.initSelectBox();
    }

    initChart(libraries) {

        const ctx = document.getElementById('canvas');
        this.chartConfig = {
            type: 'line',
            options: {
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: function (tooltipItem) {
                                return tooltipItem[0].dataset.label + ' ' + tooltipItem[0].raw.name;
                            },
                            label: function (tooltipItem) {
                                return 'Ø Complexity: ' + tooltipItem.formattedValue;
                            },
                        },
                    },
                    zoom: {
                        pan: {
                            enabled: true,
                        },
                        zoom: {
                            wheel: {
                                enabled: true,
                            },
                            pinch: {
                                enabled: true
                            },
                            mode: 'xy',
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            tooltipFormat: 'll',
                            parser: 'MM-DD-YYYY',
                        },
                    },
                    y: {
                        beginAtZero: true,
                    },
                },
            },
        };
        Chart.register(zoomPlugin);
        this.chart = new Chart(ctx, this.chartConfig);

        libraries.forEach(library => this.addLibrary(library['name'], library['tags']))

        this.chart.update();
    }

    initSelectBox() {
        const $select = $('.js-library-select');
        $select.select2();

        // register events
        $select.on('select2:select', this.disableSorting);
        $select.on('select2:select', this.selectLibrary.bind(this));
        $select.on('select2:unselect', this.removeLibrary.bind(this));
    }

    disableSorting(event) {
        const $element = $(event.params.data.element);

        $element.detach();
        $(this).append($element);
        $(this).trigger('change');
    }

    addLibrary(label, data) {
        const colorName = this.colorNames[this.chartConfig.data.datasets.length % this.colorNames.length];
        const nextColor = this.chartColors[colorName];
        const newDataset = {
            label: label,
            data: data,
            fill: false,
            pointRadius: 5,
            pointHoverRadius: 7,
            borderColor: nextColor,
        };

        this.chartConfig.data.datasets.push(newDataset);
    }

    selectLibrary(event) {
        const data = event.params.data;
        const self = this;
        $.ajax(data.id).done(function (response) {
            self.addLibrary(data.text, response);
            self.chart.update();
        });
    }

    removeLibrary(event) {
        const data = event.params.data;

        for (let index = 0; index < this.chartConfig.data.datasets.length; ++index) {
            if (data.text === this.chartConfig.data.datasets[index].label) {
                this.chartConfig.data.datasets.splice(index, 1);
                break;
            }
        }

        this.chart.update();
    }
}
