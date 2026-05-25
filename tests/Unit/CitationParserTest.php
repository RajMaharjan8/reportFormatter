<?php

use App\Support\Checks\CitationParser;

it('extracts a parenthesised citation', function () {
    $citations = (new CitationParser)->parse('Lean operations matter (Smith, 2021).');

    expect($citations)->toHaveCount(1)
        ->and($citations[0]['surname'])->toBe('Smith')
        ->and($citations[0]['year'])->toBe('2021');
});

it('extracts a citation with a page number', function () {
    $citations = (new CitationParser)->parse('Direct quote (Smith, 2021, p. 12).');

    expect($citations[0]['surname'])->toBe('Smith')
        ->and($citations[0]['year'])->toBe('2021');
});

it('extracts an et al. citation', function () {
    $citations = (new CitationParser)->parse('Studies show (Smith et al., 2021) the trend.');

    expect($citations[0]['surname'])->toBe('Smith')
        ->and($citations[0]['year'])->toBe('2021');
});

it('extracts a two-author citation', function () {
    $citations = (new CitationParser)->parse('(Smith and Doe, 2021) suggests that...');

    expect($citations[0]['surname'])->toBe('Smith')
        ->and($citations[0]['year'])->toBe('2021');
});

it('extracts a narrative citation', function () {
    $citations = (new CitationParser)->parse('Smith (2021) found that lean operations matter.');

    expect($citations)->toHaveCount(1)
        ->and($citations[0]['surname'])->toBe('Smith')
        ->and($citations[0]['year'])->toBe('2021');
});

it('returns no citations from plain text', function () {
    $citations = (new CitationParser)->parse('This paragraph has no citations.');

    expect($citations)->toBe([]);
});
