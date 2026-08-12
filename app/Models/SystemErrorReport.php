<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SystemErrorReport extends Model
{
    public const CATEGORY_CLIENT_ERROR = 'client_error';

    public const CATEGORY_SERVER_ERROR = 'server_error';

    public const CATEGORY_FEATURE_UNAVAILABLE = 'feature_unavailable';

    protected $fillable = [
        'reference',
        'fingerprint',
        'http_status',
        'category',
        'exception_class',
        'message',
        'source_file',
        'source_line',
        'stack_frames',
        'occurrence_count',
        'last_request_id',
        'last_route_name',
        'last_request_method',
        'last_request_path',
        'last_user_id',
        'first_seen_at',
        'last_seen_at',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'source_line' => 'integer',
            'occurrence_count' => 'integer',
            'message' => 'encrypted',
            'stack_frames' => 'encrypted:array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(SystemErrorOccurrence::class);
    }

    public function latestOccurrence(): HasOne
    {
        return $this->hasOne(SystemErrorOccurrence::class)->latestOfMany('occurred_at');
    }

    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function isUnexpectedServerError(): bool
    {
        return $this->category === self::CATEGORY_SERVER_ERROR && $this->http_status >= 500;
    }
}
