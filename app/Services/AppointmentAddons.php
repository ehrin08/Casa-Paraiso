<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Addon;
use App\Models\PromotionSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AppointmentAddons
{
    private ?Collection $activeCatalog = null;

    private ?Collection $fullCatalog = null;
    /** @return Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> */
    public function selected(array $codes, bool $includeInactive = false): Collection
    {
        $catalog = $this->catalog($includeInactive)->keyBy('code');
        $selected = collect($codes)
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->unique()
            ->values();

        if ($selected->count() !== count($codes) || $selected->contains(fn (string $code) => ! $catalog->has($code))) {
            throw ValidationException::withMessages([
                'addon_codes' => __('Select valid add-ons without duplicates.'),
            ]);
        }

        return $selected->map(fn (string $code) => $catalog->get($code))->values();
    }

    /** @return Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> */
    public function catalog(bool $includeInactive = false): Collection
    {
        $cache = $includeInactive ? $this->fullCatalog : $this->activeCatalog;
        if ($cache !== null) {
            return $cache;
        }

        $catalog = Addon::query()->when(! $includeInactive, fn ($query) => $query->where('is_active', true))->orderBy('id')->get()
            ->map(fn (Addon $addon) => [
                'code' => $addon->code,
                'name' => $addon->name,
                'price' => (float) $addon->price,
                'duration_minutes' => (int) $addon->duration_minutes,
            ])
            ->values();

        if ($includeInactive) {
            $this->fullCatalog = $catalog;
        } else {
            $this->activeCatalog = $catalog;
        }

        return $catalog;
    }

    public function name(string $code): ?string
    {
        return Addon::query()->where('code', $code)->value('name');
    }

    /** @param Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> $addons */
    public function durationMinutes(Collection $addons): int
    {
        return (int) $addons->sum('duration_minutes');
    }

    /** @param Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> $addons */
    public function total(Collection $addons): float
    {
        return (float) $addons->sum('price');
    }

    /** @param Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> $addons */
    public function assertDoesNotDuplicateVoucher(Collection $addons, ?PromotionSuggestion $voucher): void
    {
        if ($voucher?->addon_code && $addons->contains('code', $voucher->addon_code)) {
            throw ValidationException::withMessages([
                'addon_codes' => __('A voucher add-on cannot also be selected as a paid add-on.'),
            ]);
        }
    }

    /** @param Collection<int, array{code: string, name: string, price: float, duration_minutes: int}> $addons */
    public function sync(Appointment $appointment, Collection $addons): void
    {
        $appointment->addons()->delete();
        $appointment->addons()->createMany($addons->map(fn (array $addon) => [
            'addon_code' => $addon['code'],
            'addon_name' => $addon['name'],
            'price' => $addon['price'],
            'duration_minutes' => $addon['duration_minutes'],
        ])->all());
    }
}
