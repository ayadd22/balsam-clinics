<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'birth_date',
        'gender',
        'is_booking_blocked',
        'blocked_until',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'blocked_until' => 'datetime',
            'is_booking_blocked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
