<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDetail;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserService
{
    private const ROLE_LABELS = [
        'superadmin' => 'Superadmin',
        'technician' => 'Teknisi',
        'finance' => 'Admin/NOC',
        'customer' => 'Pelanggan',
    ];

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function create(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                // Placeholder inert — akun non-admin di NEXA selalu login lewat
                // OTP, password ini tidak pernah dipakai. Kolom NOT NULL
                // (peninggalan scaffolding default Laravel).
                'password' => Hash::make(Str::random(40)),
            ]);

            $user->assignRole($data['role']);

            // `code` sudah digenerate otomatis lewat User::booted() (semua
            // role, lihat CLAUDE.md "User") — tidak perlu diisi di sini.

            $userDetails = [];

            if (! empty($data['nik'])) {
                // Sudah divalidasi bisa di-parse di UserRequest — gender &
                // birth_date murni derivasi dari NIK, tidak diinput manual.
                $userDetails = array_merge(['nik' => $data['nik']], UserDetail::parseNik($data['nik']));
            }

            if (($data['ktp_photo'] ?? null) instanceof UploadedFile) {
                $userDetails['ktp_photo'] = $data['ktp_photo']->store('ktp', 'local');
            }

            $user->userDetails()->create($userDetails);

            return $user;
        });

        $this->notificationService->send($user, new UserRegisteredNotification(self::ROLE_LABELS[$data['role']] ?? $data['role']));

        $roleLabel = self::ROLE_LABELS[$data['role']] ?? $data['role'];
        $this->auditLogService->record(
            'user.created',
            $user,
            "Membuat akun pengguna baru \"{$user->name}\" (role: {$roleLabel}).",
        );

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $oldRole = $user->getRoleNames()->first();

        $user = DB::transaction(function () use ($user, $data) {
            $user->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
            ]);

            $user->syncRoles([$data['role']]);

            $userDetails = $user->userDetails;
            $updates = [];

            // NIK cuma boleh di-set kalau belum pernah ada — sekali tersimpan,
            // UserRequest sudah menolak percobaan mengubahnya, jadi di sini
            // aman untuk hanya menangani kasus "baru pertama kali diisi".
            if (($userDetails === null || $userDetails->nik === null) && ! empty($data['nik'])) {
                $updates = array_merge(['nik' => $data['nik']], UserDetail::parseNik($data['nik']));
            }

            if (($data['ktp_photo'] ?? null) instanceof UploadedFile) {
                if ($userDetails?->ktp_photo) {
                    Storage::disk('local')->delete($userDetails->ktp_photo);
                }

                $updates['ktp_photo'] = $data['ktp_photo']->store('ktp', 'local');
            }

            if ($updates !== []) {
                $user->userDetails()->updateOrCreate([], $updates);
            }

            return $user;
        });

        $newRole = $user->getRoleNames()->first();

        if ($oldRole !== $newRole) {
            $oldLabel = self::ROLE_LABELS[$oldRole] ?? $oldRole;
            $newLabel = self::ROLE_LABELS[$newRole] ?? $newRole;

            $this->auditLogService->record(
                'user.role_changed',
                $user,
                "Mengubah role pengguna \"{$user->name}\" dari {$oldLabel} menjadi {$newLabel}.",
                ['from' => $oldRole, 'to' => $newRole],
            );
        }

        return $user;
    }

    /**
     * Lengkapi NIK & foto KTP untuk user yang belum punya keduanya — dipakai
     * oleh modal "Lengkapi NIK & Foto KTP" di form Service (lihat CLAUDE.md
     * "Service"). Beda dari update(): di sini nik & ktp_photo wajib ada
     * berdua (sudah ditegakkan CompleteKycRequest), tidak ada kasus parsial.
     */
    public function completeKyc(User $user, string $nik, UploadedFile $ktpPhoto): User
    {
        return DB::transaction(function () use ($user, $nik, $ktpPhoto) {
            $updates = array_merge(['nik' => $nik], UserDetail::parseNik($nik));
            $updates['ktp_photo'] = $ktpPhoto->store('ktp', 'local');

            $user->userDetails()->updateOrCreate([], $updates);

            return $user->fresh('userDetails');
        });
    }

    public function delete(User $user): void
    {
        $name = $user->name;
        $phone = $user->phone;

        if ($user->userDetails?->ktp_photo) {
            Storage::disk('local')->delete($user->userDetails->ktp_photo);
        }

        $user->delete();

        $this->auditLogService->record(
            'user.deleted',
            null,
            "Menghapus akun pengguna \"{$name}\" ({$phone}).",
        );
    }
}
