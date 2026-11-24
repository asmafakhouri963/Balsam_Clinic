<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\Specialty;
use App\Models\Scopes\ReceptionistBranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'branch_id',
        'doctor_id',
        'patient_id',
        'appointment_date',
        'start_time',
        'end_time',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'status' => AppointmentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ReceptionistBranchScope());
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopeForSpecialty(Builder $query, Specialty|string $specialty): Builder
    {
        $value = $specialty instanceof Specialty ? $specialty->value : $specialty;

        return $query->whereHas('doctor', fn (Builder $q) => $q->where('specialty', $value));
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('appointment_date', $date);
    }

    public function scopeWithStatus(Builder $query, AppointmentStatus|string $status): Builder
    {
        $value = $status instanceof AppointmentStatus ? $status->value : $status;

        return $query->where('status', $value);
    }
}