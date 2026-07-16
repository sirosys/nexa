<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogService
{
    /**
     * Catat satu aksi sensitif — pola sama persis NotificationService::send():
     * kegagalan apa pun (mis. DB down) ditelan jadi Log::warning, tidak
     * pernah throw ke pemanggil, supaya audit logging tidak pernah
     * menggagalkan aksi bisnis yang sedang dicatat.
     *
     * Actor diambil dari sesi login saat ini (Auth::id()) — null berarti
     * aksi dipicu sistem (scheduler/webhook tanpa sesi login).
     */
    public function record(string $action, ?Model $auditable, string $description, array $changes = []): void
    {
        try {
            AuditLog::create([
                'actor_id' => Auth::id(),
                'action' => $action,
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'description' => $description,
                'changes' => $changes === [] ? null : $changes,
                'ip_address' => app()->runningInConsole() ? null : request()?->ip(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Audit log gagal disimpan', [
                'action' => $action,
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
