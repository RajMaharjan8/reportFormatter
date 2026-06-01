<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverTemplate extends Model
{
    /** The most custom covers a single user may save. */
    public const MAX_PER_USER = 3;

    protected $fillable = [
        'user_id',
        'name',
        'html',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
