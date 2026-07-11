<?php

namespace Tests\Support;

use App\Services\Billing\XenditGateway;
use RuntimeException;

class FakeXenditGateway implements XenditGateway
{
    /** @var array<int, array{referenceId: string, amount: float, description: string, channelCode: string, channelProperties: array<string, mixed>, type: string}> */
    public array $calls = [];

    public bool $shouldFail = false;

    public string $status = 'PENDING';

    public function createPaymentRequest(string $referenceId, float $amount, string $description, string $channelCode, array $channelProperties, string $type = 'PAY'): array
    {
        $this->calls[] = compact('referenceId', 'amount', 'description', 'channelCode', 'channelProperties', 'type');

        if ($this->shouldFail) {
            throw new RuntimeException('Simulated Xendit failure');
        }

        $id = 'pr-'.$referenceId;

        $actions = [
            ['type' => 'REDIRECT_CUSTOMER', 'descriptor' => 'WEB_URL', 'value' => "https://checkout.xendit.co/{$referenceId}"],
        ];

        return [
            'id' => $id,
            'status' => $this->status,
            'checkout_url' => "https://checkout.xendit.co/{$referenceId}",
            'actions' => $actions,
            'raw' => ['id' => $id, 'status' => $this->status, 'actions' => $actions],
        ];
    }
}
