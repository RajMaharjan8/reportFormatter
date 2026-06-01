<?php

use App\Models\CoverTemplate;
use App\Models\Report;
use Livewire\Livewire;

it('loads the cover designer for a signed-in user', function () {
    loginAsTestUser();

    $this->get(route('cover.templates'))
        ->assertOk()
        ->assertSee('Cover Designer');
});

it('saves a custom cover for the user', function () {
    $user = loginAsTestUser();

    Livewire::test('pages::cover-templates')
        ->set('name', 'My TU Cover')
        ->call('saveCover', '<h1>My Project</h1><p>Raj</p>')
        ->assertHasNoErrors();

    $template = CoverTemplate::firstWhere('user_id', $user->id);

    expect($template)->not->toBeNull()
        ->and($template->name)->toBe('My TU Cover')
        ->and($template->html)->toContain('My Project');
});

it('caps saved covers at three per user', function () {
    $user = loginAsTestUser();

    foreach (['A', 'B', 'C'] as $name) {
        CoverTemplate::create(['user_id' => $user->id, 'name' => $name, 'html' => '<p>x</p>']);
    }

    Livewire::test('pages::cover-templates')
        ->set('name', 'Fourth')
        ->call('saveCover', '<p>nope</p>')
        ->assertHasErrors('name');

    expect(CoverTemplate::where('user_id', $user->id)->count())->toBe(3);
});

it('deletes only the owner\'s cover', function () {
    $user = loginAsTestUser();
    $template = CoverTemplate::create(['user_id' => $user->id, 'name' => 'Gone', 'html' => '<p>x</p>']);

    Livewire::test('pages::cover-templates')
        ->call('delete', $template->id);

    expect(CoverTemplate::find($template->id))->toBeNull();
});

it('lets a new report pick a custom cover from the form', function () {
    $user = loginAsTestUser();
    $template = CoverTemplate::create(['user_id' => $user->id, 'name' => 'Mine', 'html' => '<h1>Designed Cover</h1>']);

    Livewire::test('pages::report-form')
        ->set('cover_format', 'custom')
        ->set('title', 'My Report')
        ->set('custom_cover_id', $template->id)
        ->call('save')
        ->assertHasNoErrors();

    $report = Report::firstWhere('cover_format', 'custom');

    expect($report)->not->toBeNull()
        ->and($report->frontOverride('cover'))->toContain('Designed Cover');
});

it('requires choosing a cover when the custom format is selected', function () {
    $user = loginAsTestUser();
    CoverTemplate::create(['user_id' => $user->id, 'name' => 'Mine', 'html' => '<p>x</p>']);

    Livewire::test('pages::report-form')
        ->set('cover_format', 'custom')
        ->set('title', 'My Report')
        ->call('save')
        ->assertHasErrors('custom_cover_id');
});

it('applies a saved cover to a report and renders it', function () {
    $user = loginAsTestUser();

    $report = Report::create([
        'user_id' => $user->id,
        'cover_format' => 'tu',
        'tu_college_name' => 'Amrit Campus',
        'tu_roll_number' => '250534',
        'title' => 'My Report',
        'student_name' => 'Raj',
    ]);

    $template = CoverTemplate::create([
        'user_id' => $user->id,
        'name' => 'Fancy',
        'html' => '<h1>Custom Cover Heading</h1>',
    ]);

    $this->post(route('reports.cover.use-template', $report), ['template_id' => $template->id])
        ->assertRedirect(route('reports.cover', $report));

    expect($report->fresh()->frontOverride('cover'))->toContain('<h1>Custom Cover Heading</h1>');

    $this->get(route('reports.output', $report))
        ->assertOk()
        ->assertSee('Custom Cover Heading');
});
