<?php

declare(strict_types=1);

namespace App\ComplexityReport;

/**
 * The risk bands a cyclomatic complexity is read in - the scale the footer prints and the colour every
 * complexity in the report is marked with, so a number says where it stands before it is compared to
 * anything.
 *
 * The cases are in the order the scale runs, which is what the ramp behind it is drawn from: the report
 * measures averages, so a band ends where its number does and the next one starts right after it.
 */
enum ComplexityLevel: string
{
    case Simple = 'simple';
    case Moderate = 'moderate';
    case Complex = 'complex';
    case Untestable = 'untestable';

    public static function of(float $complexity): self
    {
        return match (true) {
            $complexity <= 10.0 => self::Simple,
            $complexity <= 20.0 => self::Moderate,
            $complexity <= 50.0 => self::Complex,
            default => self::Untestable,
        };
    }

    public function range(): string
    {
        return match ($this) {
            self::Simple => '1–10',
            self::Moderate => '11–20',
            self::Complex => '21–50',
            self::Untestable => '> 50',
        };
    }

    public function risk(): string
    {
        return match ($this) {
            self::Simple => 'Simple procedure, little risk',
            self::Moderate => 'More complex, moderate risk',
            self::Complex => 'Complex, high risk',
            self::Untestable => 'Untestable code, very high risk',
        };
    }
}
