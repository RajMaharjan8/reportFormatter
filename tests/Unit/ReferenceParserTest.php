<?php

use App\Support\Checks\ReferenceParser;

it('returns an empty result when no references section is present', function () {
    $result = (new ReferenceParser)->parse("1. Introduction\nSome body text.");

    expect($result['text'])->toBe('')
        ->and($result['entries'])->toBe([]);
});

it('isolates everything after the References heading', function () {
    $text = "Body text here.\n\nReferences\n\nSmith, J. (2021) Operations Management. London: SagePub.";

    $result = (new ReferenceParser)->parse($text);

    expect($result['text'])->toContain('Smith, J. (2021)')
        ->and($result['text'])->not->toContain('Body text here');
});

it('splits multiple entries by author-line heuristic', function () {
    $text = <<<'TXT'
    References

    Smith, J. (2021) Operations Management. London: SagePub.
    Doe, A. (2020) Supply Chain Networks. New York: Penguin.
    Patel, R. (2019) Logistics. Manchester: WilyPress.
    TXT;

    $result = (new ReferenceParser)->parse($text);

    expect($result['entries'])->toHaveCount(3)
        ->and($result['entries'][0]['surname'])->toBe('Smith')
        ->and($result['entries'][0]['year'])->toBe('2021')
        ->and($result['entries'][1]['surname'])->toBe('Doe')
        ->and($result['entries'][2]['surname'])->toBe('Patel');
});

it('records URLs and only counts has_access_date when [Accessed ...] is present', function () {
    $text = <<<'TXT'
    References

    Smith, J. (2021) Operations Management. Available at: https://example.com [Accessed 12 May 2026].
    Doe, A. (2020) Logistics. Available at: https://example.org
    TXT;

    $result = (new ReferenceParser)->parse($text);

    expect($result['entries'][0]['has_url'])->toBeTrue()
        ->and($result['entries'][0]['has_access_date'])->toBeTrue()
        ->and($result['entries'][1]['has_url'])->toBeTrue()
        ->and($result['entries'][1]['has_access_date'])->toBeFalse();
});

it('records how many continuation lines look hanging-indented', function () {
    $text = "References\n\nSmith, J. (2021) Operations Management.\n    London: SagePub.\n    Second edition.\nDoe, A. (2020) Logistics.";

    $result = (new ReferenceParser)->parse($text);

    expect($result['entries'])->toHaveCount(2)
        ->and($result['entries'][0]['continuation_lines'])->toBe(2)
        ->and($result['entries'][0]['hanging_indent_lines'])->toBe(2)
        ->and($result['entries'][1]['continuation_lines'])->toBe(0);
});
