<?php

namespace App\Support;

/**
 * Strips contact details and booking numbers from feedback text before it is
 * exported or sent to an external sentiment model. Names are not detected.
 */
class SentimentRedactor
{
    public static function redact(string $comment): string
    {
        $comment = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u', '[email]', $comment) ?? $comment;
        $comment = preg_replace('/https?:\/\/\S+/iu', '[url]', $comment) ?? $comment;
        $comment = preg_replace('/\b(?:APT|TRX)[-\s]?\w+\b/iu', '[booking]', $comment) ?? $comment;
        $comment = preg_replace('/(?<!\w)(?:\+?63|0)9\d{9}(?!\w)/', '[phone]', $comment) ?? $comment;

        return trim($comment);
    }
}
