<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'يرجى تحديد الفرع.',
            'branch_id.exists' => 'الفرع المحدد غير موجود.',
            'doctor_id.required' => 'يرجى تحديد الطبيب.',
            'doctor_id.exists' => 'الطبيب المحدد غير موجود.',
            'patient_id.required' => 'يرجى تحديد المريض.',
            'patient_id.exists' => 'المريض المحدد غير موجود.',
            'appointment_date.required' => 'يرجى تحديد تاريخ الموعد.',
            'appointment_date.date' => 'صيغة تاريخ الموعد غير صحيحة.',
            'appointment_date.after_or_equal' => 'لا يمكن حجز موعد في تاريخ ماضٍ.',
            'start_time.required' => 'يرجى تحديد وقت بداية الموعد.',
            'start_time.date_format' => 'صيغة وقت البداية يجب أن تكون HH:MM.',
            'end_time.date_format' => 'صيغة وقت النهاية يجب أن تكون HH:MM.',
            'end_time.after' => 'وقت النهاية يجب أن يكون بعد وقت البداية.',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف.',
        ];
    }
}