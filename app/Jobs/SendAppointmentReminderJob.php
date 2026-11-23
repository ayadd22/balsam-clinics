<?php

namespace App\Jobs;

use App\Contracts\SmsServiceInterface;
use App\Models\AppointmentReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 60;

    public function __construct(
        public int $reminderId
    ) {}

    public function handle(SmsServiceInterface $smsService): void
    {
        $reminder = AppointmentReminder::with('appointment.patient')
            ->findOrFail($this->reminderId);

        if ($reminder->status === 'sent') {
            return;
        }

        $smsService->sendAppointmentReminder($reminder->appointment);

        $reminder->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        AppointmentReminder::whereKey($this->reminderId)
            ->where('status', 'pending')
            ->update([
                'status' => 'failed',
            ]);
    }
}
