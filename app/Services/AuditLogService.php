<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function created(Model $model): void
    {
        $newValues = Arr::except(
            $model->getAttributes(),
            ['id', 'created_at', 'updated_at']
        );

        $this->store($model, 'created', [], $newValues);
    }

    public function updated(Model $model): void
    {
        $changes = Arr::except(
            $model->getChanges(),
            ['updated_at']
        );

        if (empty($changes)) {
            return;
        }

        $oldValues = [];

        foreach (array_keys($changes) as $key) {
            $oldValues[$key] = $model->getRawOriginal($key);
        }

        $this->store($model, 'updated', $oldValues, $changes);
    }

    public function deleted(Model $model): void
    {
        $oldValues = Arr::except(
            $model->getAttributes(),
            ['created_at', 'updated_at']
        );

        $this->store($model, 'deleted', $oldValues, []);
    }

    private function store(
        Model $model,
        string $event,
        array $oldValues,
        array $newValues
    ): void {
        $model->auditLogs()->create([
            'user_id' => Auth::id(),
            'event' => $event,
            'old_values' => empty($oldValues) ? null : $oldValues,
            'new_values' => empty($newValues) ? null : $newValues,
        ]);
    }
}
