<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use SebastianBergmann\PHPLOC\Log\Text;

/**
 * What phploc printed for a release, printed by phploc.
 *
 * The report shows two of the sixty-odd numbers a measurement consists of; the raw output is the rest,
 * and it is a report of its own - sections, indentation, a column of right aligned numbers, a percentage
 * behind everything that is part of something. None of that is rebuilt here: the stored measurement is
 * handed to the same printer the phploc command line uses, so the modal shows phploc's output rather
 * than the report's rendering of it, and there is no second layout to keep in step with the tool.
 *
 * The printer writes to standard output because that is what a command line tool does, which is why this
 * catches it in a buffer instead of asking it for a string.
 */
final readonly class PhplocReport
{
    /**
     * @param array<string, float|int> $metrics as measured, see {@see Analysis::$metrics}
     */
    public function render(array $metrics): string
    {
        ob_start();

        try {
            // tests are never counted into the report, so there are none to print
            (new Text())->printResult($metrics, false);
        } finally {
            $output = ob_get_clean();
        }

        return false === $output ? '' : $output;
    }
}
