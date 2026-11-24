<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Enums\Specialty;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BalsamSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('Password123!');

        // 1. الفروع الثلاثة: دمشق، حلب، اللاذقية
        $damascus = Branch::updateOrCreate(
            ['code' => 'DAM'],
            [
                'name' => 'فرع دمشق',
                'address' => 'دمشق - أبو رمانة',
                'phone' => '+963112223344',
                'is_active' => true,
            ]
        );

        $aleppo = Branch::updateOrCreate(
            ['code' => 'ALP'],
            [
                'name' => 'فرع حلب',
                'address' => 'حلب - الجميلية',
                'phone' => '+963212223344',
                'is_active' => true,
            ]
        );

        $latakia = Branch::updateOrCreate(
            ['code' => 'LAT'],
            [
                'name' => 'فرع اللاذقية',
                'address' => 'اللاذقية - شارع الثورة',
                'phone' => '+963412223344',
                'is_active' => true,
            ]
        );

        // 2. مستخدم الإدارة (Admin)
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@balsam.test'],
            [
                'name' => 'مدير النظام - بلسم',
                'phone' => '+963991000001',
                'password' => $password,
                'role' => UserRole::Admin,
                'branch_id' => null,
            ]
        );

        // 3. موظفو الاستقبال في الفروع
        $damReceptionist = User::updateOrCreate(
            ['email' => 'reception.dam@balsam.test'],
            [
                'name' => 'استقبال دمشق',
                'phone' => '+963991000002',
                'password' => $password,
                'role' => UserRole::Receptionist,
                'branch_id' => $damascus->id,
            ]
        );

        $alpReceptionist = User::updateOrCreate(
            ['email' => 'reception.alp@balsam.test'],
            [
                'name' => 'استقبال حلب',
                'phone' => '+963991000003',
                'password' => $password,
                'role' => UserRole::Receptionist,
                'branch_id' => $aleppo->id,
            ]
        );

        $latReceptionist = User::updateOrCreate(
            ['email' => 'reception.lat@balsam.test'],
            [
                'name' => 'استقبال اللاذقية',
                'phone' => '+963991000004',
                'password' => $password,
                'role' => UserRole::Receptionist,
                'branch_id' => $latakia->id,
            ]
        );

        // 4. الأطباء ودوامهم في أكثر من فرع وأيام مختلفة
        $doc1User = User::updateOrCreate(
            ['email' => 'doctor.cardiologist@balsam.test'],
            [
                'name' => 'د. سامر خوري',
                'phone' => '+963992000001',
                'password' => $password,
                'role' => UserRole::Doctor,
                'branch_id' => $damascus->id,
            ]
        );

        $doc1 = Doctor::updateOrCreate(
            ['user_id' => $doc1User->id],
            [
                'specialty' => Specialty::Cardiology,
                'bio' => 'أخصائي أمراض القلب في فروع دمشق واللاذقية',
            ]
        );

        $doc2User = User::updateOrCreate(
            ['email' => 'doctor.dermatologist@balsam.test'],
            [
                'name' => 'د. ريم منصور',
                'phone' => '+963992000002',
                'password' => $password,
                'role' => UserRole::Doctor,
                'branch_id' => $aleppo->id,
            ]
        );

        $doc2 = Doctor::updateOrCreate(
            ['user_id' => $doc2User->id],
            [
                'specialty' => Specialty::Dermatology,
                'bio' => 'أخصائية أمراض الجلدية في فروع حلب ودمشق',
            ]
        );

        $schedules = [
            // د. سامر في دمشق (الأحد والثلاثاء) وفي اللاذقية (الخميس)
            [
                'doctor_id' => $doc1->id,
                'branch_id' => $damascus->id,
                'day_of_week' => DayOfWeek::Sunday,
                'start_time' => '09:00:00',
                'end_time' => '13:00:00',
                'slot_duration_minutes' => 30,
            ],
            [
                'doctor_id' => $doc1->id,
                'branch_id' => $damascus->id,
                'day_of_week' => DayOfWeek::Tuesday,
                'start_time' => '10:00:00',
                'end_time' => '14:00:00',
                'slot_duration_minutes' => 30,
            ],
            [
                'doctor_id' => $doc1->id,
                'branch_id' => $latakia->id,
                'day_of_week' => DayOfWeek::Thursday,
                'start_time' => '11:00:00',
                'end_time' => '15:00:00',
                'slot_duration_minutes' => 30,
            ],
            // د. ريم في حلب (الأحد والأربعاء) وفي دمشق (الخميس)
            [
                'doctor_id' => $doc2->id,
                'branch_id' => $aleppo->id,
                'day_of_week' => DayOfWeek::Sunday,
                'start_time' => '09:30:00',
                'end_time' => '13:30:00',
                'slot_duration_minutes' => 30,
            ],
            [
                'doctor_id' => $doc2->id,
                'branch_id' => $aleppo->id,
                'day_of_week' => DayOfWeek::Wednesday,
                'start_time' => '10:00:00',
                'end_time' => '14:00:00',
                'slot_duration_minutes' => 30,
            ],
            [
                'doctor_id' => $doc2->id,
                'branch_id' => $damascus->id,
                'day_of_week' => DayOfWeek::Thursday,
                'start_time' => '12:00:00',
                'end_time' => '16:00:00',
                'slot_duration_minutes' => 30,
            ],
        ];

        foreach ($schedules as $schedule) {
            DoctorSchedule::firstOrCreate(
                [
                    'doctor_id' => $schedule['doctor_id'],
                    'branch_id' => $schedule['branch_id'],
                    'day_of_week' => $schedule['day_of_week']->value,
                    'start_time' => $schedule['start_time'],
                ],
                $schedule
            );
        }

        // 5. المرضى (بما فيهم مريض عنده غيابين لتهيئة اختبار المنع)
        $patient1User = User::updateOrCreate(
            ['email' => 'patient1@balsam.test'],
            [
                'name' => 'أحمد الحسن',
                'phone' => '+963993000001',
                'password' => $password,
                'role' => UserRole::Patient,
                'branch_id' => $damascus->id,
            ]
        );

        $patient1 = Patient::updateOrCreate(
            ['phone' => '+963993000001'],
            [
                'user_id' => $patient1User->id,
                'full_name' => 'أحمد الحسن',
                'date_of_birth' => '1992-05-14',
                'no_show_count' => 0,
                'banned_until' => null,
            ]
        );

        $patient2User = User::updateOrCreate(
            ['email' => 'patient.twonoshows@balsam.test'],
            [
                'name' => 'نور العلي',
                'phone' => '+963993000002',
                'password' => $password,
                'role' => UserRole::Patient,
                'branch_id' => $damascus->id,
            ]
        );

        // مريض عنده غيابين حالياً لكي يصبح 3 بعد أمر "لم يحضر" ويُمنع من الحجز أونلاين
        $patientTwoNoShows = Patient::updateOrCreate(
            ['phone' => '+963993000002'],
            [
                'user_id' => $patient2User->id,
                'full_name' => 'نور العلي',
                'date_of_birth' => '1995-08-22',
                'no_show_count' => 2,
                'banned_until' => null,
            ]
        );

        $patient3 = Patient::updateOrCreate(
            ['phone' => '+963993000003'],
            [
                'user_id' => null,
                'full_name' => 'مازن الخطيب',
                'date_of_birth' => '1988-11-03',
                'no_show_count' => 0,
                'banned_until' => null,
            ]
        );

        // 6. مواعيد ليوم غدٍ + مواعيد فائتة بدون تسجيل وصول + موعد مكتمل مع سجل طبي
        $tz = 'Asia/Damascus';
        $tomorrow = Carbon::now($tz)->addDay()->toDateString();
        $pastDate = Carbon::now($tz)->subDay()->toDateString();

        // موعد غدٍ في دمشق
        $apptTomorrow = Appointment::withoutGlobalScopes()->firstOrCreate(
            ['reference_number' => '000001-2026-DAM'],
            [
                'branch_id' => $damascus->id,
                'doctor_id' => $doc1->id,
                'patient_id' => $patient1->id,
                'appointment_date' => $tomorrow,
                'start_time' => '09:00:00',
                'end_time' => '09:30:00',
                'status' => AppointmentStatus::Booked,
                'notes' => 'موعد متابعة غداً',
            ]
        );

        // موعد فائت بدون تسجيل وصول للمريض الذي يملك غيابين
        Appointment::withoutGlobalScopes()->firstOrCreate(
            ['reference_number' => '000002-2026-DAM'],
            [
                'branch_id' => $damascus->id,
                'doctor_id' => $doc1->id,
                'patient_id' => $patientTwoNoShows->id,
                'appointment_date' => $pastDate,
                'start_time' => '10:00:00',
                'end_time' => '10:30:00',
                'status' => AppointmentStatus::Booked,
                'notes' => 'موعد فائت لاختبار أمر لم يحضر ومنع الحجز بعد الغياب الثالث',
            ]
        );

        // موعد مكتمل وسجل طبي سابق
        $completedAppt = Appointment::withoutGlobalScopes()->firstOrCreate(
            ['reference_number' => '000003-2026-ALP'],
            [
                'branch_id' => $aleppo->id,
                'doctor_id' => $doc2->id,
                'patient_id' => $patient3->id,
                'appointment_date' => $pastDate,
                'start_time' => '11:00:00',
                'end_time' => '11:30:00',
                'status' => AppointmentStatus::Completed,
                'notes' => 'كشف سابق',
            ]
        );

        MedicalRecord::firstOrCreate(
            ['appointment_id' => $completedAppt->id],
            [
                'doctor_id' => $doc2->id,
                'patient_id' => $patient3->id,
                'diagnosis' => 'التهاب جلد تماسي بسيط',
                'prescription' => 'مرهم موضعي مرتين يومياً لمدة 7 أيام',
                'notes' => 'تجنب المنظفات القاسية',
            ]
        );

        // 7. توليد Sanctum Tokens للمستخدمين الأساسيين لتسهيل الاختبار
        $tokens = [
            'admin' => $adminUser->createToken('balsam-admin')->plainTextToken,
            'reception_dam' => $damReceptionist->createToken('balsam-reception-dam')->plainTextToken,
            'reception_alp' => $alpReceptionist->createToken('balsam-reception-alp')->plainTextToken,
            'reception_lat' => $latReceptionist->createToken('balsam-reception-lat')->plainTextToken,
            'doctor_cardio' => $doc1User->createToken('balsam-doctor-cardio')->plainTextToken,
            'doctor_derma' => $doc2User->createToken('balsam-doctor-derma')->plainTextToken,
            'patient1' => $patient1User->createToken('balsam-patient1')->plainTextToken,
            'patient2' => $patient2User->createToken('balsam-patient2')->plainTextToken,
        ];

        $this->command?->info('BalsamSeeder completed. Sample Sanctum tokens:');
        foreach ($tokens as $label => $token) {
            $this->command?->line(" - {$label}: {$token}");
        }
    }
}