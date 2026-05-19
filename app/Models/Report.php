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
        'module_code',
        'module_title',
        'title',
        'abstract',
        'section_label',
        'page_number_align',
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
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }
}
