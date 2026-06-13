<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function update(User $user, Appointment $appointment): bool
    {
        return $user->getKey() === $appointment->created_by
            || $user->roles()->where('slug', 'admin')->exists();
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }
}
