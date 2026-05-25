<?php

use App\Models\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('deletes local <img src="/storage/..."> files when a report is deleted', function () {
    Storage::fake('public');

    Storage::disk('public')->put('section-images/figure-one.png', 'fake-binary-1');
    Storage::disk('public')->put('section-images/figure-two.png', 'fake-binary-2');

    $user = loginAsTestUser();
    $report = Report::factory()->for($user)->create();
    $report->sections()->create([
        'order' => 0,
        'title' => 'Intro',
        'content' => '<p>See <img src="/storage/section-images/figure-one.png" alt="One"> and '
                    .'<img src="storage/section-images/figure-two.png" alt="Two">.</p>',
    ]);

    Storage::disk('public')->assertExists('section-images/figure-one.png');
    Storage::disk('public')->assertExists('section-images/figure-two.png');

    $report->delete();

    Storage::disk('public')->assertMissing('section-images/figure-one.png');
    Storage::disk('public')->assertMissing('section-images/figure-two.png');
});

it('leaves unrelated files in the public disk alone when a report is deleted', function () {
    Storage::fake('public');

    Storage::disk('public')->put('section-images/somebody-else.png', 'still here');

    $user = loginAsTestUser();
    $report = Report::factory()->for($user)->create();
    $report->sections()->create([
        'order' => 0,
        'title' => 'Intro',
        'content' => '<p>No images in this section.</p>',
    ]);

    $report->delete();

    Storage::disk('public')->assertExists('section-images/somebody-else.png');
});

it('removes the uploaded PDF from disk after the report check finishes', function () {
    Storage::fake('local');
    loginAsTestUser();

    $pdf = UploadedFile::fake()->create('report.pdf', 50, 'application/pdf');

    Livewire::test('pages::report-check')
        ->set('format', 'london_met')
        ->set('pdf', $pdf)
        ->call('analyze');

    expect(Storage::disk('local')->files('livewire-tmp'))->toBeEmpty();
});
