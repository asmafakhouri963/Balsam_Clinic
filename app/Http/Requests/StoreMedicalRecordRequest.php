<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isDoctor() || $user->isAdmin());
    }

    public function rules(): array
    {
        return [
            'diagnosis' => ['required', 'string', 'min:3', 'max:3000'],
            'prescription' => ['required', 'string', 'min:3', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'diagnosis.required' => 'حقل التشخيص مطلوب.',
            'diagnosis.min' => 'التشخيص يجب ألا يقل عن 3 أحرف.',
            'prescription.required' => 'حقل الوصفة الطبية مطلوب.',
            'prescription.min' => 'الوصفة الطبية يجب ألا تقل عن 3 أحرف.',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 2000 حرف.',
        ];
    }
}