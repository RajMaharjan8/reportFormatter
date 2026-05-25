<?php

use App\Models\Report;
use App\Support\CitationFormatter;
use App\Support\ReportCompiler;
use Livewire\Livewire;

function makeReportForReferences(array $overrides = []): Report
{
    return Report::create(array_merge([
        'user_id' => auth()->id() ?? loginAsTestUser()->id,
        'cover_format' => 'london_met',
        'module_code' => 'MN7001NI',
        'module_title' => 'Operations Management',
        'title' => 'Sample Report',
        'student_name' => 'Raj',
        'london_id' => '25030253',
        'college_id' => 'np01',
    ], $overrides));
}

it('scopes references to a single report', function () {
    $a = makeReportForReferences();
    $b = makeReportForReferences();

    $a->references()->create([
        'type' => 'journal',
        'data' => ['authors' => 'Smith, J.', 'year' => '2023', 'title' => 'A paper'],
    ]);

    expect($a->references()->count())->toBe(1)
        ->and($b->references()->count())->toBe(0);
});

it('formats London Met inline citations as (Author, Year)', function () {
    $report = makeReportForReferences(['reference_format' => 'london_met']);
    $reference = $report->references()->create([
        'type' => 'journal',
        'data' => ['authors' => 'Jane Smith', 'year' => '2023', 'title' => 'A paper', 'journal' => 'JOE'],
    ]);

    $formatter = new CitationFormatter('london_met', [$reference]);

    expect($formatter->inline($reference))->toBe('(Smith, 2023)');
});

it('formats APA inline citations as (Author, Year)', function () {
    $report = makeReportForReferences();
    $reference = $report->references()->create([
        'type' => 'book',
        'data' => ['authors' => 'Doe, A.', 'year' => '2021', 'title' => 'A book', 'publisher' => 'Routledge'],
    ]);

    $formatter = new CitationFormatter('apa', [$reference]);

    expect($formatter->inline($reference))->toBe('(Doe, 2021)');
});

it('numbers IEEE inline citations by alphabetical author order', function () {
    $report = makeReportForReferences();

    $smith = $report->references()->create([
        'type' => 'journal',
        'data' => ['authors' => 'Smith, J.', 'year' => '2023', 'title' => 'B'],
    ]);
    $adams = $report->references()->create([
        'type' => 'journal',
        'data' => ['authors' => 'Adams, K.', 'year' => '2022', 'title' => 'A'],
    ]);

    $formatter = new CitationFormatter('ieee', [$smith, $adams]);

    expect($formatter->inline($adams))->toBe('[1]')
        ->and($formatter->inline($smith))->toBe('[2]');
});

it('renders inline citation spans in the compiled output and tracks usage', function () {
    $report = makeReportForReferences(['reference_format' => 'london_met']);

    $reference = $report->references()->create([
        'type' => 'journal',
        'data' => ['authors' => 'Smith, J.', 'year' => '2023', 'title' => 'A paper'],
    ]);

    $report->sections()->create([
        'placement' => 'body',
        'order' => 0,
        'title' => 'Introduction',
        'content' => '<p>Hello <span class="ref-cite" data-ref-id="'.$reference->id.'" contenteditable="false">cite</span>.</p>',
    ]);

    $compiled = ReportCompiler::for($report->load('sections'))->sections();

    expect($compiled[0]['html'])->toContain('(Smith, 2023)')
        ->and($compiled[0]['html'])->toContain('href="#ref-'.$reference->id.'"')
        ->and($compiled[0]['html'])->not->toContain('ref-cite"');
});

it('links inline citations to their bibliography entry by id', function () {
    $report = makeReportForReferences(['reference_format' => 'london_met']);

    $reference = $report->references()->create([
        'type' => 'journal',
        'data' => ['authors' => 'Smith, J.', 'year' => '2023', 'title' => 'A paper'],
    ]);

    $report->sections()->create([
        'placement' => 'body',
        'order' => 0,
        'title' => 'Body',
        'content' => '<p><span class="ref-cite" data-ref-id="'.$reference->id.'">x</span></p>'
            .'<div data-references-list>placeholder</div>',
    ]);

    $html = ReportCompiler::for($report->load('sections'))->sections()[0]['html'];

    expect($html)->toContain('href="#ref-'.$reference->id.'"')
        ->and($html)->toContain('id="ref-'.$reference->id.'"');
});

