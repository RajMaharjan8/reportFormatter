<?php

namespace App\Support\Checks;

/**
 * Extracts in-text Harvard citations from the body of a report.
 *
 * Recognised forms:
 *   (Surname, 2021)
 *   (Surname, 2021, p. 12)
 *   (Surname and Other, 2021)
 *   (Surname et al., 2021)
 *   Surname (2021) states that ...
 */
class CitationParser
{
    /**
     * @return list<array{surname: string, year: string, raw: string}>
     */
    public function parse(string $bodyText): array
    {
        $citations = [];

        // Parenthesised: (Surname, 2021) | (Surname et al., 2021) | (Surname and Other, 2021)
        $pattern = '/\(\s*([A-Z][A-Za-z\'-]+)(?:\s+et\s+al\.?|\s+and\s+[A-Z][A-Za-z\'-]+)?\s*,\s*(\d{4})[a-z]?(?:\s*,\s*p+\.?\s*\d+(?:-\d+)?)?\s*\)/';
        if (preg_match_all($pattern, $bodyText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $citations[] = ['surname' => $m[1], 'year' => $m[2], 'raw' => $m[0]];
            }
        }

        // Narrative: Surname (2021) — common preceding tokens to avoid noise.
        $narrative = '/(?:^|[\s\.,;:])([A-Z][A-Za-z\'-]+)\s+\((\d{4})[a-z]?\)/';
        if (preg_match_all($narrative, $bodyText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $citations[] = ['surname' => $m[1], 'year' => $m[2], 'raw' => $m[0]];
            }
        }

        return $citations;
    }
}
