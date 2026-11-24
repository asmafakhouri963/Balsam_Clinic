<?php

namespace App\Services;

use App\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSenderInterface
{
    public function send(string $phone, string $message, array $context = []): void
    {
        Log::channel('stack')->info('[BALSAM SMS LOG]', [
            'to' => $phone,
            'message' => $message,
            'context' => $context,
        ]);
    }
}