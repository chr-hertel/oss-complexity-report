import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';
import moment from 'moment';
import 'chartjs-adapter-moment';
import 'select2';
import $ from 'jquery';

export default class extends Controller {
    chartColors = {
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
            JSON.parse(this.element.dataset.labels),
            JSON.parse(this.element.dataset.tagData),
            this.element.dataset.mainLib
        );
        this.initSelectBox();
    }

    initChart(labels, tagData, mainLib) {
        const momentLabels = labels.forEach((label) => moment(label, 'MM-DD-YY'));

        const ctx = document.getElementById('canvas');
        this.chartConfig = {
            type: 'line',
            data: {
                labels: momentLabels,
                datasets: [
                    {
                        label: mainLib,
                        data: tagData,
                        fill: false,
                        borderColor: 'rgb(45,45,45)',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                    },
                ],
            },
            options: {
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: function (tooltipItem) {
                                return tooltipItem[0].raw.name;
                            },
                            label: function (tooltipItem) {
                                return 'Ø Complexity: ' + tooltipItem.formattedValue;
                            },
                        },
                    },
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
        this.chart = new Chart(ctx, this.chartConfig);
    }

    initSelectBox() {
        const $select = $('.js-library-select');
        $select.select2();

        // register events
        $select.on('select2:select', this.disableSorting);
        $select.on('select2:select', this.addLibrary.bind(this));
        $select.on('select2:unselect', this.removeLibrary.bind(this));
    }

    disableSorting(event) {
        const $element = $(event.params.data.element);

        $element.detach();
        $(this).append($element);
        $(this).trigger('change');
    }

    addLibrary(event) {
        const data = event.params.data;
        const colorName = this.colorNames[this.chartConfig.data.datasets.length % this.colorNames.length];
        const newColor = this.chartColors[colorName];
        const config = this.chartConfig;
        const chart = this.chart;
        $.ajax(data.id).done(function (response) {
            const newDataset = {
                label: data.text,
                data: response,
                fill: false,
                pointRadius: 5,
                pointHoverRadius: 7,
                borderColor: newColor,
            };

            config.data.datasets.push(newDataset);
            chart.update();
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
