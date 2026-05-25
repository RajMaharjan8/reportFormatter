<?php

use App\Support\Checks\CheckResult;
use App\Support\Checks\LondonMetFormatChecker;
use App\Support\Checks\ParsedPdf;

function statusFor(array $results, string $label): string
{
    foreach ($results as $result) {
        if ($result->label === $label) {
            return $result->status;
        }
    }

    throw new RuntimeException("No check named {$label}");
}

function wellFormedReport(): ParsedPdf
{
    $cover = <<<'COVER'
    Islington College
    London Metropolitan University

    Module Code: MN7001NI
    Module Title: Operations Management

    Student Name: Raj Maharjan
    London Met ID: 25030253
    COVER;

    $contents = <<<'TOC'
    Table of Contents

    Abstract ............................................. i
    1. Introduction ........................................ 1
    2. Background .......................................... 5
    3. Discussion .......................................... 9
    4. Conclusion ......................................... 12
    References ........................................... 15
    TOC;

    $abstract = "Abstract\n\nThis report investigates...";

    $body = str_repeat('Lorem ipsum dolor sit amet consectetur adipiscing elit. ', 60);

    $intro = "1. Introduction\nLean operations matter (Smith, 2021) for performance.\n\n  3\n".$body;
    $background = "2. Background\nDoe (2020) explored supply chains.\n\n  4\n".$body;
    $discussion = "3. Discussion\nFurther evidence (Patel, 2019, p. 42) supports this.\n\n  5\n".$body;
    $conclusion = "4. Conclusion\nFinal thoughts.\n\n  6\n".$body;

    $references = <<<'REFS'
    References

    Doe, A. (2020) Supply Chain Networks. New York: Penguin.
       Second edition.
    Patel, R. (2019) Logistics. Manchester: WilyPress.
       Available at: https://example.org [Accessed 12 May 2026].
    Smith, J. (2021) Operations Management. London: SagePub.
       Third edition.

      7
    REFS;

    return new ParsedPdf([
        $cover,
        $contents,
        $abstract."\n".$body,
        $intro,
        $background,
        $discussion,
        $conclusion,
        $references,
    ]);
}

it('passes the structural checks on a well-formed London Met report', function () {
    $results = (new LondonMetFormatChecker)->check(wellFormedReport());

    expect(statusFor($results, 'Cover page'))->toBe(CheckResult::PASS)
        ->and(statusFor($results, 'Module code'))->toBe(CheckResult::PASS)
        ->and(statusFor($results, 'Student identity'))->toBe(CheckResult::PASS)
        ->and(statusFor($results, 'Table of contents'))->toBe(CheckResult::PASS)
        ->and(statusFor($results, 'Abstract'))->toBe(CheckResult::PASS)
        ->and(statusFor($results, 'Numbered sections'))->toBe(CheckResult::PASS)
        ->and(statusFor($results, 'References section'))->toBe(CheckResult::PASS);
});

it('passes the citation and reference checks on a well-formed report', function () {
    $results = (new LondonMetFormatChecker)->check(wellFormedReport());

    expect(statusFor($results, 'Reference entry format'))->toBe(CheckResult::PASS)
        ->and(statusFor($results, 'Alphabetical order'))->toBe(CheckResult::PASS)
        ->and(statusFor($results, 'In-text citations'))->toBe(CheckResult::PASS)
        ->and(statusFor($results, 'Citations match references'))->toBe(CheckResult::PASS)
        ->and(statusFor($results, 'Orphan references'))->toBe(CheckResult::PASS);
});

it('fails the cover-page check when university and college names are missing', function () {
    $pdf = new ParsedPdf([
        "Module Code: MN7001NI\nStudent Name: Raj\nLondon Met ID: 25030253",
        'Body text.',
    ]);

    $results = (new LondonMetFormatChecker)->check($pdf);

    expect(statusFor($results, 'Cover page'))->toBe(CheckResult::FAIL);
});

