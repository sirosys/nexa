<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserService
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $isAdmin = (bool) ($data['admin'] ?? false);

            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'admin' => $isAdmin,
                // Placeholder inert — akun non-admin di NEXA selalu login lewat
                // OTP. Kolom password & email NOT NULL (peninggalan scaffolding
                // default Laravel) tapi nilainya tidak pernah dipakai login.
                'password' => Hash::make(Str::random(40)),
                'email' => "user-{$data['phone']}@nexa.internal",
            ]);

            // `code` (buat invoicing/billing) cuma relevan untuk akun customer,
            // bukan staff/admin — dibiarkan null untuk akun admin.
            if (! $isAdmin) {
                $user->update([
                    'code' => 'CUS'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                ]);
            }

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
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'admin' => (bool) ($data['admin'] ?? false),
                // Ikut disinkronkan supaya tidak bentrok unique kalau nomor
                // telepon lama dipakai ulang oleh user lain nantinya.
                'email' => "user-{$data['phone']}@nexa.internal",
            ]);

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
    }

    public function delete(User $user): void
    {
        if ($user->userDetails?->ktp_photo) {
            Storage::disk('local')->delete($user->userDetails->ktp_photo);
        }

        $user->delete();
    }
}
