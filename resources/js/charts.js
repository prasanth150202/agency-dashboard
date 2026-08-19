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
