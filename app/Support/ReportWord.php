<?php

namespace App\Support;

use App\Models\Report;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Style\TOC;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Builds an editable Word (.docx) version of a report — cover, title page,
 * a Word-native Table of Contents, the abstract, and the numbered body.
 */
class ReportWord
{
    public static function download(Report $report): StreamedResponse
    {
        $word = (new self)->build($report);
        $writer = IOFactory::createWriter($word);

        $name = Str::slug($report->title ?: $report->module_title ?: 'report').'.docx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function build(Report $report): PhpWord
    {
        $report->loadMissing('sections');
        $compiler = ReportCompiler::for($report);

        // Without this PHPWord writes raw &, < and > into the XML, which
        // produces a .docx Word refuses to open.
        Settings::setOutputEscapingEnabled(true);

        $word = new PhpWord;
        $word->getSettings()->setUpdateFields(true);
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);

        // Apply the report's configured line spacing globally — PhpWord uses
        // a line-height multiplier where 1.0 = single, 1.15 = the default rule.
        $word->setDefaultParagraphStyle([
            'lineHeight' => $report->lineSpacing(),
        ]);

        $word->addTitleStyle(1, ['bold' => true, 'size' => 14], ['spaceBefore' => 240, 'spaceAfter' => 120]);
        $word->addTitleStyle(2, ['bold' => true, 'size' => 12], ['spaceBefore' => 180, 'spaceAfter' => 60]);
        $word->addTitleStyle(3, ['bold' => true, 'size' => 12], ['spaceBefore' => 120, 'spaceAfter' => 60]);

        $sectionStyle = $this->sectionStyle($report);

        $this->addCover($word->addSection($sectionStyle), $report);
        $this->addTitlePage($word->addSection($sectionStyle), $report);

        foreach ($compiler->frontMatter() as $page) {
            $this->addFrontPage($word->addSection($sectionStyle), $page);
        }

        $this->addContents($word->addSection($sectionStyle));

        if (filled($report->abstract)) {
            $this->addAbstract($word->addSection($sectionStyle), (string) $report->abstract);
        }

        $align = in_array($report->page_number_align, ['left', 'center', 'right'], true)
            ? $report->page_number_align
            : 'right';

        $this->addBody($word->addSection($sectionStyle), $compiler, $align);

        return $word;
    }

    /**
     * Build the PhpWord section style — margins are stored in inches on the
     * report and converted here to twips (1 inch = 1440 twips).
     *
     * @return array<string, int>
     */
    protected function sectionStyle(Report $report): array
    {
        $margins = $report->pageMargins();

        return [
            'marginTop' => (int) round($margins['top'] * 1440),
            'marginRight' => (int) round($margins['right'] * 1440),
            'marginBottom' => (int) round($margins['bottom'] * 1440),
            'marginLeft' => (int) round($margins['left'] * 1440),
        ];
    }

    protected function addCover(Section $section, Report $report): void
    {
        if ($report->cover_format === 'tu') {
            $this->addTuCover($section, $report);

            return;
        }

        $bold = ['bold' => true];
        $centre = ['alignment' => 'center'];

        foreach (['images/london-met-logo.png' => 110, 'images/islington-logo.png' => 200] as $path => $width) {
            $file = public_path($path);
            if (is_file($file)) {
                $section->addImage($file, ['width' => $width, 'alignment' => 'center']);
            }
        }

        $section->addTextBreak(2);
        $section->addText('Module Code & Module Title', $bold, $centre);
        $section->addText(trim($report->module_code.' '.$report->module_title), $bold, $centre);

        $section->addTextBreak(2);
        $section->addText($report->assessment_type ?: 'Assessment Type', $bold, $centre);
        $section->addText(trim(($report->academic_year ?? '').' '.($report->semester ?? '')) ?: 'Semester', $bold, $centre);

        $section->addTextBreak(2);
        $lines = array_filter([
            'Student Name: '.$report->student_name,
            'London Met ID: '.$report->london_id,
            'College ID: '.$report->college_id,
            $report->assignment_due_date ? 'Assignment Due Date: '.$report->assignment_due_date->format('l, F j, Y') : null,
            $report->submission_date ? 'Assignment Submission Date: '.$report->submission_date->format('l, F j, Y') : null,
            $report->submitted_to ? 'Submitted To: '.$report->submitted_to : null,
        ]);

        foreach ($lines as $line) {
            $section->addText($line, $bold, $centre);
        }
    }

