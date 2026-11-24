<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AvailabilityCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityCacheService $cacheService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'doctor_id.required' => 'يرجى تحديد الطبيب.',
            'doctor_id.exists' => 'الطبيب المحدد غير موجود.',
            'branch_id.required' => 'يرجى تحديد الفرع.',
            'branch_id.exists' => 'الفرع المحدد غير موجود.',
            'date.required' => 'يرجى تحديد التاريخ.',
            'date.date' => 'صيغة التاريخ غير صحيحة.',
            'date.after_or_equal' => 'لا يمكن عرض المواعيد المتاحة لتاريخ ماضٍ.',
        ]);

        $user = $request->user();
        if ($user && $user->isReceptionist() && $user->branch_id && (int) $validated['branch_id'] !== (int) $user->branch_id) {
            throw ValidationException::withMessages([
                'branch_id' => ['غير مسموح لموظف الاستقبال بالاستعلام عن فرع آخر.'],
            ]);
        }

        $slots = $this->cacheService->getAvailableSlots(
            doctorId: (int) $validated['doctor_id'],
            branchId: (int) $validated['branch_id'],
            date: $validated['date'],
        );

        return response()->json([
            'doctor_id' => (int) $validated['doctor_id'],
            'branch_id' => (int) $validated['branch_id'],
            'date' => $validated['date'],
            'available_slots' => $slots,
        ]);
    }
}