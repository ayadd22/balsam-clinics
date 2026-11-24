<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Enums\Specialty;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ClinicDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Branches
        $branches = collect([
            [
                'name' => 'Damascus Main Clinic',
                'code' => 'DAM-001',
                'city' => 'Damascus',
                'address' => 'Abu Rummaneh',
                'phone' => '0111111111',
                'is_active' => true,
            ],
            [
                'name' => 'Aleppo Clinic',
                'code' => 'ALP-001',
                'city' => 'Aleppo',
                'address' => 'Al-Aziziyah',
                'phone' => '0211111111',
                'is_active' => true,
            ],
            [
                'name' => 'Latakia Clinic',
                'code' => 'LAT-001',
                'city' => 'Latakia',
                'address' => 'Al-Ziraa',
                'phone' => '0411111111',
                'is_active' => true,
            ],
        ])->map(function (array $data) {
            return Branch::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        })->values();

        // Demo users
        $demoUsers = collect();

        // Admin
        $admin = $this->createUser(
            'System Admin',
            'admin@balsam.test',
            UserRole::ADMIN
        );

        $demoUsers->push($admin);

        $admin->branches()->syncWithoutDetaching(
            $branches->pluck('id')->all()
        );

        // Receptionists
        foreach ($branches as $index => $branch) {
            $receptionist = $this->createUser(
                'Receptionist ' . ($index + 1),
                'receptionist' . ($index + 1) . '@balsam.test',
                UserRole::RECEPTIONIST
            );

            $demoUsers->push($receptionist);

            $receptionist->branches()->syncWithoutDetaching([
                $branch->id,
            ]);
        }

        // Doctors
        $specialties = Specialty::cases();
        $doctors = collect();
        $doctorBranchIds = [];

        foreach (range(1, 5) as $index) {
            $user = $this->createUser(
                'Doctor ' . $index,
                'doctor' . $index . '@balsam.test',
                UserRole::DOCTOR
            );

            $demoUsers->push($user);

            $specialty = $specialties[
                ($index - 1) % count($specialties)
            ];

            $doctor = Doctor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialty' => $specialty,
                    'license_number' => 'DOC-' . str_pad(
                        $index,
                        4,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'bio' => 'Experienced doctor at Balsam Clinics.',
                    'is_active' => true,
                ]
            );

            $doctors->push($doctor);

            $primaryBranch = $branches[
                ($index - 1) % $branches->count()
            ];

            $branchIds = [$primaryBranch->id];

            // The first three doctors work at a second branch too.
            if ($index <= 3) {
                $secondaryBranch = $branches[$index % $branches->count()];
                $branchIds[] = $secondaryBranch->id;
            }

            $doctorBranchIds[$doctor->id] = $branchIds;

            $user->branches()->syncWithoutDetaching($branchIds);
        }

       
        // Doctor schedules

        // Remove old schedules belonging to demo doctors only.
        // This prevents outdated branch assignments from previous seed runs.
        DoctorSchedule::whereIn('doctor_id', $doctors->pluck('id'))->delete();

        foreach ($doctors as $index => $doctor) {
            $branchIds = $doctorBranchIds[$doctor->id];

            foreach ($branchIds as $branchIndex => $branchId) {

                // Doctors with multiple branches work on different days.
                if (count($branchIds) > 1) {
                    $allowedDays = $branchIndex === 0
                        ? ['MONDAY', 'WEDNESDAY', 'FRIDAY']
                        : ['TUESDAY', 'THURSDAY'];
                } else {
                    // Doctors assigned to one branch work on weekdays.
                    $allowedDays = [
                        'MONDAY',
                        'TUESDAY',
                        'WEDNESDAY',
                        'THURSDAY',
                        'FRIDAY',
                    ];
                }

                foreach (DayOfWeek::cases() as $day) {
                    if (!in_array($day->name, $allowedDays, true)) {
                        continue;
                    }

                    DoctorSchedule::create([
                        'doctor_id' => $doctor->id,
                        'branch_id' => $branchId,
                        'day_of_week' => $day->value,
                        'start_time' => $branchIndex === 0
                            ? '08:00:00'
                            : '10:00:00',
                        'end_time' => $branchIndex === 0
                            ? '17:00:00'
                            : '16:00:00',
                        'is_active' => true,
                    ]);
                }
            }
        }

            
        

        // Patients
        $patients = collect();

        foreach (range(1, 15) as $index) {
            $user = $this->createUser(
                'Patient ' . $index,
                'patient' . $index . '@balsam.test',
                UserRole::PATIENT
            );

            $demoUsers->push($user);

            $patient = Patient::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => 'Patient ' . $index,
                    'phone' => '09900000' . str_pad(
                        $index,
                        2,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'birth_date' => Carbon::today()->subYears(20 + $index),
                    'gender' => $index % 2 === 0 ? 'female' : 'male',
                    'is_booking_blocked' => in_array(
                        $index,
                        [1, 14, 15],
                        true
                    ),
                    'blocked_until' => in_array(
                        $index,
                        [1, 14],
                        true
                    )
                        ? now()->addDays(7)
                        : ($index === 15 ? now()->subDays(7) : null),
                ]
            );

            $patients->push($patient);
        }

        // Appointment scenarios
        $scenarios = [
            [-20, AppointmentStatus::COMPLETED],
            [-19, AppointmentStatus::COMPLETED],
            [-18, AppointmentStatus::COMPLETED],
            [-17, AppointmentStatus::COMPLETED],
            [-16, AppointmentStatus::COMPLETED],

            [-15, AppointmentStatus::NO_SHOW],
            [-14, AppointmentStatus::NO_SHOW],
            [-13, AppointmentStatus::NO_SHOW],
            [-12, AppointmentStatus::NO_SHOW],

            [-11, AppointmentStatus::CANCELLED],
            [-10, AppointmentStatus::CANCELLED],

            [-9, AppointmentStatus::ARRIVED],
            [-8, AppointmentStatus::ARRIVED],
            [-7, AppointmentStatus::ARRIVED],

            [1, AppointmentStatus::BOOKED],
            [2, AppointmentStatus::BOOKED],
            [3, AppointmentStatus::BOOKED],
            [4, AppointmentStatus::BOOKED],
            [5, AppointmentStatus::BOOKED],
            [6, AppointmentStatus::BOOKED],
        ];

        $appointments = collect();

        foreach ($scenarios as $index => [$offset, $status]) {
            $doctor = $doctors[$index % $doctors->count()];

            // All four no-show appointments belong to the first patient.
            $patient = $status === AppointmentStatus::NO_SHOW
                ? $patients[0]
                : $patients[$index % $patients->count()];

            $branchId = $doctorBranchIds[$doctor->id][0];

            $date = $this->weekdayFromOffset($offset);

            $startHour = 9 + ($index % 6);

            $startTime = sprintf('%02d:00:00', $startHour);
            $endTime = sprintf('%02d:30:00', $startHour);

            $appointmentData = [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'branch_id' => $branchId,
                'appointment_date' => $date->toDateString(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $status,
                'checked_in_at' => in_array($status, [
                    AppointmentStatus::ARRIVED,
                    AppointmentStatus::COMPLETED,
                ], true)
                    ? $date->copy()->setTimeFromTimeString($startTime)
                    : null,
            ];

            $appointment = Appointment::withoutGlobalScopes()->firstOrNew([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'branch_id' => $branchId,
                'appointment_date' => $date->toDateString(),
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);

            $appointment->fill($appointmentData);
            $appointment->save();

            $appointments->push($appointment);
        }

        // Medical records
        foreach ($appointments as $appointment) {
            if ($appointment->status !== AppointmentStatus::COMPLETED) {
                continue;
            }

            MedicalRecord::updateOrCreate(
                ['appointment_id' => $appointment->id],
                [
                    'doctor_id' => $appointment->doctor_id,
                    'diagnosis' => 'General medical examination.',
                    'prescription' => 'Follow the prescribed treatment.',
                    'notes' => 'Demo medical record.',
                ]
            );
        }

        // Appointment reminders
        foreach ($appointments as $appointment) {
            if ($appointment->status !== AppointmentStatus::BOOKED) {
                continue;
            }

            AppointmentReminder::updateOrCreate(
                [
                    'appointment_id' => $appointment->id,
                    'reminder_type' => 'day_before',
                ],
                [
                    'status' => 'pending',
                    'sent_at' => null,
                ]
            );
        }

        // Sanctum tokens: replace previous demo tokens instead of duplicating them.
        foreach ($demoUsers->unique('id') as $user) {
            $user->tokens()
                ->where('name', 'postman')
                ->delete();

            $token = $user->createToken('postman')->plainTextToken;

            $this->command?->info(
                $user->email . ' | Token: ' . $token
            );
        }

        $this->command?->info(
            'Balsam demo data seeded successfully.'
        );
    }

    private function createUser(
        string $name,
        string $email,
        UserRole $role
    ): User {
        $user = User::query()
            ->where('email', $email)
            ->first();

        if (!$user) {
            $user = User::factory()->create([
                'name' => $name,
                'email' => $email,
            ]);
        } else {
            $user->name = $name;
        }

        $user->forceFill([
            'role' => $role,
        ]);

        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }

    private function weekdayFromOffset(int $offset): Carbon
    {
        $date = Carbon::today()->addDays($offset);
        $direction = $offset < 0 ? -1 : 1;

        while ($date->isWeekend()) {
            $date->addDays($direction);
        }

        return $date;
    }
}
