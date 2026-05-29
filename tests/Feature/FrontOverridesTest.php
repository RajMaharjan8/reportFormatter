<?php

use App\Models\Report;
use App\Models\User;

function overrideReport(array $overrides = []): Report
{
    return Report::create(array_merge([
        'user_id' => auth()->id() ?? loginAsTestUser()->id,
        'cover_format' => 'tu',
        'tu_college_name' => 'Amrit Campus',
        'tu_roll_number' => '250534',
        'title' => 'Flutter App',
        'student_name' => 'Raj Maharjan',
    ], $overrides));
}

it('offers an Edit pages button and an editable preview to the owner', function () {
    $report = overrideReport();

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('Edit pages');

    $this->get(route('reports.output', ['report' => $report, 'edit' => 1]))
        ->assertOk()
        ->assertSee('Save changes')
        ->assertSee('Reset all')
        ->assertSee('data-block="declaration"', false)
        ->assertSee('contenteditable', false);
});

it('saves a hand-edited front page and renders it in the report', function () {
    $report = overrideReport();

    $this->post(route('reports.front-overrides.save', $report), [
        'blocks' => ['declaration' => '<h2>My custom declaration</h2>'],
    ])->assertRedirect(route('reports.output', $report));

    expect($report->fresh()->frontOverride('declaration'))->toBe('<h2>My custom declaration</h2>');

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('My custom declaration')
        ->assertDontSee('I hereby declare that this project work');
});

it('ignores blocks that are not editable', function () {
    $report = overrideReport();

    $this->post(route('reports.front-overrides.save', $report), [
        'blocks' => ['totally_made_up' => '<p>nope</p>'],
    ])->assertRedirect();

    expect($report->fresh()->front_overrides)->toBeNull();
});

it('resets all overrides back to the generated template', function () {
    $report = overrideReport(['front_overrides' => ['declaration' => '<p>custom</p>']]);

    $this->post(route('reports.front-overrides.reset', $report))->assertRedirect();

    expect($report->fresh()->front_overrides)->toBeNull();

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('Student Declaration');
});

it('forbids editing another user\'s report', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->create(['cover_format' => 'tu']);

    loginAsTestUser();

    $this->post(route('reports.front-overrides.save', $report), [
        'blocks' => ['cover' => '<p>x</p>'],
    ])->assertForbidden();

    $this->post(route('reports.front-overrides.reset', $report))->assertForbidden();
});
