<?php

namespace App\Http\Requests;

class StaffAppointmentCompletionRequest extends AdminAppointmentCompletionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }
}