it('only lists references actually cited in the bibliography, in alphabetical order', function () {
    $report = makeReportForReferences(['reference_format' => 'london_met']);

    $used = $report->references()->create([
        'type' => 'journal',
        'data' => ['authors' => 'Smith, J.', 'year' => '2023', 'title' => 'Used paper'],
    ]);
    $alsoUsed = $report->references()->create([
        'type' => 'book',
        'data' => ['authors' => 'Adams, K.', 'year' => '2020', 'title' => 'A book', 'publisher' => 'OUP'],
    ]);
    $report->references()->create([
        'type' => 'journal',
        'data' => ['authors' => 'Zane, Q.', 'year' => '2021', 'title' => 'Unused'],
    ]);

    $report->sections()->create([
        'placement' => 'body',
        'order' => 0,
        'title' => 'Body',
        'content' => '<p>Read <span class="ref-cite" data-ref-id="'.$used->id.'">x</span> and '
            .'<span class="ref-cite" data-ref-id="'.$alsoUsed->id.'">y</span>.</p>'
            .'<div data-references-list>placeholder</div>',
    ]);

    $html = ReportCompiler::for($report->load('sections'))->sections()[0]['html'];

    expect($html)->toContain('Used paper')
        ->and($html)->toContain('A book')
        ->and($html)->not->toContain('Unused')
        ->and(strpos($html, 'A book'))->toBeLessThan(strpos($html, 'Used paper'));
});

it('switches inline citations to the report format', function () {
    $report = makeReportForReferences(['reference_format' => 'ieee']);

    $reference = $report->references()->create([
        'type' => 'journal',
        'data' => ['authors' => 'Smith, J.', 'year' => '2023', 'title' => 'A paper'],
    ]);

    $report->sections()->create([
        'placement' => 'body',
        'order' => 0,
        'title' => 'Body',
        'content' => '<p><span class="ref-cite" data-ref-id="'.$reference->id.'">x</span></p>',
    ]);

    $html = ReportCompiler::for($report->load('sections'))->sections()[0]['html'];

    expect($html)->toContain('[1]')
        ->and($html)->not->toContain('(Smith');
});

it('adds and edits references through the manage-references component', function () {
    $report = makeReportForReferences();

    Livewire::test('manage-references', ['report' => $report])
        ->set('type', 'journal')
        ->set('form.authors', 'Smith, J.')
        ->set('form.year', '2023')
        ->set('form.title', 'Stuff')
        ->set('form.journal', 'Things')
        ->call('save');

    $reference = $report->references()->first();

    expect($reference)->not->toBeNull()
        ->and($reference->type)->toBe('journal')
        ->and($reference->data['title'])->toBe('Stuff');
});

it('changes the report format through the manage-references component', function () {
    $report = makeReportForReferences();

    Livewire::test('manage-references', ['report' => $report])
        ->call('changeFormat', 'ieee');

    expect($report->fresh()->reference_format)->toBe('ieee');
});

it('escapes HTML in user-entered reference fields', function () {
    $report = makeReportForReferences();

    $reference = $report->references()->create([
        'type' => 'journal',
        'data' => [
            'authors' => 'Smith <script>alert(1)</script>',
            'year' => '2023',
            'title' => 'Title <b>bold</b>',
            'journal' => 'JOE',
        ],
    ]);

    $report->sections()->create([
        'placement' => 'body',
        'order' => 0,
        'title' => 'Body',
        'content' => '<p><span class="ref-cite" data-ref-id="'.$reference->id.'">x</span></p>'
            .'<div data-references-list>placeholder</div>',
    ]);

    $html = ReportCompiler::for($report->load('sections'))->sections()[0]['html'];

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->not->toContain('Title <b>bold</b>');
});
