<?php

namespace App\Support;

/**
 * Normalises stored section content to HTML.
 *
 * Sections written by the current editor are already HTML. Sections written
 * by the previous (TipTap) editor were stored as TipTap JSON; this converts
 * that legacy format so it still renders in CKEditor and the report output.
 */
class SectionContent
{
    public static function toHtml(?string $raw): string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return '';
        }

        // HTML is used as-is; only legacy TipTap JSON needs conversion.
        if (! str_starts_with($raw, '{')) {
            return $raw;
        }

        $doc = json_decode($raw, true);

        if (! is_array($doc) || ($doc['type'] ?? null) !== 'doc') {
            return $raw;
        }

        return self::renderNodes($doc['content'] ?? []);
    }

    /** @param  array<int, mixed>  $nodes */
    protected static function renderNodes(array $nodes): string
    {
        return implode('', array_map(
            fn ($node) => is_array($node) ? self::renderNode($node) : '',
            $nodes,
        ));
    }

    /** @param  array<string, mixed>  $node */
    protected static function renderNode(array $node): string
    {
        $children = self::renderNodes($node['content'] ?? []);

        return match ($node['type'] ?? '') {
            'text' => self::renderText($node),
            'paragraph' => "<p>{$children}</p>",
            'heading' => self::renderHeading($node, $children),
            'bulletList' => "<ul>{$children}</ul>",
            'orderedList' => "<ol>{$children}</ol>",
            'listItem' => "<li>{$children}</li>",
            'blockquote' => "<blockquote>{$children}</blockquote>",
            'codeBlock' => "<pre><code>{$children}</code></pre>",
            'hardBreak' => '<br>',
            'horizontalRule' => '<hr>',
            'table' => "<table><tbody>{$children}</tbody></table>",
            'tableRow' => "<tr>{$children}</tr>",
            'tableHeader' => "<th>{$children}</th>",
            'tableCell' => "<td>{$children}</td>",
            'captionedImage', 'image' => self::renderImage($node),
            default => $children,
        };
    }

    /** @param  array<string, mixed>  $node */
    protected static function renderHeading(array $node, string $children): string
    {
        $level = max(1, min(3, (int) ($node['attrs']['level'] ?? 2)));

        return "<h{$level}>{$children}</h{$level}>";
    }

    /** @param  array<string, mixed>  $node */
    protected static function renderImage(array $node): string
    {
        $src = trim((string) ($node['attrs']['src'] ?? ''));

        if ($src === '') {
            return '';
        }

        $caption = trim((string) ($node['attrs']['caption'] ?? ''));
        $figcaption = $caption !== ''
            ? '<figcaption>'.htmlspecialchars($caption, ENT_QUOTES).'</figcaption>'
            : '';

        return '<figure class="image"><img src="'.htmlspecialchars($src, ENT_QUOTES).'">'.$figcaption.'</figure>';
    }

    /** @param  array<string, mixed>  $node */
    protected static function renderText(array $node): string
    {
        $text = htmlspecialchars((string) ($node['text'] ?? ''), ENT_QUOTES);

        foreach (array_reverse($node['marks'] ?? []) as $mark) {
            $text = self::applyMark(is_array($mark) ? $mark : [], $text);
        }

        return $text;
    }

    /** @param  array<string, mixed>  $mark */
    protected static function applyMark(array $mark, string $text): string
    {
        return match ($mark['type'] ?? '') {
            'bold' => "<strong>{$text}</strong>",
            'italic' => "<em>{$text}</em>",
            'underline' => "<u>{$text}</u>",
            'strike' => "<s>{$text}</s>",
            'code' => "<code>{$text}</code>",
            'link' => '<a href="'.htmlspecialchars((string) ($mark['attrs']['href'] ?? '#'), ENT_QUOTES).'">'.$text.'</a>',
            default => $text,
        };
    }
}
