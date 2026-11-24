# Balsam Clinic API (نظام إدارة عيادات بلسم)

A multi-branch clinic management REST API built with **Laravel**, **Laravel Sanctum**, **MySQL**, **Redis**, and **Laravel Horizon**. It handles slot availability caching, appointment lifecycle and rules, medical records, automated scheduling, SMS notifications, and unified API error responses.

---

## Core Features & Business Rules

* **Multi-Branch & Role-Based Isolation:**
  * Supports multiple clinic branches (e.g., Damascus, Aleppo) and user roles (`Admin`, `Receptionist`, `Doctor`, `Patient`).
  * `ReceptionistBranchScope` enforces branch isolation so receptionists only access records belonging to their assigned branch.
* **Slot Availability & Redis Caching:**
  * Calculates available appointment slots based on doctor schedules (`DoctorSchedule`), slot duration, and existing bookings, with Redis caching (`AvailabilityCacheService`) invalidated on booking/cancellation changes.
* **Appointment Lifecycle & 3-Absence Ban Rule:**
  * Tracks appointment statuses (`Booked`, `Arrived`, `NoShow`, `Cancelled`) with unique generated reference numbers.
  * Automatically processes missed appointments via scheduled commands, increments `no_show_count`, and applies an online booking ban (`banned_until`) upon reaching the 3-absence threshold.
* **Medical Records:**
  * Supports creating, viewing, and updating medical records linked to appointments, accompanied by audit logging (`AuditLogService`).
* **Queues, Horizon & Automated Commands:**
  * Uses Redis queues monitored by **Laravel Horizon** (`high`, `default`, `reports`).
  * Scheduled Artisan commands handle appointment reminders, missed appointment processing, and monthly clinic reports.
  * SMS delivery (`NotificationDeliveryService`) logs deliveries in `notification_logs`, enforces idempotency keys to prevent duplicates, and supports failure simulation and retries.
* **Unified JSON Error Handling:**
  * All `/api/*` errors (`401`, `403`, `404`, `405`, `422`, and `500`) follow a consistent JSON structure (`success`, `message`, `errors`).

---

## Project Structure Highlights

* `app/Http/Controllers/Api/` — `AvailabilityController`, `AppointmentController`, `MedicalRecordController`
* `app/Services/` — `AppointmentService`, `AvailabilityCacheService`, `MedicalRecordService`, `AuditLogService`, `NotificationDeliveryService`
* `app/Models/Scopes/` — `ReceptionistBranchScope`
* `app/Console/Commands/` — `SendAppointmentRemindersCommand`, `ProcessMissedAppointmentsCommand`, `GenerateMonthlyClinicReportCommand`
* `app/Jobs/` & `app/Listeners/` & `app/Observers/` — Async jobs, event listeners, and model observers
* `database/migrations/`, `database/factories/`, `database/seeders/` — Schema, factories, and `BalsamSeeder`
* `tests/Feature/` — Feature and API test suites
* `Balsam_Clinic-API.postman_collection.json` & `Balsam_Clinic.postman_environment.json` — Postman collection and local environment

---

## Installation & Local Setup

1. **Install PHP & Node dependencies:**
   ```bash
   composer install
   npm install
   ```

2. **Configure environment variables:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Update `.env` with your **MySQL** credentials, **Redis** host/port, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, and `APP_TIMEZONE=Asia/Damascus`.

3. **Run migrations and seeders:**
   ```bash
   php artisan migrate --seed
   ```

4. **Clear configuration & cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

---

## Running the Application, Horizon & Scheduler

* **Start Laravel development server:**
  ```bash
  php artisan serve
  ```

* **Start Laravel Horizon (queue worker & dashboard):**
  ```bash
  php artisan horizon
  ```

* **Run scheduled tasks / Artisan commands:**
  ```bash
  php artisan schedule:run
  ```
  Or invoke commands directly:
  ```bash
  php artisan appointments:send-reminders
  php artisan appointments:process-missed
  php artisan reports:monthly-clinic
  ```

---

## Testing & Postman

* **Run the test suite:**
  ```bash
  php artisan test
  ```
  Or run Feature tests specifically:
  ```bash
  php artisan test --testsuite=Feature
  ```

* **Postman Testing:**
  1. Import `Balsam_Clinic-API.postman_collection.json` and `Balsam_Clinic.postman_environment.json` into Postman.
  2. Select the **Balsam Clinic Local** environment and populate the role tokens (`admin_token`, `reception_dam_token`, `reception_alp_token`, `doctor_token`, `patient_token`) and IDs as needed.