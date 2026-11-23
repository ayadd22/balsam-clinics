<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Scopes\ReceptionistBranchScope;
use App\Models\Scopes\DoctorAppointmentScope;
use Illuminate\Database\Eloquent\Builder;

class Appointment extends Model
{


    protected static function booted(): void 
    {
     static::addGlobalScope(new ReceptionistBranchScope);
     static::addGlobalScope(new DoctorAppointmentScope);
    }


    protected $fillable = [
        'patient_id',
        'doctor_id',
        'branch_id',
        'appointment_date',
        'start_time',
        'end_time',
        'status',
        'checked_in_at',
        'reference_number',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'checked_in_at' => 'datetime',
            'status' => AppointmentStatus::class,
        ];
    }


    public function scopeForDoctor(
    Builder $query,
    int $doctorId
): Builder {
    return $query->where('doctor_id', $doctorId);
}

public function scopeForBranch(
    Builder $query,
    int $branchId
): Builder {
    return $query->where('branch_id', $branchId);
}

public function scopeOnDate(
    Builder $query,
    string $date
): Builder {
    return $query->whereDate('appointment_date', $date);
}

public function scopeWithStatus(
    Builder $query,
    AppointmentStatus|string $status
): Builder {
    $value = $status instanceof AppointmentStatus
        ? $status->value
        : $status;

    return $query->where('status', $value);
}

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

   
}
