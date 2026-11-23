<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\AuditLogController;


Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum');
});

Route::get('/user', function (\Illuminate\Http\Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/appointments', [AppointmentController::class, 'store']);

    Route::patch(
        '/appointments/{appointment}/cancel',
        [AppointmentController::class, 'cancel']
    );
    Route::get('/doctors', [DoctorController::class, 'index']);
    Route::get(
    '/appointments/availability',
    [AppointmentController::class, 'availability']
);


Route::get('/medical-records', [MedicalRecordController::class, 'index']);

Route::get(
    '/medical-records/{medicalRecord}',
    [MedicalRecordController::class, 'show']
);

Route::post(
    '/medical-records',
    [MedicalRecordController::class, 'store']
);

Route::put(
    '/medical-records/{medicalRecord}',
    [MedicalRecordController::class, 'update']
);
Route::get('/audit-logs', [AuditLogController::class, 'index']);

Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show']);
});
