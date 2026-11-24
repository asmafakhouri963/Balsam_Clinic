<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicalRecordRequest;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Http\Resources\MedicalRecordResource;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Services\MedicalRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    public function __construct(
        private readonly MedicalRecordService $medicalRecordService,
    ) {}

    public function store(StoreMedicalRecordRequest $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();

        if ($user->isDoctor() && $user->doctor && (int) $appointment->doctor_id !== (int) $user->doctor->id) {
            return response()->json([
                'message' => 'غير مصرح لك بإضافة سجل طبي لموعد طبيب آخر.',
            ], 403);
        }

        $record = $this->medicalRecordService->createForAppointment($appointment, $request->validated());

        return (new MedicalRecordResource($record))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateMedicalRecordRequest $request, MedicalRecord $medicalRecord): JsonResponse
    {
        $user = $request->user();

        if ($user->isDoctor() && $user->doctor && (int) $medicalRecord->doctor_id !== (int) $user->doctor->id) {
            return response()->json([
                'message' => 'غير مصرح لك بتعديل سجل طبي يخص طبيباً آخر.',
            ], 403);
        }

        $updated = $this->medicalRecordService->updateRecord($medicalRecord, $request->validated());

        return (new MedicalRecordResource($updated))->response();
    }

    public function show(Request $request, MedicalRecord $medicalRecord): JsonResponse
    {
        $user = $request->user();

        if ($user->isPatient()) {
            return response()->json([
                'message' => 'غير مصرح للمريض بالوصول المباشر إلى السجل الطبي.',
            ], 403);
        }

        if ($user->isDoctor() && $user->doctor && (int) $medicalRecord->doctor_id !== (int) $user->doctor->id) {
            return response()->json([
                'message' => 'غير مصرح بالاطلاع على سجل طبي يخص طبيباً آخر.',
            ], 403);
        }

        $medicalRecord->load(['appointment', 'doctor.user', 'patient', 'auditLogs.user']);

        return (new MedicalRecordResource($medicalRecord))->response();
    }
}