<?php

use App\Models\Report;

function makeReportForLayout(array $overrides = []): Report
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

it('defaults to the standard rule for margins and line spacing', function () {
    $report = makeReportForLayout();

    $margins = $report->pageMargins();

    expect($margins['top'])->toBe(1.0)
        ->and($margins['right'])->toBe(1.0)
        ->and($margins['bottom'])->toBe(1.0)
        ->and($margins['left'])->toBe(1.5)
        ->and($report->lineSpacing())->toBe(1.15);
});

it('persists user-chosen margins', function () {
    $report = makeReportForLayout();

    $this->post(route('reports.cover.settings', $report), [
        'margin_top' => 1.25,
        'margin_right' => 1.0,
        'margin_bottom' => 1.0,
        'margin_left' => 1.5,
    ])->assertRedirect();

    $fresh = $report->fresh();

    expect((float) $fresh->margin_top)->toBe(1.25)
        ->and((float) $fresh->margin_left)->toBe(1.5);
});

it('ignores line_spacing in the request — users cannot change it', function () {
    $report = makeReportForLayout();

    $this->post(route('reports.cover.settings', $report), [
        'line_spacing' => 2.5,
    ])->assertRedirect();

    expect((float) $report->fresh()->line_spacing)->toBe(1.15);
});

it('clamps margin and line-spacing values to a printable range', function () {
    $report = makeReportForLayout([
        'margin_top' => 99,
        'margin_left' => 0.01,
        'line_spacing' => 0.1,
    ]);

    expect($report->pageMargins()['top'])->toBe(3.0)
        ->and($report->pageMargins()['left'])->toBe(0.25)
        ->and($report->lineSpacing())->toBe(1.0);
});

it('renders the configured margins into the report output', function () {
    $report = makeReportForLayout(['margin_left' => 1.5, 'margin_top' => 1.0]);

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('1.00in')
        ->assertSee('1.50in');
});

it('defaults Heading 1 to centered, not capitalized', function () {
    $report = makeReportForLayout();

    expect($report->headingAlign())->toBe('center')
        ->and($report->heading_uppercase)->toBeFalse();
});

it('persists the Heading 1 alignment and capitalize options', function () {
    $report = makeReportForLayout();

    $this->post(route('reports.cover.settings', $report), [
        'heading_align' => 'left',
        'heading_uppercase' => '1',
    ])->assertRedirect();

    $fresh = $report->fresh();

    expect($fresh->headingAlign())->toBe('left')
        ->and($fresh->heading_uppercase)->toBeTrue();
});

it('treats an unchecked capitalize box as off', function () {
    $report = makeReportForLayout(['heading_uppercase' => true]);

    // The checkbox is omitted from the request when unticked.
    $this->post(route('reports.cover.settings', $report), [
        'heading_align' => 'center',
    ])->assertRedirect();

    expect($report->fresh()->heading_uppercase)->toBeFalse();
});

it('renders the chosen heading alignment and capitalize into the output', function () {
    $report = makeReportForLayout(['heading_align' => 'left', 'heading_uppercase' => true]);

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('text-align: left')
        ->assertSee('text-transform: uppercase');
});

it('rejects an invalid heading alignment', function () {
    $report = makeReportForLayout();

    $this->post(route('reports.cover.settings', $report), [
        'heading_align' => 'sideways',
    ])->assertSessionHasErrors('heading_align');
});

it('rejects out-of-range margins', function () {
    $report = makeReportForLayout();

    $this->post(route('reports.cover.settings', $report), [
        'margin_top' => 99,
    ])->assertSessionHasErrors('margin_top');
});
