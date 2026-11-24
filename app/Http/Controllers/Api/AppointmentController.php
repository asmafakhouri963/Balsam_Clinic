<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Appointment::query()
            ->with(['branch', 'doctor.user', 'patient']);

        if ($request->filled('branch_id')) {
            $query->forBranch((int) $request->integer('branch_id'));
        }

        if ($request->filled('doctor_id')) {
            $query->forDoctor((int) $request->integer('doctor_id'));
        }

        if ($request->filled('specialty')) {
            $query->forSpecialty($request->string('specialty')->toString());
        }

        if ($request->filled('date')) {
            $query->onDate($request->string('date')->toString());
        }

        if ($request->filled('status')) {
            $query->withStatus($request->string('status')->toString());
        }

        return AppointmentResource::collection(
            $query->latest('appointment_date')->latest('start_time')->paginate(15)
        );
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if ($user->isReceptionist() && $user->branch_id && (int) $data['branch_id'] !== (int) $user->branch_id) {
            return response()->json([
                'message' => 'غير مسموح لموظف الاستقبال بحجز موعد خارج فرعه.',
            ], 403);
        }

        $isOnlineBooking = $user->isPatient();

        $appointment = $this->appointmentService->book($data, $isOnlineBooking);

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();

        if (! $this->canManageAppointment($user, $appointment)) {
            return response()->json([
                'message' => 'غير مصرح لك بإلغاء هذا الموعد.',
            ], 403);
        }

        $updated = $this->appointmentService->cancel($appointment);

        return (new AppointmentResource($updated))->response();
    }

    public function arrive(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isReceptionist()) {
            return response()->json([
                'message' => 'تسجيل الوصول متاح للإدارة وموظف الاستقبال فقط.',
            ], 403);
        }

        if ($user->isReceptionist() && $user->branch_id && (int) $appointment->branch_id !== (int) $user->branch_id) {
            return response()->json([
                'message' => 'الموعد يتبع فرعاً آخر خارج صلاحياتك.',
            ], 403);
        }

        $updated = $this->appointmentService->markArrived($appointment);

        return (new AppointmentResource($updated))->response();
    }

    private function canManageAppointment($user, Appointment $appointment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isReceptionist()) {
            return ! $user->branch_id || (int) $appointment->branch_id === (int) $user->branch_id;
        }

        if ($user->isPatient() && $user->patient) {
            return (int) $appointment->patient_id === (int) $user->patient->id;
        }

        return false;
    }
}