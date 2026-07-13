<?php

namespace Tests\Support;

use App\Models\Pop;
use App\Services\Mikrotik\MikrotikGateway;
use RuntimeException;

class CapturingMikrotikGateway implements MikrotikGateway
{
    /** @var array<int, array{action: string, pop_id: int, username: string, password: ?string, profile: ?string}> */
    public array $calls = [];

    public bool $shouldFail = false;

    public bool $reachable = true;

    public function createPppoeSecret(Pop $pop, string $username, string $password, ?string $profile = null): bool
    {
        return $this->record('createPppoeSecret', $pop, $username, $password, $profile);
    }

    public function enablePppoeSecret(Pop $pop, string $username): bool
    {
        return $this->record('enablePppoeSecret', $pop, $username);
    }

    public function disablePppoeSecret(Pop $pop, string $username): bool
    {
        return $this->record('disablePppoeSecret', $pop, $username);
    }

    public function deletePppoeSecret(Pop $pop, string $username): bool
    {
        return $this->record('deletePppoeSecret', $pop, $username);
    }

    public function isReachable(Pop $pop): bool
    {
        $this->calls[] = [
            'action' => 'isReachable',
            'pop_id' => $pop->id,
            'username' => '',
            'password' => null,
            'profile' => null,
        ];

        if ($this->shouldFail) {
            throw new RuntimeException('Simulated MikroTik failure');
        }

        return $this->reachable;
    }

    private function record(string $action, Pop $pop, string $username, ?string $password = null, ?string $profile = null): bool
    {
        $this->calls[] = [
            'action' => $action,
            'pop_id' => $pop->id,
            'username' => $username,
            'password' => $password,
            'profile' => $profile,
        ];

        if ($this->shouldFail) {
            throw new RuntimeException('Simulated MikroTik failure');
        }

        return true;
    }
}
