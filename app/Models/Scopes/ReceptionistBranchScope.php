<?php

namespace App\Models\Scopes;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ReceptionistBranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->role !== UserRole::RECEPTIONIST) {
            return;
        }

        $branchIds = $user->branches()->pluck('branches.id');

        $builder->whereIn(
            $model->getTable() . '.branch_id',
            $branchIds
        );
    }
}

