<?php

namespace App\Support\Checks;

/**
 * Pulls the References / Bibliography section out of an extracted PDF and
 * splits it into structured entries the checker can reason about.
 *
 * Harvard / London Met entry shape:
 *   Surname, A. (YYYY) Title. Place: Publisher.
 *   Surname, A. (YYYY) 'Article', Journal, vol(issue), pp. xx-xx.
 *   Surname, A. (YYYY) Title. Available at: URL [Accessed DD Month YYYY].
 */
class ReferenceParser
{
    /**
     * @return array{
     *   text: string,
     *   entries: list<array{
     *     raw: string,
     *     lines: list<string>,
     *     surname: ?string,
     *     year: ?string,
     *     has_year: bool,
     *     has_url: bool,
     *     has_access_date: bool,
     *     hanging_indent_lines: int,
     *     continuation_lines: int
     *   }>
     * }
     */
    public function parse(string $fullText): array
    {
        $section = $this->isolateSection($fullText);

        if ($section === '') {
            return ['text' => '', 'entries' => []];
        }

        return [
            'text' => $section,
            'entries' => $this->splitEntries($section),
        ];
    }

    private function isolateSection(string $text): string
    {
        if (preg_match('/(^|\n)\s*(references|bibliography)\s*\n/i', $text, $m, PREG_OFFSET_CAPTURE)) {
            $start = (int) $m[0][1] + strlen($m[0][0]);

            return trim(substr($text, $start));
        }

        return '';
    }

    /**
     * Split the references section into individual entries. An entry begins on
     * a line that looks like an author surname + initial(s): "Surname, A." or
     * "Surname, A. and Other, B." Continuation lines stay attached.
     *
     * @return list<array{
     *   raw: string, lines: list<string>, surname: ?string, year: ?string,
     *   has_year: bool, has_url: bool, has_access_date: bool,
     *   hanging_indent_lines: int, continuation_lines: int
     * }>
     */
    private function splitEntries(string $section): array
    {
        $rawLines = preg_split('/\R/', $section) ?: [];

        $entries = [];
        $current = [];

        foreach ($rawLines as $line) {
            if (trim($line) === '') {
                if ($current !== []) {
                    $entries[] = $current;
                    $current = [];
                }

                continue;
            }

            if ($this->looksLikeEntryStart($line) && $current !== []) {
                $entries[] = $current;
                $current = [];
            }

            $current[] = $line;
        }

        if ($current !== []) {
            $entries[] = $current;
        }

        $summaries = array_map(fn (array $lines) => $this->summarizeEntry($lines), $entries);

        // Drop entries that don't look like real references (e.g. a stray page
        // number or footer fragment that ended up after the heading).
        return array_values(array_filter(
            $summaries,
            fn (array $e) => $e['surname'] !== null || $e['has_year'] || str_word_count($e['raw']) >= 4,
        ));
    }

    private function looksLikeEntryStart(string $line): bool
    {
        return (bool) preg_match('/^\s*[A-Z][A-Za-z\'-]+(?:\s+[A-Z][A-Za-z\'-]+)?,\s*[A-Z]\.?/', $line);
    }

    /**
     * @param  list<string>  $lines
     * @return array{
     *   raw: string, lines: list<string>, surname: ?string, year: ?string,
     *   has_year: bool, has_url: bool, has_access_date: bool,
     *   hanging_indent_lines: int, continuation_lines: int
     * }
     */
    private function summarizeEntry(array $lines): array
    {
        $raw = trim(implode(' ', array_map('trim', $lines)));

        $surname = null;
        if (preg_match('/^\s*([A-Z][A-Za-z\'-]+)\s*,/', $lines[0] ?? '', $m)) {
            $surname = $m[1];
        }

        $year = null;
        if (preg_match('/\((\d{4})[a-z]?\)/', $raw, $m)) {
            $year = $m[1];
        }

        $hasUrl = (bool) preg_match('#https?://[^\s\]]+#', $raw);
        $hasAccessDate = (bool) preg_match('/\[\s*Accessed\b[^\]]*\]/i', $raw);

        $continuation = max(0, count($lines) - 1);
        $hangingIndentLines = 0;
        for ($i = 1; $i < count($lines); $i++) {
            if (preg_match('/^\s{2,}\S/', $lines[$i])) {
                $hangingIndentLines++;
            }
        }

        return [
            'raw' => $raw,
            'lines' => array_values($lines),
            'surname' => $surname,
            'year' => $year,
            'has_year' => $year !== null,
            'has_url' => $hasUrl,
            'has_access_date' => $hasAccessDate,
            'hanging_indent_lines' => $hangingIndentLines,
            'continuation_lines' => $continuation,
        ];
    }
}
