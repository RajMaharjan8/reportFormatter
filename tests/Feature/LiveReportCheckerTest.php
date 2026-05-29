<?php

use App\Models\Report;
use App\Models\User;
use App\Support\Checks\CheckResult;
use App\Support\Checks\LiveReportChecker;

function liveStatusFor(array $results, string $label): string
{
    foreach ($results as $result) {
        if ($result->label === $label) {
            return $result->status;
        }
    }

    throw new RuntimeException("No check named {$label}");
}

it('flags missing cover fields on a London Met report', function () {
    $user = loginAsTestUser();
    $report = Report::create([
        'user_id' => $user->id,
        'cover_format' => 'london_met',
    ]);

    $outcome = (new LiveReportChecker)->check($report->fresh());

    expect($outcome['format'])->toBe('London Metropolitan University')
        ->and(liveStatusFor($outcome['results'], 'Report title'))->toBe(CheckResult::FAIL)
        ->and(liveStatusFor($outcome['results'], 'Student name'))->toBe(CheckResult::FAIL)
        ->and(liveStatusFor($outcome['results'], 'Module code'))->toBe(CheckResult::FAIL);
});

it('passes London Met cover fields when filled in', function () {
    $user = loginAsTestUser();
    $report = Report::create([
        'user_id' => $user->id,
        'cover_format' => 'london_met',
        'title' => 'Operations Management',
        'student_name' => 'Raj Maharjan',
        'london_id' => '25030253',
        'module_code' => 'MN7001NI',
        'module_title' => 'Operations',
        'college_id' => 'IC123',
    ]);

    $outcome = (new LiveReportChecker)->check($report->fresh());

    expect(liveStatusFor($outcome['results'], 'Report title'))->toBe(CheckResult::PASS)
        ->and(liveStatusFor($outcome['results'], 'London Met ID'))->toBe(CheckResult::PASS)
        ->and(liveStatusFor($outcome['results'], 'Module code'))->toBe(CheckResult::PASS);
});

it('warns when the London Met ID is not 8 digits', function () {
    $user = loginAsTestUser();
    $report = Report::create([
        'user_id' => $user->id,
        'cover_format' => 'london_met',
        'title' => 'X',
        'student_name' => 'Raj',
        'london_id' => '12345',
        'module_code' => 'MN1',
        'module_title' => 'Y',
        'college_id' => 'IC',
    ]);

    $outcome = (new LiveReportChecker)->check($report->fresh());

    expect(liveStatusFor($outcome['results'], 'London Met ID'))->toBe(CheckResult::WARN);
});

it('fails when no body sections have been added', function () {
    $user = loginAsTestUser();
    $report = Report::create([
        'user_id' => $user->id,
        'cover_format' => 'london_met',
        'title' => 'X',
        'student_name' => 'Raj',
        'london_id' => '25030253',
        'module_code' => 'MN1',
        'module_title' => 'Y',
        'college_id' => 'IC',
    ]);

    $outcome = (new LiveReportChecker)->check($report->fresh());

    expect(liveStatusFor($outcome['results'], 'Body sections'))->toBe(CheckResult::FAIL);
});

it('passes body sections when 3+ filled sections exist', function () {
    $user = loginAsTestUser();
    $report = Report::create([
        'user_id' => $user->id,
        'cover_format' => 'london_met',
        'title' => 'X',
        'student_name' => 'Raj',
        'london_id' => '25030253',
        'module_code' => 'MN1',
        'module_title' => 'Y',
        'college_id' => 'IC',
    ]);

    foreach (['Introduction', 'Body', 'Conclusion'] as $i => $title) {
        $report->sections()->create([
            'order' => $i,
            'placement' => 'body',
            'title' => $title,
            'content' => "<p>Content for {$title}.</p>",
        ]);
    }

    $outcome = (new LiveReportChecker)->check($report->fresh());

    expect(liveStatusFor($outcome['results'], 'Body sections'))->toBe(CheckResult::PASS);
});

it('checks the TU-specific 150-word abstract limit', function () {
    $user = loginAsTestUser();
    $report = Report::create([
        'user_id' => $user->id,
        'cover_format' => 'tu',
        'title' => 'X',
        'student_name' => 'Raj',
        'tu_roll_number' => '073/MSREE/519',
        'tu_college_name' => 'Pulchowk Campus, Institute of Engineering',
        'tu_submitted_to_position' => 'Department of Mechanical Engineering',
        'abstract' => str_repeat('word ', 200),
    ]);

    $outcome = (new LiveReportChecker)->check($report->fresh());

    expect($outcome['format'])->toBe('Tribhuvan University (IOE)')
        ->and(liveStatusFor($outcome['results'], 'Abstract length (TU 150-word limit)'))->toBe(CheckResult::FAIL);
});

it('routes a signed-in user to their report\'s live check page', function () {
    $user = loginAsTestUser();
    $report = Report::factory()->for($user)->create();

    $this->get(route('reports.live-check', $report))->assertOk();
});

it('forbids viewing another user\'s live check page', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->for($owner)->create();

    loginAsTestUser();

    $this->get(route('reports.live-check', $report))->assertForbidden();
});
