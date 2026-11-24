<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * يسجّل فقط الحقول التي تغيرت فعلياً في الموديل مع الحماية من الحلقات التكرارية.
     */
    public function recordModelChanges(Model $model, array $watchedFields = []): ?AuditLog
    {
        $dirty = $model->getDirty();

        // استبعاد الحقول التقنية إذا لم تُحدّد حقول مراقبة بعينها
        unset($dirty['updated_at'], $dirty['created_at'], $dirty['last_visit_at']);

        if (! empty($watchedFields)) {
            $dirty = array_intersect_key($dirty, array_flip($watchedFields));
        }

        if (empty($dirty)) {
            return null;
        }

        $oldValues = [];
        $newValues = [];

        foreach ($dirty as $field => $newValue) {
            $oldRaw = $model->getOriginal($field);
            $oldValues[$field] = $oldRaw instanceof \BackedEnum ? $oldRaw->value : $oldRaw;
            $newValues[$field] = $newValue instanceof \BackedEnum ? $newValue->value : $newValue;
        }

        // كتابة سجل التعديل مباشرة دون إطلاق Observer إضافي على AuditLog
        return AuditLog::query()->create([
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'user_id' => Auth::id(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'created_at' => now(),
        ]);
    }
}