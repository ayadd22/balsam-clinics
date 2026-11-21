<?php

namespace App\Models\Scopes;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class DoctorAppointmentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->role !== UserRole::DOCTOR) {
            return;
        }

        $doctorId = $user->doctor?->id;

        $builder->where(
            $model->getTable() . '.doctor_id',
            $doctorId ?? 0
        );
    }
}
