<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecord;
use App\Models\AuditLog;
use App\Models\AppointmentSequence;
use App\Models\AppointmentReminder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ModelRelationshipsTest extends TestCase
{
    public function test_all_model_relationships_are_defined(): void
    {
        // Branch
        $branch = new Branch();

        $this->assertInstanceOf(HasMany::class, $branch->schedules());
        $this->assertInstanceOf(HasMany::class, $branch->appointments());
        $this->assertInstanceOf(BelongsToMany::class, $branch->users());
        $this->assertInstanceOf(HasMany::class, $branch->appointmentSequences());

        // Doctor
        $doctor = new Doctor();

        $this->assertInstanceOf(BelongsTo::class, $doctor->user());
        $this->assertInstanceOf(HasMany::class, $doctor->schedules());
        $this->assertInstanceOf(HasMany::class, $doctor->appointments());
        $this->assertInstanceOf(HasMany::class, $doctor->medicalRecords());

        // DoctorSchedule
        $schedule = new DoctorSchedule();

        $this->assertInstanceOf(BelongsTo::class, $schedule->doctor());
        $this->assertInstanceOf(BelongsTo::class, $schedule->branch());

        // Patient
        $patient = new Patient();

        $this->assertInstanceOf(BelongsTo::class, $patient->user());
        $this->assertInstanceOf(HasMany::class, $patient->appointments());

        // Appointment
        $appointment = new Appointment();

        $this->assertInstanceOf(BelongsTo::class, $appointment->patient());
        $this->assertInstanceOf(BelongsTo::class, $appointment->doctor());
        $this->assertInstanceOf(BelongsTo::class, $appointment->branch());
        $this->assertInstanceOf(HasOne::class, $appointment->medicalRecord());
        $this->assertInstanceOf(HasMany::class, $appointment->reminders());
        $this->assertInstanceOf(MorphMany::class, $appointment->auditLogs());

        // MedicalRecord
        $record = new MedicalRecord();

        $this->assertInstanceOf(BelongsTo::class, $record->appointment());
        $this->assertInstanceOf(BelongsTo::class, $record->doctor());
        $this->assertInstanceOf(MorphMany::class, $record->auditLogs());

        // AuditLog
        $auditLog = new AuditLog();

        $this->assertInstanceOf(MorphTo::class, $auditLog->auditable());
        $this->assertInstanceOf(BelongsTo::class, $auditLog->user());

        // AppointmentSequence
        $sequence = new AppointmentSequence();

        $this->assertInstanceOf(BelongsTo::class, $sequence->branch());

        // AppointmentReminder
        $reminder = new AppointmentReminder();

        $this->assertInstanceOf(BelongsTo::class, $reminder->appointment());

        // User
        $user = new User();

        $this->assertInstanceOf(HasOne::class, $user->doctor());
        $this->assertInstanceOf(HasOne::class, $user->patient());
        $this->assertInstanceOf(BelongsToMany::class, $user->branches());
        $this->assertInstanceOf(HasMany::class, $user->auditLogs());
    }
}
