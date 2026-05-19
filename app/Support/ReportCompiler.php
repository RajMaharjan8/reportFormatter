<?php

namespace App\Support;

use App\Models\Report;
use App\Models\Section;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;

/**
 * Compiles a report into the data a paginated document needs:
 *
 *  - section HTML with section-prefixed heading numbers baked in (1, 2.1, 2.4.1 …)
 *  - a flat Table of Contents with anchor ids for every heading
 *  - numbered figures and tables with anchor ids for the Table of Figures / Tables
 *
 * Anchor ids let Paged.js resolve real page numbers via `target-counter()`.
 */
class ReportCompiler
{
    /** @var list<array{number: string, marker: string, title: string, id: string, html: string}> */
    protected array $sections = [];

    /** @var list<array{level: int, number: string, marker: string, label: string, id: string}> */
    protected array $contents = [];

    /** @var list<array{number: int, label: string, caption: string, id: string, section: string}> */
    protected array $figures = [];

    /** @var list<array{number: int, label: string, caption: string, id: string, section: string}> */
    protected array $tables = [];

    protected int $figureCount = 0;

    protected int $tableCount = 0;

    public function __construct(protected Report $report) {}

    public static function for(Report $report): self
    {
        return (new self($report))->compile();
    }

    public function compile(): self
    {
        $sectionIndex = 0;

        foreach ($this->report->sections as $section) {
            $sectionIndex++;
            $sectionAnchor = 'sec-'.$section->id;
            $marker = $this->sectionMarker($sectionIndex);

            $this->contents[] = [
                'level' => 1,
                'number' => (string) $sectionIndex,
                'marker' => $marker,
                'label' => (string) $section->title,
                'id' => $sectionAnchor,
            ];

            $this->sections[] = [
                'number' => (string) $sectionIndex,
                'marker' => $marker,
                'title' => (string) $section->title,
                'id' => $sectionAnchor,
                'html' => $this->processSection($section, $sectionIndex),
            ];
        }

        return $this;
    }

    /**
     * The prefix shown before a section title — plain "1." by default, or a
     * word-based label like "Chapter 1:" when the report sets section_label.
     */
    protected function sectionMarker(int $sectionIndex): string
    {
        $label = trim((string) $this->report->section_label);

        return $label === '' ? $sectionIndex.'.' : $label.' '.$sectionIndex.':';
    }

    /** @return list<array{number: string, marker: string, title: string, id: string, html: string}> */
    public function sections(): array
    {
        return $this->sections;
    }

    /** @return list<array{level: int, number: string, marker: string, label: string, id: string}> */
    public function contents(): array
    {
        return $this->contents;
    }

    /** @return list<array{number: int, label: string, caption: string, id: string, section: string}> */
    public function figures(): array
    {
        return $this->figures;
    }

    /** @return list<array{number: int, label: string, caption: string, id: string, section: string}> */
    public function tables(): array
    {
        return $this->tables;
    }

    public function hasFigures(): bool
    {
        return $this->figures !== [];
    }

    public function hasTables(): bool
    {
        return $this->tables !== [];
    }

    protected function processSection(Section $section, int $sectionIndex): string
    {
        $html = SectionContent::toHtml($section->content);

        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="report-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $document->getElementById('report-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $this->removeEmptyParagraphs($document);
        $this->numberHeadings($document, $section, $sectionIndex);
        $this->numberFigures($document, (string) $section->title);
        $this->numberTables($document, (string) $section->title);

        return $this->innerHtml($root);
    }

