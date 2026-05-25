<?php

namespace App\Support\Checks;

/**
 * Text content of an uploaded report PDF, indexed by page for checks that
 * need to reason about where something appears (cover, body, references).
 *
 * `fragments` is optional positional data extracted from the PDF text
 * operators: each fragment knows its text, the page it appears on, and
 * an approximate font size in points (derived from the text matrix).
 */
class ParsedPdf
{
    /**
     * @param  list<string>  $pages  text content of each page, 0-indexed
     * @param  list<array{page: int, text: string, font_size: float}>  $fragments
     */
    public function __construct(
        public array $pages,
        public array $fragments = [],
    ) {}

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function fullText(): string
    {
        return implode("\n", $this->pages);
    }

    public function firstPagesText(int $count = 2): string
    {
        return implode("\n", array_slice($this->pages, 0, $count));
    }

    public function lastPagesText(int $count = 3): string
    {
        return implode("\n", array_slice($this->pages, -$count));
    }

    public function hasFontSizeData(): bool
    {
        return $this->fragments !== [];
    }
}
