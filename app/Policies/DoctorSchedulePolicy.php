<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DoctorSchedule;
use App\Enums\UserRole;

class DoctorSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::ADMIN,
            UserRole::RECEPTIONIST,
            UserRole::DOCTOR,
        ]);
    }

    public function view(User $user, DoctorSchedule $schedule): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        if ($user->role === UserRole::DOCTOR) {
            return $user->doctor?->id === $schedule->doctor_id;
        }

        if ($user->role === UserRole::RECEPTIONIST) {
            return $user->branches()
                ->where('branches.id', $schedule->branch_id)
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::ADMIN;
    }

    public function update(User $user, DoctorSchedule $schedule): bool
    {
        return $user->role === UserRole::ADMIN;
    }

    public function delete(User $user, DoctorSchedule $schedule): bool
    {
        return $user->role === UserRole::ADMIN;
    }
}
