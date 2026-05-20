<?php

namespace App\Support;

use App\Models\Reference;

/**
 * Builds inline citations and bibliography entries in IEEE, APA, or
 * London Met (Harvard-style) format from a Reference model.
 *
 * Inline citations are plain strings — "(Smith, 2023)" for author-year styles
 * and "[3]" for IEEE. The IEEE number is computed by sorting the supplied list
 * of references alphabetically and then mapping each id to its 1-based index.
 */
class CitationFormatter
{
    public const FORMATS = ['ieee', 'apa', 'london_met'];

    /**
     * @param  iterable<Reference>  $references
     */
    public function __construct(protected string $format, iterable $references = [])
    {
        $this->format = \in_array($format, self::FORMATS, true) ? $format : 'london_met';

        $sorted = collect($references)
            ->sortBy(fn (Reference $r) => $r->sortKey())
            ->values();

        foreach ($sorted as $index => $reference) {
            $this->order[(int) $reference->id] = $index + 1;
        }
    }

    /** @var array<int, int> */
    protected array $order = [];

    public function format(): string
    {
        return $this->format;
    }

    /**
     * The short text that replaces an inline `[REF:id]` placeholder.
     */
    public function inline(Reference $reference): string
    {
        return match ($this->format) {
            'ieee' => '['.($this->order[(int) $reference->id] ?? '?').']',
            default => $this->authorYear($reference),
        };
    }

    /**
     * The full bibliography entry as a plain string (no surrounding tags).
     */
    public function bibliography(Reference $reference): string
    {
        return match ($this->format) {
            'ieee' => $this->ieeeEntry($reference),
            'apa' => $this->apaEntry($reference),
            default => $this->londonMetEntry($reference),
        };
    }

    /**
     * The leading marker shown in the bibliography list — "[1] " for IEEE and
     * an empty string for author-year styles (where the entry begins with the
     * author and year directly).
     */
    public function bibliographyMarker(Reference $reference): string
    {
        if ($this->format === 'ieee') {
            return '['.($this->order[(int) $reference->id] ?? '?').'] ';
        }

        return '';
    }

    // ------------------------------------------------------------------
    // Inline helpers
    // ------------------------------------------------------------------

    protected function authorYear(Reference $reference): string
    {
        $author = $this->shortAuthor($reference);
        $year = trim((string) $reference->field('year', 'n.d.'));

        if ($author === '' && $year === '') {
            return '(n.d.)';
        }

        return $author === ''
            ? "({$year})"
            : "({$author}, {$year})";
    }

    /**
     * Pick the surname (or organisation name) used in an inline (author, year)
     * citation. For 2 authors join with "and"; 3+ becomes "Smith et al.".
     */
    protected function shortAuthor(Reference $reference): string
    {
        $authors = $this->splitAuthors($reference);

        if ($authors === []) {
            return '';
        }

        $surnames = array_map(fn ($name) => $this->surname($name), $authors);

        return match (true) {
            count($surnames) === 1 => $surnames[0],
            count($surnames) === 2 => $surnames[0].' and '.$surnames[1],
            default => $surnames[0].' et al.',
        };
    }

    /**
     * Read a Reference field as a plain string with HTML special characters
     * escaped — the bibliography then composes those values into rich HTML
     * using its own controlled `<em>` and punctuation markup.
     */
    protected function safe(Reference $reference, string $key, string $default = ''): string
    {
        $raw = $reference->field($key, $default);
        $raw = \is_array($raw) ? implode(', ', $raw) : (string) $raw;

        return htmlspecialchars(trim($raw), ENT_QUOTES);
    }

    /**
     * @return list<string>
     */
    protected function splitAuthors(Reference $reference): array
    {
        $raw = $reference->field('authors', '');

        if (\is_array($raw)) {
            $items = $raw;
        } else {
            $items = preg_split('/\s*(?:;| and |,(?=\s*\S+\s+\S))\s*/', (string) $raw) ?: [];
        }

        return array_values(array_filter(array_map('trim', $items), fn ($x) => $x !== ''));
    }

