<?php

use App\Models\Report;
use Livewire\Livewire;

function tuReport(array $overrides = []): Report
{
    return Report::create(array_merge([
        'cover_format' => 'tu',
        'tu_college_name' => "Institute of Engineering\nPulchowk Campus",
        'tu_roll_number' => '073/MSREE/519',
        'tu_submitted_to_position' => 'Department of Mechanical Engineering',
        'title' => 'Energy, Finance and Economics — Assignment No. 10',
        'student_name' => 'Sushil Paudel',
        'submitted_to' => 'Prof. Dr. Amrit Man Nakarmi',
        'submission_date' => '2026-05-19',
    ], $overrides));
}

it('defaults the cover format to london_met', function () {
    $report = Report::create([
        'module_code' => 'MN7001NI',
        'module_title' => 'Operations Management',
        'title' => 'Sample',
        'student_name' => 'Raj',
        'london_id' => '25030253',
        'college_id' => 'np01',
    ]);

    expect($report->fresh()->cover_format)->toBe('london_met');
});

it('renders the TU cover sheet with university name, college and roll number', function () {
    $report = tuReport();

    $this->get(route('reports.cover', $report))
        ->assertOk()
        ->assertSee('TRIBHUVAN UNIVERSITY')
        ->assertSee('Pulchowk Campus')
        ->assertSee('073/MSREE/519')
        ->assertSee('SUBMITTED TO:')
        ->assertSee('Department of Mechanical Engineering')
        ->assertSee('images/tu/tulogo.png')
        ->assertDontSee('London Metropolitan University');
});

it('renders the TU cover on the full report output', function () {
    $report = tuReport();

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('TRIBHUVAN UNIVERSITY')
        ->assertSee('073/MSREE/519')
        ->assertDontSee('Module Code');
});

it('keeps the London Met cover for london_met reports', function () {
    $report = Report::create([
        'cover_format' => 'london_met',
        'module_code' => 'MN7001NI',
        'module_title' => 'Operations Management',
        'title' => 'Amazon',
        'student_name' => 'Raj',
        'london_id' => '25030253',
        'college_id' => 'np01',
    ]);

    $this->get(route('reports.cover', $report))
        ->assertOk()
        ->assertSee('London Metropolitan University')
        ->assertDontSee('TRIBHUVAN UNIVERSITY');
});

it('saves a TU report from the form without London Met fields', function () {
    Livewire::test('pages::report-form')
        ->set('cover_format', 'tu')
        ->set('tu_college_name', 'Institute of Engineering')
        ->set('title', 'Assignment No. 10')
        ->set('student_name', 'Sushil Paudel')
        ->set('tu_roll_number', '073/MSREE/519')
        ->call('save')
        ->assertHasNoErrors();

    $report = Report::firstWhere('cover_format', 'tu');

    expect($report)->not->toBeNull()
        ->and($report->tu_roll_number)->toBe('073/MSREE/519')
        ->and($report->assignment_due_date)->toBeNull()
        ->and($report->submission_date)->toBeNull();
    expect(blank($report->module_code))->toBeTrue();
});

it('requires TU fields when the TU format is chosen', function () {
    Livewire::test('pages::report-form')
        ->set('cover_format', 'tu')
        ->call('save')
        ->assertHasErrors(['tu_college_name', 'title', 'student_name', 'tu_roll_number']);
});

it('does not require London Met fields when the TU format is chosen', function () {
    Livewire::test('pages::report-form')
        ->set('cover_format', 'tu')
        ->call('save')
        ->assertHasNoErrors(['module_code', 'module_title', 'london_id', 'college_id']);
});
