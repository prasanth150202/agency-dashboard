import {
    Chart,
    LineController,
    LineElement,
    BarController,
    BarElement,
    DoughnutController,
    ArcElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Filler,
} from 'chart.js';

Chart.register(
    LineController,
    LineElement,
    BarController,
    BarElement,
    DoughnutController,
    ArcElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Filler
);

Chart.defaults.font.family = 'Figtree, ui-sans-serif, system-ui, sans-serif';
Chart.defaults.color = '#6f6f7d';
Chart.defaults.borderColor = '#eeeef0';

window.Chart = Chart;

const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/**
 * A server-built trend chart. `config` comes straight from PHP:
 *  labels:   bucket labels
 *  series:   [{ key, label, kind: 'bar'|'line', axis: 'money'|'count', color, values: {CUR: [...]} | [...] }]
 *  currencies: currencies with money series (first is the default)
 *  tooltips: { CUR: [[ 'Revenue: $10.00', ... ], ...] } — pre-formatted per bucket
 * Money is formatted server-side, so the browser never does currency math.
 */
export function registerCharts(Alpine) {
    Alpine.data('trendChart', (config) => ({
        currency: config.currencies?.[0] ?? null,
        hidden: {},
        chart: null,

        init() {
            this.$nextTick(() => this.render());
        },

        destroy() {
            this.chart?.destroy();
        },

        toggle(key) {
            this.hidden[key] = !this.hidden[key];
            this.render();
        },

        setCurrency(currency) {
            this.currency = currency;
            this.render();
        },

        values(series) {
            return Array.isArray(series.values) ? series.values : (series.values?.[this.currency] ?? []);
        },

        render() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;

            const datasets = config.series
                .filter((s) => !this.hidden[s.key])
                .map((s) => ({
                    type: s.kind,
                    label: s.label,
                    data: this.values(s),
                    yAxisID: s.axis,
                    backgroundColor: s.kind === 'bar' ? s.color : `${s.color}1a`,
                    borderColor: s.color,
                    borderWidth: s.kind === 'bar' ? 0 : 2,
                    borderRadius: s.kind === 'bar' ? 4 : 0,
                    maxBarThickness: 22,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: 0.3,
                    order: s.kind === 'bar' ? 2 : 1,
                }));

            const hasMoney = datasets.some((d) => d.yAxisID === 'money');
            const hasCount = datasets.some((d) => d.yAxisID === 'count');
            const tooltips = config.tooltips?.[this.currency ?? '_'] ?? config.tooltips?._ ?? [];

            this.chart?.destroy();
            this.chart = new Chart(canvas, {
                data: { labels: config.labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: reducedMotion() ? false : { duration: 250 },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#171717',
                            padding: 10,
                            displayColors: false,
                            callbacks: {
                                title: (items) => items[0]?.label ?? '',
                                label: () => null,
                                afterBody: (items) => tooltips[items[0]?.dataIndex] ?? [],
                            },
                        },
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                        money: { display: hasMoney, position: 'left', beginAtZero: true, grid: { color: '#eeeef0' } },
                        count: {
                            display: hasCount,
                            position: hasMoney ? 'right' : 'left',
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { display: !hasMoney, color: '#eeeef0' },
                        },
                    },
                },
            });
        },
    }));

    Alpine.data('copyText', (text) => ({
        copied: false,
        copy() {
            navigator.clipboard?.writeText(text).then(() => {
                this.copied = true;
                setTimeout(() => (this.copied = false), 1800);
            });
        },
    }));
}
