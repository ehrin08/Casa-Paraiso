<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackSentimentRun extends Model
{
    protected $fillable = ['feedback_id', 'source', 'classifier_version', 'label', 'score', 'confidence', 'evidence', 'is_authoritative'];

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'confidence' => 'decimal:4', 'evidence' => 'array', 'is_authoritative' => 'boolean'];
    }

    public function feedback()
    {
        return $this->belongsTo(Feedback::class);
    }
}
