<?php

namespace App\Models;

use Database\Factories\ReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reference extends Model
{
    /** @use HasFactory<ReferenceFactory> */
    use HasFactory;

    public const TYPES = ['url', 'journal', 'book', 'article'];

    protected $fillable = [
        'report_id',
        'type',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Read a single field from the JSON `data` blob.
     */
    public function field(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * The first author's surname, used to sort the bibliography alphabetically.
     */
    public function sortKey(): string
    {
        $authors = $this->field('authors', '');
        $authors = is_array($authors) ? implode(', ', $authors) : (string) $authors;
        $first = trim(explode(',', $authors)[0] ?? '');

        if ($first === '') {
            return strtolower((string) $this->field('title', $this->field('site_name', '')));
        }

        $parts = preg_split('/\s+/', $first) ?: [];
        $surname = end($parts);

        return strtolower($surname !== false ? $surname : $first);
    }
}
