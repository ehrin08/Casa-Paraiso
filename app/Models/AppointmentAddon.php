<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentAddon extends Model
{
    protected $fillable = [
        'appointment_id',
        'addon_code',
        'addon_name',
        'price',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
