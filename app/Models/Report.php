<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $fillable = [
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
}
