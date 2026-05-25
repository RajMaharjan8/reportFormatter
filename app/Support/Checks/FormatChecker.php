<?php

namespace App\Support\Checks;

/**
 * Strategy for evaluating an uploaded report against a specific
 * university format (e.g. London Met). Implementations return a list of
 * pass / warn / fail results that the UI renders as a checklist.
 */
interface FormatChecker
{
    public function name(): string;

    /**
     * @return list<CheckResult>
     */
    public function check(ParsedPdf $pdf): array;
}
