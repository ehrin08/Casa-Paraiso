<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TransactionNumber
{
    public const MAX_SAVE_ATTEMPTS = 3;

    public function next(): string
    {
        $prefix = 'TRX-';
        $sequence = 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Transaction::query()->where('transaction_number', $number)->exists());

        return $number;
    }

    /**
     * Persist a new transaction while retrying only transaction-number collisions.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Transaction
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_SAVE_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(fn (): Transaction => Transaction::query()->create([
                    ...$attributes,
                    'transaction_number' => $this->next(),
                ]));
            } catch (QueryException $exception) {
                if (! $this->isTransactionNumberCollision($exception)) {
                    throw $exception;
                }

                $lastException = $exception;
            }
        }

        throw $lastException;
    }

    private function isTransactionNumberCollision(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains($message, 'transaction_number');
    }
}