    /**
     * Drop blank paragraphs (empty lines, stray <p><br></p>) so the printed
     * report has no unintended vertical gaps.
     */
    protected function removeEmptyParagraphs(DOMDocument $document): void
    {
        foreach (iterator_to_array($document->getElementsByTagName('p')) as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }

            if ($paragraph->getElementsByTagName('img')->length > 0) {
                continue;
            }

            $text = str_replace("\u{00A0}", ' ', $paragraph->textContent);

            if (trim($text) === '') {
                $paragraph->parentNode?->removeChild($paragraph);
            }
        }
    }

    /**
     * Walk every heading in document order, assigning section-prefixed numbers
     * and an anchor id, and record it in the Table of Contents.
     *
     * The section title is heading level 1, so editor headings nest beneath it:
     * an <h2> becomes section.major (e.g. 2.1) and an <h3> becomes
     * section.major.minor (e.g. 2.1.3). A legacy <h1> is treated as an <h2>.
     */
    protected function numberHeadings(DOMDocument $document, Section $section, int $sectionIndex): void
    {
        $xpath = new DOMXPath($document);
        $headings = $xpath->query('//h1|//h2|//h3');

        if (! $headings instanceof DOMNodeList) {
            return;
        }

        $major = 0;
        $minor = 0;
        $headingIndex = 0;

        foreach ($headings as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }

            if ($heading->tagName === 'h3') {
                $minor++;
                $number = $sectionIndex.'.'.$major.'.'.$minor;
                $level = 3;
            } else {
                $major++;
                $minor = 0;
                $number = $sectionIndex.'.'.$major;
                $level = 2;
            }

            $headingIndex++;
            $anchor = 'h-'.$section->id.'-'.$headingIndex;
            $heading->setAttribute('id', $anchor);

            $this->contents[] = [
                'level' => $level,
                'number' => $number,
                'marker' => $number.'.',
                'label' => trim((string) preg_replace('/\s+/', ' ', $heading->textContent)),
                'id' => $anchor,
            ];

            $heading->insertBefore($document->createTextNode($number.'.  '), $heading->firstChild);
        }
    }

    protected function numberFigures(DOMDocument $document, string $sectionTitle): void
    {
        foreach ($this->collect($document, 'figure') as $figure) {
            if ($figure->getElementsByTagName('img')->length === 0) {
                continue;
            }

            $this->figureCount++;
            $anchor = 'fig-'.$this->figureCount;
            $figure->setAttribute('id', $anchor);

            $caption = $this->captionText($figure->getElementsByTagName('figcaption'));
            $label = $caption === '' ? "Figure {$this->figureCount}" : "Figure {$this->figureCount}: {$caption}";

            $this->figures[] = [
                'number' => $this->figureCount,
                'label' => $label,
                'caption' => $caption,
                'id' => $anchor,
                'section' => $sectionTitle,
            ];

            $this->writeCaption($figure, 'figcaption', $document, $label);
        }
    }

    protected function numberTables(DOMDocument $document, string $sectionTitle): void
    {
        foreach ($this->collect($document, 'table') as $table) {
            $this->tableCount++;
            $anchor = 'tbl-'.$this->tableCount;

            // CKEditor wraps tables in <figure class="table">; anchor the block.
            $wrapper = $table->parentNode;
            $isFigure = $wrapper instanceof DOMElement && $wrapper->tagName === 'figure';
            ($isFigure ? $wrapper : $table)->setAttribute('id', $anchor);

            // The caption may sit in <table><caption> or <figure><figcaption>.
            $captionEl = $table->getElementsByTagName('caption')->item(0);

            if (! $captionEl instanceof DOMElement && $isFigure) {
                foreach ($wrapper->childNodes as $child) {
                    if ($child instanceof DOMElement && $child->tagName === 'figcaption') {
                        $captionEl = $child;
                        break;
                    }
                }
            }

            $caption = $captionEl instanceof DOMElement ? trim($captionEl->textContent) : '';
            $label = $caption === '' ? "Table {$this->tableCount}" : "Table {$this->tableCount}: {$caption}";

            $this->tables[] = [
                'number' => $this->tableCount,
                'label' => $label,
                'caption' => $caption,
                'id' => $anchor,
                'section' => $sectionTitle,
            ];

            if ($captionEl instanceof DOMElement) {
                $captionEl->textContent = $label;
            } else {
                $this->writeCaption($table, 'caption', $document, $label, prepend: true);
            }
        }
    }

    /**
     * Snapshot a live element list into a stable array before mutating the DOM.
     *
     * @return list<DOMElement>
     */
    protected function collect(DOMDocument $document, string $tag): array
    {
        $elements = [];

        foreach ($document->getElementsByTagName($tag) as $element) {
            if ($element instanceof DOMElement) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    protected function captionText(DOMNodeList $list): string
    {
        $node = $list->item(0);

        return $node ? trim($node->textContent) : '';
    }

    protected function writeCaption(DOMElement $parent, string $tag, DOMDocument $document, string $text, bool $prepend = false): void
    {
        $existing = $parent->getElementsByTagName($tag)->item(0);

        if ($existing instanceof DOMElement) {
            $existing->textContent = $text;

            return;
        }

        $node = $document->createElement($tag);
        $node->textContent = $text;

        if ($prepend && $parent->firstChild) {
            $parent->insertBefore($node, $parent->firstChild);
        } else {
            $parent->appendChild($node);
        }
    }

    protected function innerHtml(DOMElement $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return $html;
    }
}
