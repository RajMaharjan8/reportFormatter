<?php

namespace App\Support\Checks;

use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * Loads a PDF file from disk, runs the selected format's checks, and
 * returns the results for the UI.
 */
class ReportPdfAnalyzer
{
    /**
     * Format slug => checker instance. Add new universities here.
     *
     * @return array<string, FormatChecker>
     */
    public static function availableFormats(): array
    {
        return [
            'london_met' => new LondonMetFormatChecker,
            'tu' => new TuFormatChecker,
        ];
    }

    /**
     * @return array{checker: FormatChecker, results: list<CheckResult>, pages: int}
     */
    public function analyze(string $pdfPath, string $formatSlug): array
    {
        $formats = self::availableFormats();

        if (! isset($formats[$formatSlug])) {
            throw new RuntimeException("Unknown format: {$formatSlug}");
        }

        $checker = $formats[$formatSlug];
        $pdf = $this->parse($pdfPath);

        return [
            'checker' => $checker,
            'results' => $checker->check($pdf),
            'pages' => $pdf->pageCount(),
        ];
    }

    private function parse(string $pdfPath): ParsedPdf
    {
        $parser = new Parser;
        $document = $parser->parseFile($pdfPath);

        $pages = [];
        $fragments = [];

        foreach ($document->getPages() as $index => $page) {
            $pages[] = (string) $page->getText();

            foreach ($this->extractFragments($page, $index) as $fragment) {
                $fragments[] = $fragment;
            }
        }

        return new ParsedPdf($pages, $fragments);
    }

    /**
     * Walk the page's text-matrix data and emit {text, page, font_size}
     * fragments. Font size is read from the PDF text matrix (its scale
     * factor); some PDFs encode it as 1.0 in the matrix and apply the
     * size at the Tf operator level — those will yield 1.0 here and the
     * font-size check will skip them.
     *
     * @return list<array{page: int, text: string, font_size: float}>
     */
    private function extractFragments(object $page, int $pageIndex): array
    {
        if (! method_exists($page, 'getDataTm')) {
            return [];
        }

        try {
            $data = $page->getDataTm();
        } catch (\Throwable) {
            return [];
        }

        $fragments = [];
        foreach ($data as $row) {
            if (! is_array($row) || count($row) < 2) {
                continue;
            }
            [$matrix, $text] = [$row[0] ?? null, $row[1] ?? null];
            if (! is_array($matrix) || ! is_string($text) || trim($text) === '') {
                continue;
            }

            $a = (float) ($matrix[0] ?? 0);
            $b = (float) ($matrix[1] ?? 0);
            $size = round(sqrt($a * $a + $b * $b), 2);

            if ($size <= 0) {
                continue;
            }

            $fragments[] = [
                'page' => $pageIndex,
                'text' => trim($text),
                'font_size' => $size,
            ];
        }

        return $fragments;
    }
}
