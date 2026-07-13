<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Notifications\ServiceActivatedNotification;
use App\Notifications\TechnicianAssignedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InstallationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly MikrotikService $mikrotikService,
    ) {}

    public function assign(Service $service, User $installer, User $assignedBy): ServiceActivation
    {
        $activation = DB::transaction(function () use ($service, $installer, $assignedBy) {
            $this->guardOpenForAssignment($service);

            $activation = $this->activationFor($service);

            $activation->update([
                'installer_id' => $installer->id,
                'assigned_by' => $assignedBy->id,
                'claimed_at' => now(),
                'updated_by' => Auth::id(),
            ]);

            $service->update(['status' => Service::STATUS_INSTALLING]);

            return $activation;
        });

        $this->notificationService->send($installer, new TechnicianAssignedNotification($service));

        return $activation;
    }

    public function claim(Service $service, User $installer): ServiceActivation
    {
        return DB::transaction(function () use ($service, $installer) {
            $this->guardOpenForAssignment($service);

            $activation = $this->activationFor($service);

            $activation->update([
                'installer_id' => $installer->id,
                'assigned_by' => null,
                'claimed_at' => now(),
                'updated_by' => Auth::id(),
            ]);

            $service->update(['status' => Service::STATUS_INSTALLING]);

            return $activation;
        });
    }

    /**
     * @param  array{odp_port: string, cable_length: ?float, photo: ?UploadedFile, notes: ?string}  $data
     */
    public function complete(Service $service, array $data): Service
    {
        if ($service->status !== Service::STATUS_INSTALLING) {
            throw new RuntimeException('Layanan ini tidak dalam status sedang instalasi.');
        }

        $activation = $service->activation;

        if ($activation === null) {
            throw new RuntimeException('Belum ada teknisi yang ditugaskan untuk layanan ini.');
        }

        $service = DB::transaction(function () use ($service, $activation, $data) {
            $activation->update([
                'odp_port' => $data['odp_port'],
                'cable_length' => $data['cable_length'] ?? null,
                'photo' => isset($data['photo']) ? $data['photo']->store('installations', 'local') : $activation->photo,
                'notes' => $data['notes'] ?? null,
                'completed_at' => now(),
                'updated_by' => Auth::id(),
            ]);

            $activatedAt = now();
            $durationMonths = (int) ($service->package->duration_months ?? 1);

            $service->update([
                'status' => Service::STATUS_ACTIVE,
                'activated_at' => $activatedAt,
                'expired_at' => $activatedAt->copy()->addMonths($durationMonths),
            ]);

            return $service;
        });

        $this->mikrotikService->provision($service);
        $this->notificationService->send($service->user, new ServiceActivatedNotification($service));

        return $service;
    }

    private function guardOpenForAssignment(Service $service): void
    {
        if ($service->status !== Service::STATUS_PENDING_INSTALLATION) {
            throw new RuntimeException('Layanan ini tidak dalam status menunggu instalasi.');
        }

        if ($service->activation?->installer_id !== null) {
            throw new RuntimeException('Instalasi ini sudah ditugaskan/diklaim teknisi lain.');
        }
    }

    /**
     * Cari-atau-buat baris ServiceActivation untuk service ini, disambungkan
     * ke Sale registrasi yang sudah settled (satu Service = satu Sale
     * pendaftaran awal di iterasi ini — belum ada alur upgrade/renewal
     * yang bikin Sale kedua, lihat CLAUDE.md "Sales").
     */
    private function activationFor(Service $service): ServiceActivation
    {
        if ($service->activation !== null) {
            return $service->activation;
        }

        $sale = $service->sales()->whereNotNull('settled_at')->latest('settled_at')->firstOrFail();

        return ServiceActivation::create([
            'service_id' => $service->id,
            'sale_id' => $sale->id,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
    }
}
