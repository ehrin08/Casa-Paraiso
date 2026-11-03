<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loads past services from a CSV file as completed visits, paid transactions and therapist
 * commissions. Rows are validated as a whole first; nothing is written while any row has an
 * error, and rows whose reference was imported before are skipped.
 */
class TransactionCsvImport
{
    public const COLUMNS = ['reference', 'date', 'time', 'service', 'amount', 'therapist', 'commission', 'customer', 'payment_method', 'notes'];

    public const REQUIRED_COLUMNS = ['reference', 'date', 'service', 'amount', 'therapist'];

    public const NOTE = 'Imported from service-history CSV';

    public function __construct(
        private readonly AppointmentNumberGenerator $appointmentNumbers,
        private readonly TransactionNumber $transactionNumbers,
    ) {}

    public static function templateCsv(): string
    {
        return implode(',', self::COLUMNS)."\r\n"
            ."2026-10-01-EXAMPLE-001,2026-10-01,,Gaia Touch,499,Therapist name,,,Cash,\r\n";
    }

    /**
     * Read and check every row without writing anything.
     *
     * @return array{file_errors: array<int, string>, errors: array<int, array<int, string>>, rows: array<int, array<string, mixed>>}
     */
    public function parse(string $path): array
    {
        $result = ['file_errors' => [], 'errors' => [], 'rows' => []];
        $contents = (string) file_get_contents($path);
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $result['file_errors'][] = __('Save the file as UTF-8 CSV and upload it again.');

            return $result;
        }

