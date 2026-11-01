<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Addon extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'duration_minutes', 'price', 'is_active'];

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer', 'price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /** Appointment add-on lines booked with this add-on's code. */
    public function bookings(): HasMany
    {
        return $this->hasMany(AppointmentAddon::class, 'addon_code', 'code');
    }

    public static function tableAvailable(): bool
    {
        return Schema::hasTable((new self)->getTable());
    }
}
