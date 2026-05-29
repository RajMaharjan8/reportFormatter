<?php

use App\Models\Report;
use App\Models\User;

it('redirects guests to the login page when visiting the dashboard', function () {
    $this->get(route('reports.index'))->assertRedirect(route('login'));
});

it('redirects guests away from creating a report', function () {
    $this->get(route('reports.create'))->assertRedirect(route('login'));
});

it('lets a signed-in user open the dashboard', function () {
    loginAsTestUser();

    $this->get(route('reports.index'))->assertOk();
});

it('only shows a user their own reports on the dashboard', function () {
    $alice = loginAsTestUser();
    Report::factory()->for($alice)->create(['student_name' => 'Alice One']);

    $bob = User::factory()->create();
    Report::factory()->for($bob)->create(['student_name' => 'Bob One']);

    $this->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Alice One')
        ->assertDontSee('Bob One');
});

it('forbids viewing another user\'s report', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->create();

    loginAsTestUser();

    $this->get(route('reports.edit', $report))->assertForbidden();
    $this->get(route('reports.sections', $report))->assertForbidden();
    $this->get(route('reports.cover', $report))->assertForbidden();
    $this->get(route('reports.output', $report))->assertForbidden();
});

it('redirects to the dashboard with a friendly notice when the user already has two reports', function () {
    $user = loginAsTestUser();
    Report::factory()->for($user)->count(User::MAX_REPORTS)->create();

    expect($user->fresh()->hasReachedReportLimit())->toBeTrue();

    Livewire::test('pages::report-form')
        ->assertRedirect(route('reports.index'));

    expect(session('report-limit'))->toContain('delete one of your existing reports')
        ->and($user->reports()->count())->toBe(User::MAX_REPORTS);
});

it('allows creating a report when under the limit', function () {
    $user = loginAsTestUser();

    Livewire::test('pages::report-form')
        ->set('cover_format', 'london_met')
        ->set('module_code', 'MN7001')
        ->set('module_title', 'Operations')
        ->set('title', 'First Report')
        ->set('student_name', 'Raj')
        ->set('london_id', '25030253')
        ->set('college_id', 'np01')
        ->call('save');

    expect($user->reports()->count())->toBe(1)
        ->and($user->reports()->first()->title)->toBe('First Report');
});

it('treats a group project with many students as a single report', function () {
    $user = loginAsTestUser();

    Livewire::test('pages::report-form')
        ->set('cover_format', 'tu')
        ->set('tu_college_name', 'Islington College')
        ->set('title', 'Group Project')
        ->set('student_name', 'Lead Student')
        ->set('tu_roll_number', '700076')
        ->call('addTuStudent')
        ->call('addTuStudent')
        ->call('addTuStudent')
        ->call('save');

    expect($user->reports()->count())->toBe(1)
        ->and($user->fresh()->hasReachedReportLimit())->toBeFalse();
});

it('deleting a report frees a slot to create another', function () {
    $user = loginAsTestUser();
    $reports = Report::factory()->for($user)->count(User::MAX_REPORTS)->create();

    expect($user->fresh()->hasReachedReportLimit())->toBeTrue();

    $reports->first()->delete();

    expect($user->fresh()->hasReachedReportLimit())->toBeFalse();
});
