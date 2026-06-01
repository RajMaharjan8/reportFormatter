<?php

use App\Models\Report;
use App\Support\ReportCompiler;

function makeReport(): Report
{
    return Report::create([
        'user_id' => auth()->id() ?? loginAsTestUser()->id,
        'module_code' => 'MN7001NI',
        'module_title' => 'Operations and Technology Management',
        'title' => "Amazon's Fulfilment Network",
        'student_name' => 'Raj Maharjan',
        'london_id' => '25030253',
        'college_id' => 'np01mb7a250180',
    ]);
}

it('numbers headings with a section prefix and builds a table of contents', function () {
    $report = makeReport();

    $report->sections()->create([
        'order' => 0,
        'title' => 'Introduction',
        'content' => '<p>Intro text.</p>',
    ]);

    $report->sections()->create([
        'order' => 1,
        'title' => 'Background',
        'content' => '<h2>Network Architecture</h2><p>...</p>'
            .'<h2>The 4 Vs</h2><h3>Visibility</h3>',
    ]);

    $contents = ReportCompiler::for($report->load('sections'))->contents();

    expect($contents)->toHaveCount(5);
    expect($contents[0])->toMatchArray(['level' => 1, 'number' => '1', 'label' => 'Introduction']);
    expect($contents[1])->toMatchArray(['level' => 1, 'number' => '2', 'label' => 'Background']);
    expect($contents[2])->toMatchArray(['level' => 2, 'number' => '2.1', 'label' => 'Network Architecture']);
    expect($contents[3])->toMatchArray(['level' => 2, 'number' => '2.2', 'label' => 'The 4 Vs']);
    expect($contents[4])->toMatchArray(['level' => 3, 'number' => '2.2.1', 'label' => 'Visibility']);
});

it('labels sections plainly by default and with a word when set', function () {
    $report = makeReport();
    $report->sections()->create(['order' => 0, 'title' => 'Introduction', 'content' => '<p>x</p>']);

    $plain = ReportCompiler::for($report->load('sections'))->sections()[0];
    expect($plain['marker'])->toBe('1.');

    $report->update(['section_label' => 'Chapter']);
    $labelled = ReportCompiler::for($report->fresh()->load('sections'))->sections()[0];
    expect($labelled['marker'])->toBe('Chapter 1:');
});

it('bakes heading numbers and anchor ids into the section html', function () {
    $report = makeReport();

    $report->sections()->create([
        'order' => 0,
        'title' => 'Background',
        'content' => '<h2>Network Architecture</h2>',
    ]);

    $html = ReportCompiler::for($report->load('sections'))->sections()[0]['html'];

    expect($html)
        ->toContain('1.1.')
        ->toContain('id="h-')
        ->toContain('Network Architecture');
});

it('numbers tables and figures with anchor ids for the lists', function () {
    $report = makeReport();

    $report->sections()->create([
        'order' => 0,
        'title' => 'Analysis',
        'content' => '<table><caption>SWOT Analysis</caption><tbody><tr><td>x</td></tr></tbody></table>'
            .'<figure data-type="figure-image" class="report-figure"><img src="chart.png"><figcaption>Emissions</figcaption></figure>',
    ]);

    $compiler = ReportCompiler::for($report->load('sections'));

    expect($compiler->hasTables())->toBeTrue();
    expect($compiler->tables()[0])->toMatchArray([
        'number' => 1,
        'label' => 'Table 1: SWOT Analysis',
        'id' => 'tbl-1',
    ]);

    expect($compiler->hasFigures())->toBeTrue();
    expect($compiler->figures()[0])->toMatchArray([
        'number' => 1,
        'label' => 'Figure 1: Emissions',
        'id' => 'fig-1',
    ]);
});

it('numbers figures and tables sequentially across sections', function () {
    $report = makeReport();

    $report->sections()->create([
        'order' => 0,
        'title' => 'One',
        'content' => '<figure class="image"><img src="a.png"><figcaption>Alpha</figcaption></figure>'
            .'<table><caption>First</caption><tbody><tr><td>x</td></tr></tbody></table>',
    ]);
    $report->sections()->create([
        'order' => 1,
        'title' => 'Two',
        'content' => '<figure class="image"><img src="b.png"><figcaption>Beta</figcaption></figure>'
            .'<figure class="image"><img src="c.png"><figcaption>Gamma</figcaption></figure>'
            .'<table><caption>Second</caption><tbody><tr><td>y</td></tr></tbody></table>',
    ]);

    $compiler = ReportCompiler::for($report->load('sections'));

    expect(array_column($compiler->figures(), 'label'))->toBe([
        'Figure 1: Alpha',
        'Figure 2: Beta',
        'Figure 3: Gamma',
    ]);
    expect(array_column($compiler->tables(), 'label'))->toBe([
        'Table 1: First',
        'Table 2: Second',
    ]);
});

it('does not double a label already baked into the caption', function () {
    $report = makeReport();

    $report->sections()->create([
        'order' => 0,
        'title' => 'One',
        'content' => '<figure class="image"><img src="a.png"><figcaption>Figure 1: Alpha</figcaption></figure>'
            .'<figure class="image"><img src="b.png"><figcaption>Beta</figcaption></figure>',
    ]);

    $labels = array_column(ReportCompiler::for($report->load('sections'))->figures(), 'label');

    expect($labels)->toBe(['Figure 1: Alpha', 'Figure 2: Beta']);
});
