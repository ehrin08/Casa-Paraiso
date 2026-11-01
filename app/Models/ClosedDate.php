<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosedDate extends Model
{
    protected $fillable = [
        'closed_on',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'closed_on' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
