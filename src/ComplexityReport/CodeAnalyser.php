<?php

declare(strict_types=1);

namespace App\ComplexityReport;

use App\Entity\Library;

class CodeAnalyser
{
    public function analyse(Library $library): Analysis
    {
        return new Analysis(12000, 23.1);
    }
}
