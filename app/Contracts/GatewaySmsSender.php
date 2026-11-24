<?php

namespace App\Services;

use App\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GatewaySmsSender implements SmsSenderInterface
{
    public function __construct(
        private readonly ?string $endpoint = null,
        private readonly ?string $apiKey = null,
    ) {}

    public function send(string $phone, string $message, array $context = []): void
    {
        if (! $this->endpoint || ! $this->apiKey) {
            throw new RuntimeException('SMS gateway endpoint or API key is not configured.');
        }

        Http::withToken($this->apiKey)
            ->timeout(10)
            ->post($this->endpoint, [
                'to' => $phone,
                'message' => $message,
                'context' => $context,
            ])
            ->throw();
    }
}