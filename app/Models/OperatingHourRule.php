<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A dated change to the default hours. A null ends_on means the rule lasts until further notice. */
class OperatingHourRule extends Model
{
    public const TYPE_CLOSED = 'closed';

    public const TYPE_SPECIAL_HOURS = 'special_hours';

    public const TYPE_PARTIAL_CLOSURE = 'partial_closure';

    public const TYPES = [self::TYPE_CLOSED, self::TYPE_SPECIAL_HOURS, self::TYPE_PARTIAL_CLOSURE];

    protected $fillable = [
        'type',
        'starts_on',
        'ends_on',
        'start_time',
        'end_time',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Rules in effect on any day from $from to $to. */
    public function scopeCovering(Builder $query, CarbonInterface|string $from, CarbonInterface|string $to): Builder
    {
        $from = $from instanceof CarbonInterface ? $from->toDateString() : $from;
        $to = $to instanceof CarbonInterface ? $to->toDateString() : $to;

        return $query->whereDate('starts_on', '<=', $to)
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from));
    }

    public function covers(string $date): bool
    {
        return $this->starts_on->toDateString() <= $date
            && ($this->ends_on === null || $this->ends_on->toDateString() >= $date);
    }

    public function isTimed(): bool
    {
        return $this->type !== self::TYPE_CLOSED;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CLOSED => __('Closed'),
            self::TYPE_SPECIAL_HOURS => __('Special hours'),
            default => __('Closed part of day'),
        };
    }
}
