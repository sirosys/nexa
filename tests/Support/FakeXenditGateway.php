<?php

namespace Tests\Support;

use App\Services\Billing\XenditGateway;
use RuntimeException;

class FakeXenditGateway implements XenditGateway
{
    /** @var array<int, array{referenceId: string, amount: float, description: string, enabledMethods: array<int, string>}> */
    public array $calls = [];

    public bool $shouldFail = false;

    public string $status = 'PENDING';

    public function createPaymentRequest(string $referenceId, float $amount, string $description, array $enabledMethods): array
    {
        $this->calls[] = compact('referenceId', 'amount', 'description', 'enabledMethods');

        if ($this->shouldFail) {
            throw new RuntimeException('Simulated Xendit failure');
        }

        $id = 'pr-'.$referenceId;

        return [
            'id' => $id,
            'status' => $this->status,
            'checkout_url' => "https://checkout.xendit.co/{$referenceId}",
            'raw' => ['id' => $id, 'status' => $this->status],
        ];
    }
}
