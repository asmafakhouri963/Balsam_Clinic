<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isDoctor() || $user->isAdmin());
    }

    public function rules(): array
    {
        return [
            'diagnosis' => ['sometimes', 'required', 'string', 'min:3', 'max:3000'],
            'prescription' => ['sometimes', 'required', 'string', 'min:3', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'diagnosis.required' => 'حقل التشخيص لا يمكن أن يكون فارغاً.',
            'diagnosis.min' => 'التشخيص يجب ألا يقل عن 3 أحرف.',
            'prescription.required' => 'حقل الوصفة الطبية لا يمكن أن يكون فارغاً.',
            'prescription.min' => 'الوصفة الطبية يجب ألا تقل عن 3 أحرف.',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 2000 حرف.',
        ];
    }
}