    protected function surname(string $name): string
    {
        $name = trim($name);

        if (str_contains($name, ',')) {
            return trim(explode(',', $name)[0]);
        }

        $parts = preg_split('/\s+/', $name) ?: [];

        if ($parts === []) {
            return $name;
        }

        return (string) end($parts);
    }

    // ------------------------------------------------------------------
    // Bibliography helpers
    // ------------------------------------------------------------------

    /**
     * Format the authors block for the bibliography. Author-year formats lead
     * with surname first ("Smith, J."), IEEE leads with initials ("J. Smith").
     */
    protected function authorsBlock(Reference $reference, bool $surnameFirst): string
    {
        $authors = $this->splitAuthors($reference);

        if ($authors === []) {
            return '';
        }

        $formatted = array_map(
            fn ($name) => htmlspecialchars(
                $surnameFirst ? $this->surnameFirst($name) : $this->initialsFirst($name),
                ENT_QUOTES,
            ),
            $authors,
        );

        return implode(', ', $formatted);
    }

    /** "John Smith" → "Smith, J." (already-formatted entries are left alone). */
    protected function surnameFirst(string $name): string
    {
        $name = trim($name);

        if ($name === '' || str_contains($name, ',')) {
            return $name;
        }

        $parts = preg_split('/\s+/', $name) ?: [];

        if (count($parts) < 2) {
            return $name;
        }

        $surname = array_pop($parts);
        $initials = implode('. ', array_map(fn ($p) => mb_substr($p, 0, 1), $parts)).'.';

        return $surname.', '.$initials;
    }

    /** "Smith, John" or "John Smith" → "J. Smith". */
    protected function initialsFirst(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        if (str_contains($name, ',')) {
            [$surname, $rest] = array_pad(array_map('trim', explode(',', $name, 2)), 2, '');
            $initials = $this->initialsFromGivenNames($rest);

            return trim($initials.' '.$surname);
        }

        $parts = preg_split('/\s+/', $name) ?: [];

        if (count($parts) < 2) {
            return $name;
        }

        $surname = array_pop($parts);
        $initials = $this->initialsFromGivenNames(implode(' ', $parts));

        return trim($initials.' '.$surname);
    }

    protected function initialsFromGivenNames(string $given): string
    {
        $given = trim($given);

        if ($given === '') {
            return '';
        }

        if (preg_match('/^[A-Z](\.[A-Z])*\.?$/', str_replace(' ', '', $given))) {
            return $given;
        }

        $parts = preg_split('/\s+/', $given) ?: [];

        return implode(' ', array_filter(array_map(
            fn ($p) => $p === '' ? '' : mb_substr($p, 0, 1).'.',
            $parts,
        )));
    }

    /**
     * London Met (Harvard) bibliography entry.
     *
     * Examples:
     *   Smith, J. (2023) 'Title of paper', Journal Name, 10(2), pp. 1-12.
     *   Doe, A. (2021) Book Title. 2nd edn. London: Routledge.
     *   BBC (2022) Article title. Available at: https://example.com (Accessed: 1 May 2026).
     */
    protected function londonMetEntry(Reference $reference): string
    {
        $authors = $this->authorsBlock($reference, surnameFirst: true);
        $year = $this->safe($reference, 'year', 'n.d.');
        $title = $this->safe($reference, 'title');

        $lead = $authors === '' ? '' : $authors.' ';
        $lead .= "({$year})";

        return match ($reference->type) {
            'journal' => $this->londonMetJournal($reference, $lead, $title),
            'book' => $this->londonMetBook($reference, $lead, $title),
            'url' => $this->londonMetUrl($reference, $lead, $title),
            default => $this->londonMetArticle($reference, $lead, $title),
        };
    }

    protected function londonMetJournal(Reference $reference, string $lead, string $title): string
    {
        $journal = $this->safe($reference, 'journal');
        $volume = $this->safe($reference, 'volume');
        $issue = $this->safe($reference, 'issue');
        $pages = $this->safe($reference, 'pages');

        $tail = "<em>{$journal}</em>";

        if ($volume !== '') {
            $tail .= ", {$volume}";

            if ($issue !== '') {
                $tail .= "({$issue})";
            }
        }

        if ($pages !== '') {
            $tail .= ', pp. '.$pages;
        }

        return $lead." '{$title}', {$tail}.";
    }

