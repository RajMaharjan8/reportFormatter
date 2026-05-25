<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Report $report) {
            $report->deleteLocalImages();
        });
    }

    protected $fillable = [
        'user_id',
        'cover_format',
        'tu_college_name',
        'tu_roll_number',
        'tu_submitted_to_position',
        'module_code',
        'module_title',
        'title',
        'abstract',
        'section_label',
        'page_number_align',
        'reference_format',
        'margin_top',
        'margin_right',
        'margin_bottom',
        'margin_left',
        'line_spacing',
        'assessment_type',
        'semester',
        'academic_year',
        'student_name',
        'london_id',
        'college_id',
        'assignment_due_date',
        'submission_date',
        'submitted_to',
        'arabic_start_page',
    ];

    protected function casts(): array
    {
        return [
            'assignment_due_date' => 'date',
            'submission_date' => 'date',
            'arabic_start_page' => 'integer',
            'margin_top' => 'float',
            'margin_right' => 'float',
            'margin_bottom' => 'float',
            'margin_left' => 'float',
            'line_spacing' => 'float',
        ];
    }

    /**
     * Page margins as a tuple of [top, right, bottom, left] inches, clamped to
     * the printable range so a malformed or extreme value can't push content
     * off the page.
     *
     * @return array{top: float, right: float, bottom: float, left: float}
     */
    public function pageMargins(): array
    {
        return [
            'top' => $this->clampMargin($this->margin_top, 1.00),
            'right' => $this->clampMargin($this->margin_right, 1.00),
            'bottom' => $this->clampMargin($this->margin_bottom, 1.00),
            'left' => $this->clampMargin($this->margin_left, 1.50),
        ];
    }

    public function lineSpacing(): float
    {
        $value = (float) ($this->line_spacing ?? 1.15);

        return max(1.0, min(3.0, $value));
    }

    protected function clampMargin(mixed $value, float $default): float
    {
        $value = (float) ($value ?? $default);

        return max(0.25, min(3.0, $value));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    public function references(): HasMany
    {
        return $this->hasMany(Reference::class)->orderBy('id');
    }

    /**
     * The citation format slug, defaulting to London Met when unset.
     */
    public function citationFormat(): string
    {
        $format = (string) ($this->reference_format ?? '');

        return in_array($format, ['ieee', 'apa', 'london_met'], true) ? $format : 'london_met';
    }

    /**
     * Delete any local image files referenced from this report's section
     * HTML before the DB cascade removes the section rows. Inline data-URL
     * images live in the row itself, so this only matters for legacy or
     * future <img src="/storage/..."> uploads.
     */
    protected function deleteLocalImages(): void
    {
        $disk = Storage::disk('public');

        foreach ($this->sections()->get(['id', 'content']) as $section) {
            foreach (self::extractPublicStoragePaths((string) $section->content) as $path) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    protected static function extractPublicStoragePaths(string $html): array
    {
        if ($html === '' || ! preg_match_all('#<img[^>]+src=["\']\s*/?storage/([^"\']+)["\']#i', $html, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }
}
