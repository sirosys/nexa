<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceDismantle;
use App\Models\User;
use App\Notifications\ServiceDismantledNotification;
use App\Notifications\TechnicianAssignedForDismantleNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DismantleService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly MikrotikService $mikrotikService,
    ) {}

    /**
     * Antrekan Service untuk dismantle — dipakai baik oleh scheduler
     * (auto-queue Service yang sudah lama suspended, $queuedBy null)
     * maupun trigger manual staff (early termination, $queuedBy diisi).
     */
    public function queue(Service $service, ?User $queuedBy = null): ServiceDismantle
    {
        return DB::transaction(function () use ($service, $queuedBy) {
            if (! in_array($service->status, [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED], true)) {
                throw new RuntimeException('Layanan ini tidak dalam status yang bisa diantrekan untuk dismantle.');
            }

            if ($service->dismantle !== null) {
                throw new RuntimeException('Layanan ini sudah diantrekan untuk dismantle.');
            }

            if ($service->activation === null) {
                throw new RuntimeException('Layanan ini belum punya riwayat instalasi, tidak bisa diantrekan untuk dismantle.');
            }

            $dismantle = ServiceDismantle::create([
                'service_id' => $service->id,
                'activation_id' => $service->activation->id,
                'queued_by' => $queuedBy?->id,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $service->update(['status' => Service::STATUS_PENDING_DISMANTLE]);

            return $dismantle;
        });
    }

    public function assign(Service $service, User $technician, User $assignedBy): ServiceDismantle
    {
        $dismantle = DB::transaction(function () use ($service, $technician, $assignedBy) {
            $this->guardOpenForAssignment($service);

            $dismantle = $service->dismantle;

            $dismantle->update([
                'technician_id' => $technician->id,
                'assigned_by' => $assignedBy->id,
                'claimed_at' => now(),
                'updated_by' => Auth::id(),
            ]);

            $service->update(['status' => Service::STATUS_DISMANTLING]);

            return $dismantle;
        });

        $this->notificationService->send($technician, new TechnicianAssignedForDismantleNotification($service));

        return $dismantle;
    }

    public function claim(Service $service, User $technician): ServiceDismantle
    {
        return DB::transaction(function () use ($service, $technician) {
            $this->guardOpenForAssignment($service);

            $dismantle = $service->dismantle;

            $dismantle->update([
                'technician_id' => $technician->id,
                'assigned_by' => null,
                'claimed_at' => now(),
                'updated_by' => Auth::id(),
            ]);

            $service->update(['status' => Service::STATUS_DISMANTLING]);

            return $dismantle;
        });
    }

    /**
     * @param  array{photo: ?UploadedFile, notes: ?string}  $data
     */
    public function complete(Service $service, array $data): Service
    {
        if ($service->status !== Service::STATUS_DISMANTLING) {
            throw new RuntimeException('Layanan ini tidak dalam status sedang dismantle.');
        }

        $dismantle = $service->dismantle;

        if ($dismantle === null) {
            throw new RuntimeException('Belum ada teknisi yang ditugaskan untuk dismantle ini.');
        }

        $service = DB::transaction(function () use ($service, $dismantle, $data) {
            $dismantle->update([
                'photo' => isset($data['photo']) ? $data['photo']->store('dismantles', 'local') : $dismantle->photo,
                'notes' => $data['notes'] ?? null,
                'completed_at' => now(),
                'updated_by' => Auth::id(),
            ]);

            $service->update([
                'status' => Service::STATUS_DISMANTLED,
                'dismantled_at' => now(),
            ]);

            return $service;
        });

        $this->mikrotikService->remove($service);
        $this->notificationService->send($service->user, new ServiceDismantledNotification($service));

        return $service;
    }

    private function guardOpenForAssignment(Service $service): void
    {
        if ($service->status !== Service::STATUS_PENDING_DISMANTLE) {
            throw new RuntimeException('Layanan ini tidak dalam status menunggu dismantle.');
        }

        if ($service->dismantle?->technician_id !== null) {
            throw new RuntimeException('Dismantle ini sudah ditugaskan/diklaim teknisi lain.');
        }
    }
}
