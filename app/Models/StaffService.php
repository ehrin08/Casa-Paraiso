<?php

namespace App\Models;

use Database\Factories\StaffServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffService extends Model
{
    /** @use HasFactory<StaffServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'staff_profile_id',
        'service_id',
    ];

    public function staffProfile()
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