it('fails the module-code check when no code is present', function () {
    $pdf = new ParsedPdf([
        "Islington College\nLondon Metropolitan University\nStudent: Raj",
        'Body text.',
    ]);

    $results = (new LondonMetFormatChecker)->check($pdf);

    expect(statusFor($results, 'Module code'))->toBe(CheckResult::FAIL);
});

it('fails the references-section check when none exists', function () {
    $pdf = new ParsedPdf([
        "Islington College London Metropolitan University\nMN7001NI\nStudent: Raj 25030253",
        'Table of Contents',
        "1. Introduction\nbody\n  3",
        "2. Body\nmore body\n  4",
    ]);

    $results = (new LondonMetFormatChecker)->check($pdf);

    expect(statusFor($results, 'References section'))->toBe(CheckResult::FAIL);
});

it('flags references that are not in alphabetical order', function () {
    $pdf = new ParsedPdf([
        'cover',
        "References\n\nSmith, J. (2021) Title A. Publisher.\nDoe, A. (2020) Title B. Publisher.",
    ]);

    $results = (new LondonMetFormatChecker)->check($pdf);

    expect(statusFor($results, 'Alphabetical order'))->toBe(CheckResult::FAIL);
});

it('flags an online reference that has no [Accessed ...] date', function () {
    $pdf = new ParsedPdf([
        'cover',
        "References\n\nSmith, J. (2021) Web Title. Available at: https://example.com",
    ]);

    $results = (new LondonMetFormatChecker)->check($pdf);

    expect(statusFor($results, 'Online references'))->toBe(CheckResult::FAIL);
});

it('flags missing in-text citations when the body has none', function () {
    $pdf = new ParsedPdf([
        'cover',
        "1. Introduction\nNo citations here at all.\n\n2. Background\nStill nothing.",
        "References\n\nSmith, J. (2021) Title. Publisher.",
    ]);

    $results = (new LondonMetFormatChecker)->check($pdf);

    expect(statusFor($results, 'In-text citations'))->toBe(CheckResult::FAIL);
});

it('flags citations that have no matching reference entry', function () {
    $body = "1. Introduction\nFurther work (Nobody, 2024) is needed.";
    $pdf = new ParsedPdf([
        'cover',
        $body,
        "References\n\nSmith, J. (2021) Title. Publisher.",
    ]);

    $results = (new LondonMetFormatChecker)->check($pdf);

    expect(statusFor($results, 'Citations match references'))->toBe(CheckResult::FAIL);
});

it('warns about references that are never cited in the body', function () {
    $body = "1. Introduction\nThis claim is supported (Smith, 2021).";
    $pdf = new ParsedPdf([
        'cover',
        $body,
        "References\n\nDoe, A. (2020) Unused Reference. Publisher.\nSmith, J. (2021) Title. Publisher.",
    ]);

    $results = (new LondonMetFormatChecker)->check($pdf);

    expect(statusFor($results, 'Orphan references'))->toBe(CheckResult::WARN);
});

it('warns when reference entries lack a hanging indent', function () {
    $section = "References\n\n"
        ."Smith, J. (2021) A long title that wraps over more than one line for sure.\n"
        ."And the continuation line has no leading indent here.\n"
        ."Doe, A. (2020) Another long title that also wraps to a continuation line.\n"
        ."Continuation also without indent.\n";

    $pdf = new ParsedPdf([
        'cover',
        $section,
    ]);

    $results = (new LondonMetFormatChecker)->check($pdf);

    expect(statusFor($results, 'Hanging indent'))->toBeIn([CheckResult::WARN, CheckResult::FAIL]);
});

it('warns or fails when most pages carry no page number', function () {
    $pages = ['Islington College London Metropolitan University MN7001NI Student: Raj 25030253', 'Table of Contents'];
    foreach (range(1, 6) as $n) {
        $pages[] = $n <= 1
            ? "Body content without trailing index marker.\n  {$n}"
            : 'Body content without any trailing number.';
    }

    $results = (new LondonMetFormatChecker)->check(new ParsedPdf($pages));

    expect(statusFor($results, 'Page numbers'))->toBeIn([CheckResult::WARN, CheckResult::FAIL]);
});