    /**
     * Render the Tribhuvan University cover sheet.
     */
    protected function addTuCover(Section $section, Report $report): void
    {
        $bold = ['bold' => true];
        $centre = ['alignment' => 'center'];

        $section->addText('TRIBHUVAN UNIVERSITY', ['bold' => true, 'size' => 24], $centre);

        if (filled($report->tu_college_name)) {
            foreach (preg_split('/\r\n|\r|\n/', (string) $report->tu_college_name) ?: [] as $line) {
                if (trim($line) !== '') {
                    $section->addText(trim($line), ['bold' => true, 'size' => 16], $centre);
                }
            }
        }

        $section->addTextBreak(2);

        $logo = public_path('images/tu/tulogo.png');
        if (is_file($logo)) {
            $section->addImage($logo, ['width' => 220, 'alignment' => 'center']);
        }

        $section->addTextBreak(2);

        if (filled($report->title)) {
            $section->addText($report->title, ['bold' => true, 'size' => 16, 'underline' => 'single'], $centre);
        }

        $section->addTextBreak(5);

        $table = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $table->addRow();

        $submittedBy = $table->addCell(4500);
        $submittedBy->addText('SUBMITTED BY:', ['bold' => true, 'underline' => 'single']);
        $submittedBy->addText('Name: '.$report->student_name, $bold);

        if (filled($report->tu_roll_number)) {
            $submittedBy->addText('Roll No: '.$report->tu_roll_number, $bold);
        }

        if ($report->submission_date) {
            $submittedBy->addText('Date: '.$report->submission_date->format('Y-m-d'), $bold);
        }

        $submittedTo = $table->addCell(4500);
        $submittedTo->addText('SUBMITTED TO:', ['bold' => true, 'underline' => 'single']);

        if (filled($report->submitted_to)) {
            $submittedTo->addText((string) $report->submitted_to, $bold);
        }

        if (filled($report->tu_submitted_to_position)) {
            $submittedTo->addText((string) $report->tu_submitted_to_position, $bold);
        }
    }

    protected function addTitlePage(Section $section, Report $report): void
    {
        $section->addTextBreak(10);
        $section->addText(
            $report->title ?: $report->module_title,
            ['bold' => true, 'size' => 20],
            ['alignment' => 'center'],
        );
    }

    /**
     * Render a custom front-matter page — its title as a heading, then its
     * content. Front pages are unnumbered and excluded from the contents.
     *
     * @param  array{title: string, id: string, html: string}  $page
     */
    protected function addFrontPage(Section $section, array $page): void
    {
        if (filled($page['title'])) {
            $section->addText($page['title'], ['bold' => true, 'size' => 14], ['spaceAfter' => 200]);
        }

        $html = trim((string) $page['html']);

        if ($html === '') {
            return;
        }

        try {
            Html::addHtml($section, $html, false, false);
        } catch (Throwable $e) {
            $section->addText(strip_tags($html));
        }
    }

    protected function addContents(Section $section): void
    {
        $section->addText('Table of Contents', ['bold' => true, 'size' => 14], ['spaceAfter' => 200]);
        $section->addTOC(['size' => 12], ['tabLeader' => TOC::TAB_LEADER_DOT]);
    }

    protected function addAbstract(Section $section, string $abstract): void
    {
        $section->addText('Abstract', ['bold' => true, 'size' => 14], ['spaceAfter' => 200]);

        foreach (preg_split('/\n+/', $abstract) ?: [] as $paragraph) {
            if (trim($paragraph) !== '') {
                $section->addText(trim($paragraph), [], ['alignment' => 'both', 'lineHeight' => 1.5]);
            }
        }
    }

    protected function addBody(Section $section, ReportCompiler $compiler, string $align): void
    {
        $footer = $section->addFooter();
        $footer->addPreserveText('{PAGE}', ['size' => 11], ['alignment' => $align]);

        foreach ($compiler->sections() as $reportSection) {
            $section->addTitle($reportSection['marker'].' '.$reportSection['title'], 1);

            $html = trim((string) $reportSection['html']);

            if ($html === '') {
                continue;
            }

            try {
                Html::addHtml($section, $html, false, false);
            } catch (Throwable $e) {
                $section->addText(strip_tags($html));
            }
        }
    }
}
