<?php

namespace Tests\Unit;

use App\Support\SentimentRedactor;
use PHPUnit\Framework\TestCase;

class SentimentRedactorTest extends TestCase
{
    public function test_it_removes_contact_details_and_booking_numbers(): void
    {
        $this->assertSame(
            'Call [phone] or [email] about [booking], see [url]',
            SentimentRedactor::redact(' Call +639171234567 or ana.cruz@example.com about APT-0042, see https://example.com/x '),
        );
        $this->assertSame('Ang ganda ng serbisyo.', SentimentRedactor::redact('Ang ganda ng serbisyo.'));
    }
}
