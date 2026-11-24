<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Services\NotificationDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class SmsNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_notification_is_logged_as_sent(): void
    {
        config(['services.sms.simulate_failure' => false]);

        Log::shouldReceive('info')->once();

        app(NotificationDeliveryService::class)->sendSms(
            '+963944111222',
            'تأكيد حجز موعدكم في عيادات بلسم',
            'test-sms-key-1'
        );

        $this->assertDatabaseHas('notification_logs', [
            'idempotency_key' => 'test-sms-key-1',
            'channel' => 'sms',
            'status' => 'sent',
        ]);
    }

    public function test_duplicate_sms_with_same_key_is_not_sent_twice(): void
    {
        config(['services.sms.simulate_failure' => false]);

        Log::shouldReceive('info')->once();

        $service = app(NotificationDeliveryService::class);

        $service->sendSms('+963944111222', 'رسالة تأكيد', 'test-sms-key-2');
        $service->sendSms('+963944111222', 'رسالة تأكيد', 'test-sms-key-2');

        $this->assertDatabaseCount('notification_logs', 1);
    }

    public function test_failed_sms_provider_is_recorded_and_exception_is_thrown(): void
    {
        config(['services.sms.simulate_failure' => true]);

        try {
            app(NotificationDeliveryService::class)->sendSms(
                '+963944111222',
                'رسالة تذكير',
                'test-sms-key-3'
            );

            $this->fail('Expected SMS provider failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'SMS',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('notification_logs', [
            'idempotency_key' => 'test-sms-key-3',
            'status' => 'failed',
        ]);
    }

    public function test_failed_sms_notification_can_be_retried(): void
    {
        $service = app(NotificationDeliveryService::class);

        config(['services.sms.simulate_failure' => true]);

        try {
            $service->sendSms('+963944111222', 'رسالة تذكير', 'test-sms-key-4');
        } catch (RuntimeException) {
            // فشل محاكى متوقع
        }

        config(['services.sms.simulate_failure' => false]);

        Log::shouldReceive('info')->once();

        $service->sendSms('+963944111222', 'رسالة تذكير', 'test-sms-key-4');

        $this->assertDatabaseHas('notification_logs', [
            'idempotency_key' => 'test-sms-key-4',
            'status' => 'sent',
        ]);
    }
}