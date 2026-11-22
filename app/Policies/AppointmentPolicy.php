<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Enums\UserRole;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::ADMIN,
            UserRole::RECEPTIONIST,
            UserRole::DOCTOR,
        ]);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        if ($user->role === UserRole::PATIENT) {
            return $user->patient?->id === $appointment->patient_id;
        }

        if ($user->role === UserRole::DOCTOR) {
            return $user->doctor?->id === $appointment->doctor_id;
        }

        if ($user->role === UserRole::RECEPTIONIST) {
            return $user->branches()
                ->where('branches.id', $appointment->branch_id)
                ->exists();
        }

        return false;
    }

    
    public function create(User $user): bool
    {
        return in_array($user->role, [
          UserRole::ADMIN,
          UserRole::RECEPTIONIST,
          UserRole::PATIENT,
       ]);
     }
    
    public function update(User $user, Appointment $appointment): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        if ($user->role === UserRole::RECEPTIONIST) {
            return $user->branches()
                ->where('branches.id', $appointment->branch_id)
                ->exists();
        }

        return false;
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->role === UserRole::ADMIN;
    }
}
