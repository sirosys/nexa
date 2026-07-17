<?php

namespace Tests\Support;

use App\Models\Site;
use App\Services\Mikrotik\MikrotikGateway;
use RuntimeException;

class CapturingMikrotikGateway implements MikrotikGateway
{
    /** @var array<int, array{action: string, site_id: int, username: string, password: ?string, profile: ?string}> */
    public array $calls = [];

    public bool $shouldFail = false;

    public bool $reachable = true;

    public function createPppoeSecret(Site $site, string $username, string $password, ?string $profile = null): bool
    {
        return $this->record('createPppoeSecret', $site, $username, $password, $profile);
    }

    public function enablePppoeSecret(Site $site, string $username): bool
    {
        return $this->record('enablePppoeSecret', $site, $username);
    }

    public function disablePppoeSecret(Site $site, string $username): bool
    {
        return $this->record('disablePppoeSecret', $site, $username);
    }

    public function deletePppoeSecret(Site $site, string $username): bool
    {
        return $this->record('deletePppoeSecret', $site, $username);
    }

    public function isReachable(Site $site): bool
    {
        $this->calls[] = [
            'action' => 'isReachable',
            'site_id' => $site->id,
            'username' => '',
            'password' => null,
            'profile' => null,
        ];

        if ($this->shouldFail) {
            throw new RuntimeException('Simulated MikroTik failure');
        }

        return $this->reachable;
    }

    private function record(string $action, Site $site, string $username, ?string $password = null, ?string $profile = null): bool
    {
        $this->calls[] = [
            'action' => $action,
            'site_id' => $site->id,
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
