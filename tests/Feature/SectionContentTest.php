<?php

use App\Support\SectionContent;

it('returns html content unchanged', function () {
    expect(SectionContent::toHtml('<p>Hello</p>'))->toBe('<p>Hello</p>');
});

it('returns an empty string for blank content', function () {
    expect(SectionContent::toHtml(null))->toBe('');
    expect(SectionContent::toHtml(''))->toBe('');
});

it('converts legacy TipTap JSON to html', function () {
    $json = json_encode([
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Intro']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [
                ['type' => 'text', 'text' => 'Bold bit', 'marks' => [['type' => 'bold']]],
            ]],
        ],
    ]);

    expect(SectionContent::toHtml($json))
        ->toBe('<p>Intro</p><h2><strong>Bold bit</strong></h2>');
});
