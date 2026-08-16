<?php

declare(strict_types=1);

namespace App\ComplexityReport\Metric;

/**
 * The sections a measurement is read in - phploc's own, in phploc's order.
 *
 * The report does not invent a grouping of its own: a measurement is printed under these four headings
 * by the tool that took it, so a number stands where whoever ran phploc would look for it, and the
 * interpreted measurement and the raw output are the same report twice.
 */
enum MetricGroup: string
{
    case Size = 'size';
    case Complexity = 'complexity';
    case Dependencies = 'dependencies';
    case Structure = 'structure';

    public function label(): string
    {
        return match ($this) {
            self::Size => 'Size',
            self::Complexity => 'Cyclomatic complexity',
            self::Dependencies => 'Dependencies',
            self::Structure => 'Structure',
        };
    }

    /**
     * What the section is about, in one line - the sentence the report adds to phploc's heading.
     */
    public function about(): string
    {
        return match ($this) {
            self::Size => 'How much code there is, counted as lines and as statements.',
            self::Complexity => 'How many branches that code carries - the report is about this one.',
            self::Dependencies => 'What the code reaches for: globals, attributes, method calls.',
            self::Structure => 'What it is built from: namespaces, classes, methods, functions, constants.',
        };
    }

    /**
     * The metrics of this section, in the order phploc prints them.
     *
     * @return list<Metric>
     */
    public function metrics(): array
    {
        return array_values(array_filter(Metric::cases(), fn (Metric $metric) => $this === $metric->group()));
    }
}
