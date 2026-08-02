<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;

class AttendanceQr
{
    public function current(): array
    {
        $now = CarbonImmutable::now('Asia/Manila');
        $bucket = (int) floor($now->timestamp / 60);
        $expiresAt = $now->startOfMinute()->addMinute();
        $payload = base64_encode(Crypt::encryptString('attendance:'.$bucket));

        return ['payload' => $payload, 'expires_at' => $expiresAt->toIso8601String(), 'bucket' => (string) $bucket];
    }

    public function validate(string $payload): string
    {
        try { $value = Crypt::decryptString(base64_decode($payload, true) ?: ''); } catch (\Throwable) { abort(422, 'This attendance QR code is invalid.'); }
        if (! preg_match('/^attendance:(\d+)$/', $value, $matches)) abort(422, 'This attendance QR code is invalid.');
        $bucket = (int) $matches[1]; $nowBucket = (int) floor(CarbonImmutable::now('Asia/Manila')->timestamp / 60);
        if ($bucket !== $nowBucket) abort(422, 'This attendance QR code has expired.');
        return (string) $bucket;
    }
}
