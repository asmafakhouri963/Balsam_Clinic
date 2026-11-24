<?php

namespace App\Models;

use App\Enums\Specialty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'specialty', 'bio'];

    protected function casts(): array
    {
        return [
            'specialty' => Specialty::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function scopeBySpecialty(Builder $query, Specialty|string $specialty): Builder
    {
        $value = $specialty instanceof Specialty ? $specialty->value : $specialty;

        return $query->where('specialty', $value);
    }

    public function scopeInBranch(Builder $query, int $branchId): Builder
    {
        return $query->whereHas('schedules', fn (Builder $q) => $q->where('branch_id', $branchId));
    }
}