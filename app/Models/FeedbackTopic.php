<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackTopic extends Model
{
    protected $fillable = ['feedback_id', 'topic_key', 'polarity', 'matched_terms'];

    protected function casts(): array
    {
        return ['matched_terms' => 'array'];
    }

    public function feedback()
    {
        return $this->belongsTo(Feedback::class);
    }
}
