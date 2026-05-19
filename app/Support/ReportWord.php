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

        $word->addTitleStyle(1, ['bold' => true, 'size' => 14], ['spaceBefore' => 240, 'spaceAfter' => 120]);
        $word->addTitleStyle(2, ['bold' => true, 'size' => 12], ['spaceBefore' => 180, 'spaceAfter' => 60]);
        $word->addTitleStyle(3, ['bold' => true, 'size' => 12], ['spaceBefore' => 120, 'spaceAfter' => 60]);

        $this->addCover($word->addSection(), $report);
        $this->addTitlePage($word->addSection(), $report);
        $this->addContents($word->addSection());

        if (filled($report->abstract)) {
            $this->addAbstract($word->addSection(), (string) $report->abstract);
        }

        $align = in_array($report->page_number_align, ['left', 'center', 'right'], true)
            ? $report->page_number_align
            : 'right';

        $this->addBody($word->addSection(), $compiler, $align);

        return $word;
    }

    protected function addCover(Section $section, Report $report): void
    {
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

    protected function addTitlePage(Section $section, Report $report): void
    {
        $section->addTextBreak(10);
        $section->addText(
            $report->title ?: $report->module_title,
            ['bold' => true, 'size' => 20],
            ['alignment' => 'center'],
        );
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
