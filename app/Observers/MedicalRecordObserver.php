<?php

namespace App\Observers;

use App\Models\MedicalRecord;
use App\Services\AuditLogService;

class MedicalRecordObserver
{
    private const AUDITED_FIELDS = [
        'diagnosis',
        'prescription',
        'notes',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function updated(MedicalRecord $medicalRecord): void
    {
        // يسجّل فقط الحقول التي تغيرت فعلياً بدون التسبب بأي حلقة تكرارية
        $this->auditLogService->recordModelChanges($medicalRecord, self::AUDITED_FIELDS);
    }
}