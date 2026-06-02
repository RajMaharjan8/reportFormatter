<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'working', 'not_working'])]
class Feedback extends Model
{
    /**
     * "Feedback" is uncountable, so Eloquent would guess the "feedback" table;
     * pin it to the migration's "feedbacks" table explicitly.
     */
    protected $table = 'feedbacks';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
