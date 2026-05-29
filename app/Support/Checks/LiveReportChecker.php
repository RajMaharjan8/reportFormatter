<?php

namespace App\Support\Checks;

use App\Models\Report;
use App\Models\Section;

/**
 * Runs format checks against the user's *live* report data — the cover
 * fields, sections and references stored in the DB — rather than a PDF
 * upload. The PDF checker can only see what was rendered; this one sees
 * the source of truth and can give precise, actionable feedback while
 * the student is still writing.
 */
class LiveReportChecker
{
    private CitationParser $citationParser;

    public function __construct(?CitationParser $citationParser = null)
    {
        $this->citationParser = $citationParser ?? new CitationParser;
    }

    /**
     * @return array{format: string, results: list<CheckResult>}
     */
    public function check(Report $report): array
    {
        $report->loadMissing(['sections', 'references']);

        return $report->cover_format === 'tu'
            ? ['format' => 'Tribhuvan University (IOE)', 'results' => $this->tuChecks($report)]
            : ['format' => 'London Metropolitan University', 'results' => $this->londonMetChecks($report)];
    }

    /**
     * @return list<CheckResult>
     */
    private function londonMetChecks(Report $report): array
    {
        return [
            $this->coverField('Report title', $report->title),
            $this->coverField('Student name', $report->student_name),
            $this->londonId($report->london_id),
            $this->coverField('Module code', $report->module_code),
            $this->coverField('Module title', $report->module_title),
            $this->coverField('College ID', $report->college_id),
            $this->abstractCheck($report, 5000),
            $this->bodySectionsCheck($report, 3),
            $this->referencesCheck($report, 3),
            $this->citationsCoverageCheck($report),
        ];
    }

    /**
     * @return list<CheckResult>
     */
    private function tuChecks(Report $report): array
    {
        return [
            $this->coverField('Report title', $report->title),
            $this->coverField('Student name', $report->student_name),
            $this->coverField('TU roll number', $report->tu_roll_number),
            $this->coverField('College / campus name', $report->tu_college_name, 'e.g. "Pulchowk Campus, Institute of Engineering"'),
            $this->coverField('Submitted-to position', $report->tu_submitted_to_position, 'e.g. "Department of Mechanical Engineering"'),
            $this->abstractCheck($report, 150 * 7), // ~150 words, allowing ~7 chars/word
            $this->tuAbstractWordLimit($report),
            $this->bodySectionsCheck($report, 4),
            $this->referencesCheck($report, 3),
            $this->citationsCoverageCheck($report),
        ];
    }

    private function coverField(string $label, ?string $value, string $hint = ''): CheckResult
    {
        if (filled($value)) {
            return CheckResult::pass($label, 'Filled in on the cover.');
        }

        $detail = "Cover page is missing {$label}.";
        if ($hint !== '') {
            $detail .= " ({$hint})";
        }

        return CheckResult::fail($label, $detail);
    }

    private function londonId(?string $value): CheckResult
    {
        if (! filled($value)) {
            return CheckResult::fail('London Met ID', 'Cover page is missing your London Met ID.');
        }

        if (preg_match('/^\d{8}$/', (string) $value)) {
            return CheckResult::pass('London Met ID', '8-digit ID present on the cover.');
        }

        return CheckResult::warn('London Met ID', "London Met IDs are 8 digits — yours is \"{$value}\".");
    }

    private function abstractCheck(Report $report, int $maxLength): CheckResult
    {
        $abstract = trim((string) $report->abstract);

        if ($abstract === '') {
            return CheckResult::warn('Abstract', 'No abstract has been added yet. London Met / TU reports normally include one before the contents.');
        }

        if (mb_strlen($abstract) > $maxLength) {
            return CheckResult::warn('Abstract', 'Abstract is quite long — consider trimming it.');
        }

        return CheckResult::pass('Abstract', 'Abstract is in place.');
    }

    private function tuAbstractWordLimit(Report $report): CheckResult
    {
        $abstract = trim((string) $report->abstract);

        if ($abstract === '') {
            return CheckResult::warn('Abstract length (TU 150-word limit)', 'No abstract yet — TU requires one of no more than 150 words.');
        }

        $words = str_word_count($abstract);

        if ($words <= 150) {
            return CheckResult::pass('Abstract length (TU 150-word limit)', "Abstract is {$words} words.");
        }

        return CheckResult::fail('Abstract length (TU 150-word limit)', "Abstract is {$words} words — TU requires 150 or fewer.");
    }

    private function bodySectionsCheck(Report $report, int $minSections): CheckResult
    {
        $body = $report->sections->where('placement', 'body');
        $count = $body->count();

        if ($count === 0) {
            return CheckResult::fail('Body sections', 'No body sections yet. Add at least an Introduction, a Main Body and a Conclusion.');
        }

        if ($count < $minSections) {
            return CheckResult::warn('Body sections', "Only {$count} section(s). A full report normally has at least {$minSections} (e.g. Introduction, Body, Conclusion).");
        }

        $blank = $body->filter(fn (Section $s) => trim(strip_tags((string) $s->content)) === '')->count();
        if ($blank > 0) {
            return CheckResult::warn('Body sections', "{$count} sections present, but {$blank} have no written content yet.");
        }

        return CheckResult::pass('Body sections', "{$count} body sections, all with content.");
    }

    private function referencesCheck(Report $report, int $minRefs): CheckResult
    {
        $count = $report->references->count();

        if ($count === 0) {
            return CheckResult::fail('References', 'No references added. Use the references panel to add sources cited in the body.');
        }

        if ($count < $minRefs) {
            return CheckResult::warn('References', "Only {$count} reference(s). A report normally cites several sources.");
        }

        return CheckResult::pass('References', "{$count} reference entries added.");
    }

    private function citationsCoverageCheck(Report $report): CheckResult
    {
        $bodyText = $this->bodyText($report);
        $citations = $this->citationParser->parse($bodyText);

        if ($citations === []) {
            if ($report->references->count() > 0) {
                return CheckResult::warn('Citations in body', 'You have references listed, but none of them are cited in the body text (e.g. "(Smith, 2021)").');
            }

            return CheckResult::warn('Citations in body', 'No in-text citations detected yet.');
        }

        $refSurnames = $report->references
            ->map(fn ($r) => $r->sortKey())
            ->filter()
            ->values()
            ->all();

        if ($refSurnames === []) {
            return CheckResult::warn('Citations in body', count($citations).' citations found in the body, but no reference entries to match against.');
        }

        $missing = [];
        $seen = [];
        foreach ($citations as $c) {
            $key = strtolower($c['surname']).'|'.$c['year'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $surnameLower = strtolower($c['surname']);
            $matched = false;
            foreach ($refSurnames as $refSurname) {
                if ($refSurname !== '' && str_contains($refSurname, $surnameLower)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                $missing[] = $c['raw'];
            }
        }

        if ($missing === []) {
            return CheckResult::pass('Citations in body', count($citations).' in-text citation(s) detected, all matched to a reference.');
        }

        return CheckResult::warn('Citations in body', count($missing).' citation(s) have no matching reference: '.implode(', ', array_slice($missing, 0, 4)));
    }

    private function bodyText(Report $report): string
    {
        return $report->sections
            ->where('placement', 'body')
            ->map(fn (Section $s) => strip_tags((string) $s->content))
            ->implode("\n\n");
    }
}
