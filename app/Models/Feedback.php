<?php

namespace App\Models;

use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    /** @use HasFactory<FeedbackFactory> */
    use HasFactory;

    protected $table = 'feedback';

    public const SENTIMENT_POSITIVE = 'positive';

    public const SENTIMENT_NEUTRAL = 'neutral';

    public const SENTIMENT_NEGATIVE = 'negative';

    public const SENTIMENT_LABELS = [
        self::SENTIMENT_POSITIVE,
        self::SENTIMENT_NEUTRAL,
        self::SENTIMENT_NEGATIVE,
    ];

    protected $fillable = [
        'customer_profile_id',
        'appointment_id',
        'service_id',
        'rating',
        'comment',
        'sentiment_label',
        'sentiment_score',
        'sentiment_analysis_version',
        'sentiment_evidence',
        'sentiment_source',
        'sentiment_confidence',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'sentiment_score' => 'decimal:2',
            'sentiment_evidence' => 'array',
            'sentiment_confidence' => 'decimal:4',
            'submitted_at' => 'datetime',
        ];
    }

    public function customerProfile()
    {
        return $this->belongsTo(CustomerProfile::class)->withTrashed();
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function topics()
    {
        return $this->hasMany(FeedbackTopic::class);
    }

    public function sentimentRuns()
    {
        return $this->hasMany(FeedbackSentimentRun::class);
    }

    public function annotations()
    {
        return $this->hasMany(FeedbackAnnotation::class);
    }
}
