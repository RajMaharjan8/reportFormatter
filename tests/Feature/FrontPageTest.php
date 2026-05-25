<?php

use App\Models\Report;
use App\Support\ReportCompiler;
use Livewire\Livewire;

function reportForFrontPages(): Report
{
    return Report::create([
        'user_id' => auth()->id() ?? loginAsTestUser()->id,
        'cover_format' => 'london_met',
        'module_code' => 'MN7001NI',
        'module_title' => 'Operations Management',
        'title' => 'Sample Report',
        'student_name' => 'Raj',
        'london_id' => '25030253',
        'college_id' => 'np01',
    ]);
}

it('keeps front pages out of the numbered sections and the contents', function () {
    $report = reportForFrontPages();
    $report->sections()->create(['placement' => 'front', 'order' => 0, 'title' => 'Acknowledgements', 'content' => '<p>Thanks.</p>']);
    $report->sections()->create(['placement' => 'body', 'order' => 1, 'title' => 'Introduction', 'content' => '<p>Intro.</p>']);

    $compiler = ReportCompiler::for($report->load('sections'));

    expect($compiler->frontMatter())->toHaveCount(1)
        ->and($compiler->frontMatter()[0]['title'])->toBe('Acknowledgements')
        ->and($compiler->sections())->toHaveCount(1)
        ->and($compiler->sections()[0]['title'])->toBe('Introduction')
        ->and($compiler->sections()[0]['number'])->toBe('1');

    $labels = collect($compiler->contents())->pluck('label');
    expect($labels)->toContain('Introduction')
        ->and($labels)->not->toContain('Acknowledgements');
});

it('numbers body sections from 1 regardless of front pages', function () {
    $report = reportForFrontPages();
    $report->sections()->create(['placement' => 'front', 'order' => 0, 'title' => 'Declaration', 'content' => null]);
    $report->sections()->create(['placement' => 'body', 'order' => 1, 'title' => 'Introduction', 'content' => null]);
    $report->sections()->create(['placement' => 'body', 'order' => 2, 'title' => 'Background', 'content' => null]);

    $sections = ReportCompiler::for($report->load('sections'))->sections();

    expect($sections)->toHaveCount(2)
        ->and($sections[0]['number'])->toBe('1')
        ->and($sections[1]['number'])->toBe('2');
});

it('renders front pages before the table of contents in the output', function () {
    $report = reportForFrontPages();
    $report->sections()->create(['placement' => 'front', 'order' => 0, 'title' => 'Acknowledgements', 'content' => '<p>Many thanks indeed.</p>']);
    $report->sections()->create(['placement' => 'body', 'order' => 1, 'title' => 'Introduction', 'content' => '<p>Intro.</p>']);

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('Acknowledgements')
        ->assertSee('Many thanks indeed.');
});

it('adds a front page from the section editor', function () {
    $report = reportForFrontPages();

    Livewire::test('pages::report-sections', ['report' => $report])
        ->set('newFrontPageTitle', 'Declaration')
        ->call('addFrontPage');

    $page = $report->sections()->where('placement', 'front')->first();

    expect($page)->not->toBeNull()
        ->and($page->title)->toBe('Declaration')
        ->and($page->placement)->toBe('front');
});

it('adds a numbered body section from the editor', function () {
    $report = reportForFrontPages();

    Livewire::test('pages::report-sections', ['report' => $report])
        ->set('newSectionTitle', 'Methodology')
        ->call('addSection');

    $section = $report->sections()->where('placement', 'body')->first();

    expect($section)->not->toBeNull()
        ->and($section->title)->toBe('Methodology')
        ->and($section->placement)->toBe('body');
});
