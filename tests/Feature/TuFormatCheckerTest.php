<?php

use App\Support\Checks\CheckResult;
use App\Support\Checks\ParsedPdf;
use App\Support\Checks\ReportPdfAnalyzer;
use App\Support\Checks\TuFormatChecker;

function tuStatusFor(array $results, string $label): string
{
    foreach ($results as $result) {
        if ($result->label === $label) {
            return $result->status;
        }
    }

    throw new RuntimeException("No check named {$label}");
}

function wellFormedTuReport(): ParsedPdf
{
    $titlePage = <<<'TITLE'
    TRIBHUVAN UNIVERSITY
    INSTITUTE OF ENGINEERING
    PULCHOWK CAMPUS

    A Study of Lean Operations in Hydrogen Combustion

    by
    Raj Maharjan

    A PROJECT REPORT
    SUBMITTED TO THE DEPARTMENT OF MECHANICAL ENGINEERING
    IN PARTIAL FULFILLMENT OF THE REQUIREMENTS FOR THE
    DEGREE OF BACHELOR OF ENGINEERING

    DEPARTMENT OF MECHANICAL ENGINEERING
    LALITPUR, NEPAL

    MAY, 2026
    TITLE;

    $approval = <<<'APPROVAL'
    TRIBHUVAN UNIVERSITY
    INSTITUTE OF ENGINEERING
    PULCHOWK CAMPUS

    The undersigned certify that they have read, and recommended to the
    Institute of Engineering for acceptance, a project report entitled...

    Supervisor, Dr. A. Sharma
    External Examiner, Dr. B. Karki
    Committee Chairperson, Dr. C. Thapa

    Date: 12 May 2026
    APPROVAL;

    $copyright = <<<'COPYRIGHTPAGE'
    COPYRIGHT

    The author has agreed that the library, Department of Mechanical
    Engineering, Pulchowk Campus, Institute of Engineering may make
    this report freely available for inspection...
    COPYRIGHTPAGE;

    $abstract = "Abstract\n\nThis report investigates flammability limits across temperatures.";

    $toc = "Table of Contents\n\nCHAPTER ONE: INTRODUCTION ..... 1\nCHAPTER TWO: METHODOLOGY ..... 12\nCHAPTER THREE: RESULTS ..... 30\nCHAPTER FOUR: DISCUSSION ..... 50\nREFERENCES ..... 70";

    $body = str_repeat('Lorem ipsum dolor sit amet consectetur adipiscing elit. ', 80);

    $chapter1 = "CHAPTER ONE: INTRODUCTION\nResults from Glassman (1987) inform this study.\n".$body."\n  4";
    $chapter2 = "CHAPTER TWO: METHODOLOGY\nFollowing Macek (1979), we sampled...\n".$body."\n  5";
    $chapter3 = "CHAPTER THREE: RESULTS\nResults align with Rees (1976) overall.\n".$body."\n  6";
    $chapter4 = "CHAPTER FOUR: DISCUSSION\nFinal thoughts.\n".$body."\n  7";

    $references = <<<'REFS'
    References

    Glassman, I., 1987, Combustion, Second edition, Academic Press, New York, ISBN 0-12-285851-4.
    Macek, A., 1979, "Flammability Limits: A Re-examination", Combustion Science and Technology, vol. 21, pp. 43-52.
    Rees, M., 1976, "The Ivory Tower and the Market Place", University of Utah Press, Salt Lake City, Utah.

      8
    REFS;

    return new ParsedPdf([
        $titlePage,
        $approval,
        $copyright,
        $abstract,
        $toc,
        $chapter1,
        $chapter2,
        $chapter3,
        $chapter4,
        $references,
    ]);
}

it('passes the structural checks on a well-formed TU report', function () {
    $results = (new TuFormatChecker)->check(wellFormedTuReport());

    expect(tuStatusFor($results, 'Title page'))->toBe(CheckResult::PASS)
        ->and(tuStatusFor($results, 'Approval page'))->toBe(CheckResult::PASS)
        ->and(tuStatusFor($results, 'Copyright page'))->toBe(CheckResult::PASS)
        ->and(tuStatusFor($results, 'Abstract'))->toBe(CheckResult::PASS)
        ->and(tuStatusFor($results, 'Table of contents'))->toBe(CheckResult::PASS)
        ->and(tuStatusFor($results, 'Chapter structure'))->toBe(CheckResult::PASS)
        ->and(tuStatusFor($results, 'References section'))->toBe(CheckResult::PASS);
});

