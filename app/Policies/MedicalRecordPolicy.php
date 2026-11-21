<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MedicalRecord;
use App\Enums\UserRole;

class MedicalRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::ADMIN,
            UserRole::DOCTOR,
            UserRole::PATIENT,
        ]);
    }

    public function view(User $user, MedicalRecord $medicalRecord): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        if ($user->role === UserRole::DOCTOR) {
            return $user->doctor?->id === $medicalRecord->doctor_id;
        }

        if ($user->role === UserRole::PATIENT) {
            return $user->patient?->id ===
                $medicalRecord->appointment?->patient_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::ADMIN,
            UserRole::DOCTOR,
        ]);
    }

    public function update(User $user, MedicalRecord $medicalRecord): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        if ($user->role === UserRole::DOCTOR) {
            return $user->doctor?->id === $medicalRecord->doctor_id;
        }

        return false;
    }

    public function delete(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->role === UserRole::ADMIN;
    }
}