    protected function londonMetBook(Reference $reference, string $lead, string $title): string
    {
        $edition = $this->safe($reference, 'edition');
        $place = $this->safe($reference, 'place');
        $publisher = $this->safe($reference, 'publisher');

        $entry = "{$lead} <em>{$title}</em>.";

        if ($edition !== '') {
            $entry .= " {$edition} edn.";
        }

        $location = trim(implode(': ', array_filter([$place, $publisher])));

        if ($location !== '') {
            $entry .= " {$location}.";
        }

        return $entry;
    }

    protected function londonMetUrl(Reference $reference, string $lead, string $title): string
    {
        $site = $this->safe($reference, 'site_name');
        $url = $this->safe($reference, 'url');
        $accessed = $this->safe($reference, 'accessed');

        $entry = $lead.' '.($title !== '' ? "<em>{$title}</em>." : '');

        if ($site !== '') {
            $entry .= " {$site}.";
        }

        if ($url !== '') {
            $entry .= ' Available at: '.$url;
        }

        if ($accessed !== '') {
            $entry .= ' (Accessed: '.$accessed.')';
        }

        return rtrim($entry, ' ').'.';
    }

    protected function londonMetArticle(Reference $reference, string $lead, string $title): string
    {
        $publication = $this->safe($reference, 'publication', (string) $reference->field('site_name', ''));
        $url = $this->safe($reference, 'url');
        $accessed = $this->safe($reference, 'accessed');

        $entry = $lead." '{$title}'";

        if ($publication !== '') {
            $entry .= ", <em>{$publication}</em>";
        }

        $entry .= '.';

        if ($url !== '') {
            $entry .= ' Available at: '.$url;

            if ($accessed !== '') {
                $entry .= ' (Accessed: '.$accessed.')';
            }

            $entry .= '.';
        }

        return $entry;
    }

    /**
     * APA-7 style bibliography entry.
     */
    protected function apaEntry(Reference $reference): string
    {
        $authors = $this->authorsBlock($reference, surnameFirst: true);
        $year = $this->safe($reference, 'year', 'n.d.');
        $title = $this->safe($reference, 'title');

        $lead = $authors === '' ? "({$year})." : "{$authors} ({$year}).";

        return match ($reference->type) {
            'journal' => $this->apaJournal($reference, $lead, $title),
            'book' => $this->apaBook($reference, $lead, $title),
            'url' => $this->apaUrl($reference, $lead, $title),
            default => $this->apaArticle($reference, $lead, $title),
        };
    }

    protected function apaJournal(Reference $reference, string $lead, string $title): string
    {
        $journal = $this->safe($reference, 'journal');
        $volume = $this->safe($reference, 'volume');
        $issue = $this->safe($reference, 'issue');
        $pages = $this->safe($reference, 'pages');

        $tail = "<em>{$journal}</em>";

        if ($volume !== '') {
            $tail .= ", <em>{$volume}</em>";

            if ($issue !== '') {
                $tail .= "({$issue})";
            }
        }

        if ($pages !== '') {
            $tail .= ', '.$pages;
        }

        return "{$lead} {$title}. {$tail}.";
    }

    protected function apaBook(Reference $reference, string $lead, string $title): string
    {
        $edition = $this->safe($reference, 'edition');
        $publisher = $this->safe($reference, 'publisher');

        $entry = "{$lead} <em>{$title}</em>";

        if ($edition !== '') {
            $entry .= " ({$edition} ed.)";
        }

        $entry .= '.';

        if ($publisher !== '') {
            $entry .= ' '.$publisher.'.';
        }

        return $entry;
    }

    protected function apaUrl(Reference $reference, string $lead, string $title): string
    {
        $site = $this->safe($reference, 'site_name');
        $url = $this->safe($reference, 'url');

        $entry = $lead;

        if ($title !== '') {
            $entry .= " <em>{$title}</em>.";
        }

        if ($site !== '') {
            $entry .= " {$site}.";
        }

        if ($url !== '') {
            $entry .= ' '.$url;
        }

        return $entry;
    }

