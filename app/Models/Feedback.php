<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'working', 'not_working', 'images'])]
class Feedback extends Model
{
    /** Settings-key defaults for the admin-managed limits. */
    public const DEFAULT_IMAGE_LIMIT = 3;

    public const DEFAULT_DAILY_LIMIT = 2;

    /**
     * "Feedback" is uncountable, so Eloquent would guess the "feedback" table;
     * pin it to the migration's "feedbacks" table explicitly.
     */
    protected $table = 'feedbacks';

    protected static function booted(): void
    {
        // Remove the uploaded image files when a feedback row is deleted.
        static::deleting(function (Feedback $feedback) {
            $images = $feedback->images ?? [];

            if ($images !== []) {
                Storage::disk('public')->delete($images);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'images' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Max number of images a user may attach to one feedback (admin-configurable).
     */
    public static function imageLimit(): int
    {
        return max(0, (int) Setting::get('feedback_image_limit', (string) self::DEFAULT_IMAGE_LIMIT));
    }

    /**
     * Max feedback submissions a user may send per calendar day (admin-configurable).
     */
    public static function dailyLimit(): int
    {
        return max(1, (int) Setting::get('feedback_daily_limit', (string) self::DEFAULT_DAILY_LIMIT));
    }

    /**
     * Public URLs for the attached images.
     *
     * @return list<string>
     */
    public function imageUrls(): array
    {
        return array_map(
            fn (string $path): string => Storage::disk('public')->url($path),
            $this->images ?? [],
        );
    }
}