        $records = $this->records($contents);
        $header = array_map(fn ($name) => Str::snake(trim((string) $name)), array_shift($records) ?? []);
        $unknown = array_values(array_diff(array_filter($header), self::COLUMNS));
        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $header));

        if ($unknown !== []) {
            $result['file_errors'][] = __('Unknown columns: :columns.', ['columns' => implode(', ', $unknown)]);
        }

        if ($missing !== []) {
            $result['file_errors'][] = __('Missing columns: :columns.', ['columns' => implode(', ', $missing)]);
        }

        if (count($header) !== count(array_unique($header))) {
            $result['file_errors'][] = __('Each column may appear only once.');
        }

        $records = array_filter($records, fn (array $record) => trim(implode('', $record)) !== '');
        $maxRows = (int) config('casa.imports.max_rows', 2000);

        if ($records === []) {
            $result['file_errors'][] = __('The file has no rows to import.');
        } elseif (count($records) > $maxRows) {
            $result['file_errors'][] = __('The file has more than :max rows. Split it into smaller files.', ['max' => $maxRows]);
        }

        if ($result['file_errors'] !== []) {
            return $result;
        }

        $resolver = $this->resolver();
        $seen = [];

        foreach ($records as $index => $record) {
            $line = $index + 2; // The header is line 1.
            $values = [];

            foreach ($header as $position => $column) {
                if ($column !== '') {
                    $values[$column] = trim((string) ($record[$position] ?? ''));
                }
            }

            [$row, $messages] = $this->row($values, $resolver);
            $reference = strtolower($values['reference'] ?? '');

            if ($reference !== '' && isset($seen[$reference])) {
                $messages[] = __('Reference :reference is repeated on line :line.', [
                    'reference' => $values['reference'],
                    'line' => $seen[$reference],
                ]);
            } elseif ($reference !== '') {
                $seen[$reference] = $line;
            }

            if ($messages !== []) {
                $result['errors'][$line] = $messages;

                continue;
            }

            $result['rows'][] = ['line' => $line, ...$row];
        }

        $existing = Transaction::query()
            ->whereIn('import_reference', array_column($result['rows'], 'reference'))
            ->pluck('import_reference')
            ->map(fn ($reference) => strtolower($reference))
            ->flip();

        foreach ($result['rows'] as &$row) {
            $row['already_imported'] = $existing->has(strtolower($row['reference']));
        }
        unset($row);

        $this->schedule($result['rows']);

        return $result;
    }

    /**
     * Per-therapist totals for the rows that will be created.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, array{therapist: string, visits: int, amount: float, commission: float}>
     */
    public function summary(array $rows): Collection
    {
        return collect($rows)
            ->reject(fn (array $row) => $row['already_imported'])
            ->groupBy('staff_profile_id')
            ->map(fn (Collection $group) => [
                'therapist' => $group->first()['therapist_name'],
                'visits' => $group->count(),
                'amount' => round($group->sum('amount'), 2),
                'commission' => round($group->sum('commission'), 2),
            ])
            ->sortBy('therapist')
            ->values();
    }

    /**
     * Write the checked rows in one database transaction.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, skipped: int}
     */
    public function import(array $rows, User $recorder, ?CarbonInterface $commissionsPaidAt = null): array
    {
        return DB::transaction(function () use ($rows, $recorder, $commissionsPaidAt): array {
            $existing = Transaction::query()
                ->whereIn('import_reference', array_column($rows, 'reference'))
                ->lockForUpdate()
                ->pluck('import_reference')
                ->map(fn ($reference) => strtolower($reference))
                ->flip();
            $customers = [];
            $appointmentNumber = null;
            $transactionNumber = null;
            $created = 0;

            foreach ($rows as $row) {
                if ($existing->has(strtolower($row['reference']))) {
                    continue;
                }

                $customer = $customers[strtolower($row['customer'] ?? '')] ??= $this->customer($row);
                $start = CarbonImmutable::parse($row['starts_at']);
                $end = CarbonImmutable::parse($row['ends_at']);
                $appointmentNumber = $this->nextNumber('APT-', $appointmentNumber, fn () => $this->appointmentNumbers->next(),
                    fn (string $number) => Appointment::query()->where('appointment_number', $number)->exists());
                $transactionNumber = $this->nextNumber('TRX-', $transactionNumber, fn () => $this->transactionNumbers->next(),
                    fn (string $number) => Transaction::query()->where('transaction_number', $number)->exists());

                $appointment = Appointment::query()->create([
                    'appointment_number' => $appointmentNumber,
                    'customer_profile_id' => $customer->id,
                    'service_id' => $row['service_id'],
                    'staff_profile_id' => $row['staff_profile_id'],
                    'requested_start_at' => $start,
                    'scheduled_start_at' => $start,
                    'scheduled_end_at' => $end,
                    'status' => Appointment::STATUS_COMPLETED,
                    'internal_notes' => self::NOTE.' ('.$row['reference'].')',
                    'confirmed_at' => $start,
                    'completed_at' => $end,
                    'created_by' => $recorder->id,
                    'updated_by' => $recorder->id,
                ]);
                AppointmentStatusLog::query()->create([
                    'appointment_id' => $appointment->id,
                    'from_status' => null,
                    'to_status' => Appointment::STATUS_COMPLETED,
                    'changed_by' => $recorder->id,
                    'reason' => self::NOTE,
                ]);
                $transaction = Transaction::query()->create([
                    'transaction_number' => $transactionNumber,
                    'customer_profile_id' => $customer->id,
                    'appointment_id' => $appointment->id,
                    'service_id' => $row['service_id'],
                    'amount' => $row['amount'],
                    'gross_amount' => $row['amount'],
                    'discount_amount' => 0,
                    'amount_due' => $row['amount'],
                    'payment_status' => Transaction::PAYMENT_PAID,
                    'payment_method' => $row['payment_method'],
                    'paid_at' => $end,
                    'recorded_by' => $recorder->id,
                    'notes' => $row['notes'] !== '' ? $row['notes'] : self::NOTE,
                    'import_reference' => $row['reference'],
                ]);
                TherapistCommission::query()->create([
                    'staff_profile_id' => $row['staff_profile_id'],
                    'appointment_id' => $appointment->id,
                    'transaction_id' => $transaction->id,
                    'primary_transaction_id' => $transaction->id,
                    'commission_type' => TherapistCommission::TYPE_EARNING,
                    'status' => $commissionsPaidAt ? TherapistCommission::STATUS_PAID : TherapistCommission::STATUS_PENDING,
                    'basis_amount' => $row['amount'],
                    'commission_rate' => $row['commission_rate'],
                    'commission_amount' => $row['commission'],
                    'earned_at' => $end,
                    'paid_at' => $commissionsPaidAt,
                    'paid_by' => $commissionsPaidAt ? $recorder->id : null,
                    'notes' => $commissionsPaidAt ? self::NOTE : null,
                ]);
                $created++;
            }

            return ['created' => $created, 'skipped' => count($rows) - $created];
        }, 3);
    }

    /** @return array<int, array<int, string|null>> */
    private function records(string $contents): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);
        $records = [];

        while (($record = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $records[] = $record;
        }

        fclose($handle);

        return $records;
    }

    /**
     * @param  array<string, string>  $values
     * @return array{0: array<string, mixed>|null, 1: array<int, string>}
     */
    private function row(array $values, array $resolver): array
    {
        $messages = [];
        $value = fn (string $column) => $values[$column] ?? '';
        $reference = $value('reference');
        $timezone = (string) config('casa.business_hours.timezone', config('app.timezone'));

        if ($reference === '' || strlen($reference) > 64 || ! preg_match('/^[A-Za-z0-9._-]+$/', $reference)) {
            $messages[] = __('Reference must be 1–64 letters, numbers, dots, dashes or underscores.');
        }

        $date = $this->strictDate($value('date'), 'Y-m-d', $timezone);
        $time = $value('time') === '' ? null : $this->strictDate($value('time'), 'H:i', $timezone);

        if (! $date) {
            $messages[] = __('Date must be written as YYYY-MM-DD.');
        } elseif ($date->startOfDay()->gt(now($timezone)->startOfDay())) {
            $messages[] = __('Date cannot be in the future.');
        }

        if ($value('time') !== '' && ! $time) {
            $messages[] = __('Time must be written as HH:MM (24-hour), or left blank.');
        }

        $serviceKey = Str::slug($value('service'));
        $service = $resolver['services'][$serviceKey] ?? null;

        if (! $service) {
            $messages[] = $value('service') === ''
                ? __('Service is required.')
                : __('Service ":service" is not in the catalog. Add it on the Services page first.', ['service' => $value('service')]);
        }

        $amount = $this->money($value('amount'));

        if ($amount === null || $amount <= 0) {
            $messages[] = __('Amount must be a number above zero.');
        }

        [$therapist, $therapistError] = $this->therapist($value('therapist'), $resolver['therapists']);

        if ($therapistError) {
            $messages[] = $therapistError;
        }

        $commission = null;

        if ($value('commission') !== '') {
            $commission = $this->money($value('commission'));

            if ($commission === null || $commission < 0 || ($amount !== null && $commission > $amount)) {
                $commission = null;
                $messages[] = __('Commission must be a number from zero up to the amount, or left blank for the default rate.');
            }
        }

        $method = collect(Transaction::PAYMENT_METHODS)
            ->first(fn (string $method) => strcasecmp($method, $value('payment_method') ?: Transaction::METHOD_CASH) === 0);

        if (! $method) {
            $messages[] = __('Payment method must be one of: :methods.', ['methods' => implode(', ', Transaction::PAYMENT_METHODS)]);
        }

        if (mb_strlen($value('customer')) > 255) {
            $messages[] = __('Customer name may be at most 255 characters.');
        }

        if (mb_strlen($value('notes')) > 500) {
            $messages[] = __('Notes may be at most 500 characters.');
        }

        if ($messages !== []) {
            return [null, $messages];
        }

        $rate = (float) config('casa.commissions.therapist_rate', 0.22);
        $commission ??= round($amount * $rate, 2);

        return [[
            'reference' => $reference,
            'date' => $date->toDateString(),
            'time' => $time?->format('H:i'),
            'service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => (int) $service->duration_minutes,
            'amount' => $amount,
            'staff_profile_id' => $therapist->id,
            'therapist_name' => $therapist->user?->name ?? __('Therapist'),
            'commission' => $commission,
            'commission_rate' => $amount > 0 ? round($commission / $amount, 4) : $rate,
            'customer' => $value('customer'),
            'payment_method' => $method,
            'notes' => $value('notes'),
        ], []];
    }

    /** @return array{services: array<string, Service>, therapists: Collection<int, StaffProfile>} */
    private function resolver(): array
    {
        $services = [];

        foreach (Service::query()->orderBy('id')->get() as $service) {
            $services[Str::slug($service->name)] ??= $service;
            $services[$service->slug] ??= $service;
        }

        foreach (Service::query()->orderBy('id')->get() as $service) {
            // "Back Massage" also finds "30-Minute Back Massage" when no exact name matches.
            $short = Str::slug(preg_replace('/^\d+-minute\s+/i', '', $service->name));
            $services[$short] ??= $service;
        }

        return [
            'services' => $services,
            'therapists' => StaffProfile::query()->with('user')->whereHas('user')->get(),
        ];
    }

    /**
     * Exact name, then a unique first name, then the email name before "." or "@".
     *
     * @param  Collection<int, StaffProfile>  $therapists
     * @return array{0: StaffProfile|null, 1: string|null}
     */
    private function therapist(string $name, Collection $therapists): array
    {
        if ($name === '') {
            return [null, __('Therapist is required.')];
        }

        $needle = mb_strtolower($name);
        $matchers = [
            fn (StaffProfile $staff) => mb_strtolower(trim($staff->user->name)) === $needle,
            fn (StaffProfile $staff) => mb_strtolower(Str::before(trim($staff->user->name), ' ')) === $needle,
            fn (StaffProfile $staff) => mb_strtolower(Str::before(Str::before((string) $staff->user->email, '@'), '.')) === $needle,
        ];

        foreach ($matchers as $matcher) {
            $matches = $therapists->filter($matcher);

            if ($matches->count() === 1) {
                return [$matches->first(), null];
            }

            if ($matches->count() > 1) {
                return [null, __('Therapist ":name" matches more than one account. Use the full name.', ['name' => $name])];
            }
        }

        return [null, __('Therapist ":name" was not found.', ['name' => $name])];
    }

    /**
     * Rows without a time follow each other from opening time, per therapist and day.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function schedule(array &$rows): void
    {
        $timezone = (string) config('casa.business_hours.timezone', config('app.timezone'));
        $opensAt = ApplicationSetting::tableAvailable() ? ApplicationSetting::current()->opensAt() : '13:00';
        $cursors = [];

        foreach ($rows as &$row) {
            $key = $row['staff_profile_id'].'|'.$row['date'];
            $start = $row['time'] !== null
                ? CarbonImmutable::parse($row['date'].' '.$row['time'], $timezone)
                : ($cursors[$key] ?? CarbonImmutable::parse($row['date'].' '.$opensAt, $timezone));
            $end = $start->addMinutes($row['duration_minutes']);
            $row['starts_at'] = $start->toIso8601String();
            $row['ends_at'] = $end->toIso8601String();

            if (! isset($cursors[$key]) || $end->gt($cursors[$key])) {
                $cursors[$key] = $end;
            }
        }
        unset($row);
    }

    private function customer(array $row): CustomerProfile
    {
        $name = trim((string) ($row['customer'] ?? ''));

        if ($name === '') {
            return CustomerProfile::importWalkIn();
        }

        $matches = CustomerProfile::query()
            ->leftJoin('users', 'users.id', '=', 'customer_profiles.user_id')
            ->whereRaw('LOWER('.CustomerProfile::displayNameSql().') = ?', [mb_strtolower($name)])
            ->select('customer_profiles.*')
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : CustomerProfile::createGuest($name);
    }

    private function nextNumber(string $prefix, ?string $previous, callable $first, callable $taken): string
    {
        if ($previous === null) {
            return $first();
        }

        $sequence = (int) Str::after($previous, $prefix);

        do {
            $number = $prefix.str_pad((string) ++$sequence, 4, '0', STR_PAD_LEFT);
        } while ($taken($number));

        return $number;
    }

    private function strictDate(string $value, string $format, string $timezone): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!'.$format, $value, $timezone);
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->format($format) === $value ? $date : null;
    }

    private function money(string $value): ?float
    {
        $value = str_replace([',', '₱', ' '], '', $value);

        return preg_match('/^\d+(\.\d{1,2})?$/', $value) ? (float) $value : null;
    }
}
