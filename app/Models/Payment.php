<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'user_id',
        'report_id',
        'gateway',
        'mode',
        'amount',
        'transaction_uuid',
        'pidx',
        'ref_id',
        'status',
        'consumed_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'consumed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * A completed payment that has not yet been spent on a download.
     */
    public function isRedeemable(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->consumed_at === null;
    }
}
