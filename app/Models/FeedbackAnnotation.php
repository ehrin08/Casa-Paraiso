<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackAnnotation extends Model
{
    protected $fillable = ['feedback_id', 'reviewer_id', 'label', 'language', 'topics', 'status', 'notes', 'adjudicated_at'];

    protected function casts(): array
    {
        return ['topics' => 'array', 'adjudicated_at' => 'datetime'];
    }

    public function feedback()
    {
        return $this->belongsTo(Feedback::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
