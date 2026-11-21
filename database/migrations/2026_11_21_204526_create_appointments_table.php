<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('doctor_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('branch_id')
                ->constrained()
                ->restrictOnDelete();

            $table->date('appointment_date');
            $table->time('start_time');
            $table->time('end_time');

            $table->string('status')->default('booked');

            $table->timestamp('checked_in_at')->nullable();

            $table->string('reference_number')->unique();

            $table->timestamps();

            $table->index([
                'doctor_id',
                'appointment_date',
                'status',
            ]);

            $table->index([
                'branch_id',
                'appointment_date',
                'status',
            ]);

            $table->index([
                'patient_id',
                'appointment_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};