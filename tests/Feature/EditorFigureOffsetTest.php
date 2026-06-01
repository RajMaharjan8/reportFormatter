<?php

use App\Models\Report;
use Livewire\Livewire;

it('continues figure and table numbering across sections in the editor', function () {
    loginAsTestUser();

    $report = Report::create([
        'user_id' => auth()->id(),
        'cover_format' => 'london_met',
        'module_code' => 'MN7001NI',
        'module_title' => 'Ops',
        'title' => 'X',
        'student_name' => 'Raj',
        'london_id' => '1',
        'college_id' => '1',
    ]);

    $report->sections()->create([
        'order' => 0, 'placement' => 'body', 'title' => 'One',
        'content' => '<figure class="image"><img src="a.png"><figcaption>x</figcaption></figure>'
            .'<table><caption>t</caption><tbody><tr><td>1</td></tr></tbody></table>',
    ]);
    $second = $report->sections()->create([
        'order' => 1, 'placement' => 'body', 'title' => 'Two', 'content' => '<p>hi</p>',
    ]);

    $component = Livewire::test('pages::report-sections', ['report' => $report])
        ->call('selectSection', $second->id);

    expect($component->instance()->figureTableOffset)->toBe(['figures' => 1, 'tables' => 1]);
});
