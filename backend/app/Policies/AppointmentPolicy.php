<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function update(User $user, Appointment $appointment): bool
    {
        // Cualquier usuario con gestion de citas puede actualizar el estado y
        // los datos de lo agendado (antes solo el creador o el admin, lo que
        // provocaba un error 403 al cambiar el estado de citas ajenas).
        return $user->hasPermission('gestionar_citas')
            || $user->getKey() === $appointment->created_by
            || $user->roles()->where('slug', 'admin')->exists();
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }
}