it('passes citation and TU-reference-format checks on a well-formed report', function () {
    $results = (new TuFormatChecker)->check(wellFormedTuReport());

    expect(tuStatusFor($results, 'TU reference format'))->toBe(CheckResult::PASS)
        ->and(tuStatusFor($results, 'Alphabetical order'))->toBe(CheckResult::PASS)
        ->and(tuStatusFor($results, 'In-text citations'))->toBe(CheckResult::PASS)
        ->and(tuStatusFor($results, 'Citations match references'))->toBe(CheckResult::PASS)
        ->and(tuStatusFor($results, 'Orphan references'))->toBe(CheckResult::PASS);
});

it('fails the title-page check when TU header lines are missing', function () {
    $pdf = new ParsedPdf([
        "Title of Report\nBy Some Student\nMonth, Year",
        'Body',
    ]);

    $results = (new TuFormatChecker)->check($pdf);

    expect(tuStatusFor($results, 'Title page'))->toBe(CheckResult::FAIL);
});

it('fails the approval-page check when no "undersigned certify" text is found', function () {
    $pdf = new ParsedPdf([
        "TRIBHUVAN UNIVERSITY\nINSTITUTE OF ENGINEERING\nPULCHOWK CAMPUS\nLALITPUR, NEPAL\nDEGREE OF BACHELOR\nDEPARTMENT OF MECHANICAL",
        'Body',
        'References',
    ]);

    $results = (new TuFormatChecker)->check($pdf);

    expect(tuStatusFor($results, 'Approval page'))->toBe(CheckResult::FAIL);
});

it('fails the abstract check when the abstract exceeds 150 words', function () {
    $longAbstract = "Abstract\n\n".str_repeat('word ', 200);
    $pdf = new ParsedPdf([
        'title',
        $longAbstract,
        'Body',
    ]);

    $results = (new TuFormatChecker)->check($pdf);

    expect(tuStatusFor($results, 'Abstract'))->toBe(CheckResult::FAIL);
});

it('flags references that use the (YYYY) Harvard style instead of TU\'s , YYYY,', function () {
    $pdf = new ParsedPdf([
        'cover',
        "References\n\nGlassman, I. (1987) Combustion. Academic Press: New York.\nMacek, A. (1979) Flammability Limits. Some Journal.",
    ]);

    $results = (new TuFormatChecker)->check($pdf);

    expect(tuStatusFor($results, 'TU reference format'))->toBe(CheckResult::FAIL);
});

it('accepts a CSIT-style title page (Institute of Science and Technology, Amrit Campus, no LALITPUR, NEPAL)', function () {
    $titlePage = <<<'TITLE'
    Tribhuvan University
    Institute of Science and Technology
    Amrit Campus

    Project Work Report
    on
    Nepali Social Media Sentiment Analysis using Transformer

    Under the Supervision of
    Mr. Akkal Bahadur Bist
    Department of Computer Science & Information Technology
    Amrit Campus, Thamel, Kathmandu

    In partial fulfillment of the requirements for the Bachelor of Science in
    Computer Science and Information Technology (B.Sc. CSIT) of Tribhuvan University

    Submitted by:
    Mr. Megh Raj Rasaili / Roll No.700076 / Batch 2079

    February, 2026
    TITLE;

    $pdf = new ParsedPdf([$titlePage, 'body']);

    $results = (new TuFormatChecker)->check($pdf);

    expect(tuStatusFor($results, 'Title page'))->toBe(CheckResult::PASS);
});

it('exposes the TU checker via ReportPdfAnalyzer::availableFormats', function () {
    $formats = ReportPdfAnalyzer::availableFormats();

    expect($formats)->toHaveKey('tu')
        ->and($formats['tu'])->toBeInstanceOf(TuFormatChecker::class);
});