    protected function apaArticle(Reference $reference, string $lead, string $title): string
    {
        $publication = $this->safe($reference, 'publication', (string) $reference->field('site_name', ''));
        $url = $this->safe($reference, 'url');

        $entry = "{$lead} {$title}.";

        if ($publication !== '') {
            $entry .= " <em>{$publication}</em>.";
        }

        if ($url !== '') {
            $entry .= ' '.$url;
        }

        return $entry;
    }

    /**
     * IEEE bibliography entry.
     *
     * Example:
     *   J. Smith, "Title of paper," Journal Name, vol. 10, no. 2, pp. 1-12, 2023.
     */
    protected function ieeeEntry(Reference $reference): string
    {
        $authors = $this->authorsBlock($reference, surnameFirst: false);
        $year = $this->safe($reference, 'year');
        $title = $this->safe($reference, 'title');

        $lead = $authors === '' ? '' : $authors.', ';

        return match ($reference->type) {
            'journal' => $this->ieeeJournal($reference, $lead, $title, $year),
            'book' => $this->ieeeBook($reference, $lead, $title, $year),
            'url' => $this->ieeeUrl($reference, $lead, $title, $year),
            default => $this->ieeeArticle($reference, $lead, $title, $year),
        };
    }

    protected function ieeeJournal(Reference $reference, string $lead, string $title, string $year): string
    {
        $journal = $this->safe($reference, 'journal');
        $volume = $this->safe($reference, 'volume');
        $issue = $this->safe($reference, 'issue');
        $pages = $this->safe($reference, 'pages');

        $entry = $lead.'"'.$title.',"';

        if ($journal !== '') {
            $entry .= ' <em>'.$journal.'</em>,';
        }

        if ($volume !== '') {
            $entry .= ' vol. '.$volume.',';
        }

        if ($issue !== '') {
            $entry .= ' no. '.$issue.',';
        }

        if ($pages !== '') {
            $entry .= ' pp. '.$pages.',';
        }

        if ($year !== '') {
            $entry .= ' '.$year.',';
        }

        return rtrim($entry, ', ').'.';
    }

    protected function ieeeBook(Reference $reference, string $lead, string $title, string $year): string
    {
        $edition = $this->safe($reference, 'edition');
        $place = $this->safe($reference, 'place');
        $publisher = $this->safe($reference, 'publisher');

        $entry = $lead.'<em>'.$title.'</em>,';

        if ($edition !== '') {
            $entry .= ' '.$edition.' ed.,';
        }

        $location = trim(implode(': ', array_filter([$place, $publisher])));

        if ($location !== '') {
            $entry .= ' '.$location.',';
        }

        if ($year !== '') {
            $entry .= ' '.$year.',';
        }

        return rtrim($entry, ', ').'.';
    }

    protected function ieeeUrl(Reference $reference, string $lead, string $title, string $year): string
    {
        $site = $this->safe($reference, 'site_name');
        $url = $this->safe($reference, 'url');
        $accessed = $this->safe($reference, 'accessed');

        $entry = $lead.'"'.$title.',"';

        if ($site !== '') {
            $entry .= ' <em>'.$site.'</em>,';
        }

        if ($year !== '') {
            $entry .= ' '.$year.'.';
        }

        if ($url !== '') {
            $entry .= ' [Online]. Available: '.$url;
        }

        if ($accessed !== '') {
            $entry .= '. [Accessed: '.$accessed.']';
        }

        return rtrim($entry, ', ').'.';
    }

    protected function ieeeArticle(Reference $reference, string $lead, string $title, string $year): string
    {
        $publication = $this->safe($reference, 'publication', (string) $reference->field('site_name', ''));
        $url = $this->safe($reference, 'url');

        $entry = $lead.'"'.$title.',"';

        if ($publication !== '') {
            $entry .= ' <em>'.$publication.'</em>,';
        }

        if ($year !== '') {
            $entry .= ' '.$year.',';
        }

        if ($url !== '') {
            $entry .= ' [Online]. Available: '.$url;
        }

        return rtrim($entry, ', ').'.';
    }
}
