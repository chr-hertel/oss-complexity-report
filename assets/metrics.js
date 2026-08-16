/*
 * How the report writes a measured number down.
 *
 * The catalog itself - what a number is called, which section it is printed under, how far it is
 * rounded, whether falling is an improvement - is rendered into the page by `MetricCatalog`, so none of
 * it is repeated here. What is here is the other half: turning one of those numbers, or the difference
 * between two of them, into the element it is read as.
 */
import { element } from './dom.js';

const ARROWS = { good: '↓', bad: '↑', flat: '→' };

/*
 * The risk bands of `ComplexityLevel`, mirrored because the release analysis is built here in the
 * browser rather than rendered. It is the one thing about a metric the catalog does not carry: four
 * numbers, and the band above the last of them.
 */
const LEVELS = [
    [10, 'simple', '1–10'],
    [20, 'moderate', '11–20'],
    [50, 'complex', '21–50'],
];
const UNTESTABLE = ['untestable', '> 50'];

const bandOf = (value) => LEVELS.find(([limit]) => value <= limit)?.slice(1) ?? UNTESTABLE;

export const level = (value) => bandOf(value)[0];

/**
 * Where a complexity stands, said in words - the note under the figure it belongs to, since the dot in
 * front of the figure carries the band as a colour but not as a name.
 */
export function band(measured) {
    const [name, range] = bandOf(measured);

    return element('span', `level level--${name}`, `${name} · ${range}`);
}

/**
 * Everything the page was told about the numbers it may draw, by the slug they are addressed under.
 */
export function catalogFrom(json) {
    return new Map(JSON.parse(json).map((metric) => [metric.slug, metric]));
}

const digits = (value, decimals) =>
    value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

/**
 * A measured number, written with the decimals its metric is read to - a count without any, an average
 * with two. A release that does not carry the number at all is a dash rather than a zero, because
 * those are different statements.
 */
export function format(metric, value) {
    return null === value || undefined === value ? '—' : digits(value, metric.decimals);
}

const sign = (value) => (value > 0 ? '+' : value < 0 ? '−' : '±');

export function signed(metric, delta) {
    return sign(delta) + digits(Math.abs(delta), metric.decimals);
}

/**
 * What a part is of its whole, which is the percentage phploc prints behind half of its numbers.
 */
export function share(part, total) {
    return total ? `${digits((Math.abs(part) / total) * 100, 1)}%` : undefined;
}

export function percent(value) {
    return undefined === value || null === value ? undefined : `${digits(value, 1)}%`;
}

/**
 * A measured value, carrying the dot of its risk band where the metric is one that is read against
 * them - the complexities, and nothing else.
 */
export function value(metric, measured) {
    if (!metric.level || null === measured || undefined === measured) {
        return element('span', null, format(metric, measured));
    }

    return element('span', `level level--${level(measured)}`, format(metric, measured));
}

/**
 * Which way a change goes, where the report has an opinion about it: complexity falling is an
 * improvement and complexity rising is a regression, while a library that grew by twenty thousand lines
 * did not thereby get better or worse - so a neutral metric has no direction at all, not a flat one.
 *
 * What counts as no movement is what the metric is written to: half of its last decimal.
 */
export function direction(metric, delta) {
    if ('lower' !== metric.direction) {
        return undefined;
    }

    return Math.abs(delta) < Math.pow(10, -metric.decimals) / 2 ? 'flat' : delta < 0 ? 'good' : 'bad';
}

/**
 * A change, coloured only where the report has an opinion about the direction.
 */
export function change(metric, delta, label) {
    if ('lower' !== metric.direction) {
        const node = element('span', 'trend trend--chip trend--flat');

        node.append(element('span', null, signed(metric, delta)));

        if (label) {
            node.append(element('span', 'trend__label', label));
        }

        return node;
    }

    const tone = direction(metric, delta);
    const node = element('span', `trend trend--chip trend--${tone}`);

    node.append(element('span', null, ARROWS[tone]), element('span', null, signed(metric, delta)));

    if (label) {
        node.append(element('span', 'trend__label', label));
    }

    return node;
}

export function row(label, measured, hint) {
    const node = element('div', 'analysis__row');
    const definition = element('dd', 'analysis__value');

    if (hint) {
        definition.append(element('span', 'analysis__share', hint));
    }

    definition.append(measured instanceof Node ? measured : element('span', null, String(measured)));
    node.append(label instanceof Node ? label : element('dt', 'analysis__label', label), definition);

    return node;
}

export function list(rows) {
    const node = element('dl', 'analysis__list');

    rows.filter(Boolean).forEach((entry) => node.append(entry));

    return node;
}

export function group(title, rows) {
    const node = element('div');

    node.append(element('div', 'analysis__title', title), list(rows));

    return node;
}
