<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\MedicalRecordController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // المواعيد المتاحة (مع كاش Redis)
    Route::get('/availability', [AvailabilityController::class, 'index']);

    // إدارة المواعيد
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('/appointments/{appointment}/arrive', [AppointmentController::class, 'arrive']);

    // السجلات الطبية
    Route::post('/appointments/{appointment}/medical-record', [MedicalRecordController::class, 'store']);
    Route::get('/medical-records/{medicalRecord}', [MedicalRecordController::class, 'show']);
    Route::patch('/medical-records/{medicalRecord}', [MedicalRecordController::class, 'update']);
});