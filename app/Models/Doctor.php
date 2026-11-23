<?php

namespace App\Models;

use App\Enums\Specialty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Doctor extends Model
{
    protected $fillable = [
        'user_id',
        'specialty',
        'license_number',
        'bio',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'specialty' => Specialty::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
{
    return $query->where('is_active', true);
}

public function scopeOfSpecialty(
    Builder $query,
    Specialty|string $specialty
): Builder {
    $value = $specialty instanceof Specialty
        ? $specialty->value
        : $specialty;

    return $query->where('specialty', $value);
}

public function scopeAtBranch(
    Builder $query,
    int $branchId
): Builder {
    return $query->whereHas('schedules', function (Builder $query) use ($branchId) {
        $query->where('branch_id', $branchId)
            ->where('is_active', true);
    });
}

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }
}
