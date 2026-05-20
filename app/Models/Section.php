<?php

namespace App\Models;

use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use HasFactory;

    protected $fillable = [
        'report_id',
        'placement',
        'order',
        'title',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Whether this section is a custom front-matter page (before the contents).
     */
    public function isFrontPage(): bool
    {
        return $this->placement === 'front';
    }
}
