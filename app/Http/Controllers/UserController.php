<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteKycRequest;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['userDetails', 'roles'])
            ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request) {
                $q = $request->string('q')->trim()->value();
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', ['users' => $users, 'q' => $request->string('q')->value()]);
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated());

        return redirect()->route('users.index')->with('status', 'Pengguna berhasil ditambahkan.');
    }

    public function show(User $user): View
    {
        $user->load(['userDetails', 'roles']);

        return view('users.show', ['user' => $user]);
    }

    public function edit(User $user): View
    {
        $user->load(['userDetails', 'roles']);

        return view('users.edit', ['user' => $user]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, $request->validated());

        return redirect()->route('users.index')->with('status', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->userService->delete($user);

        return redirect()->route('users.index')->with('status', 'Pengguna berhasil dihapus.');
    }

    /**
     * Modal "Lengkapi NIK & Foto KTP" di form Service — dipanggil lewat
     * fetch (multipart, ada file upload) begitu staff memilih/membuat
     * pelanggan yang belum lengkap datanya. Bukan bagian dari
     * authorizeResource() (bukan action resource standar), jadi gate
     * otorisasi ditulis eksplisit di sini.
     */
    public function completeKyc(CompleteKycRequest $request, User $user): JsonResponse
    {
        $this->authorize('completeKyc', $user);

        $updated = $this->userService->completeKyc(
            $user,
            $request->validated('nik'),
            $request->file('ktp_photo'),
        );

        return response()->json([
            'id' => $updated->id,
            'name' => $updated->name,
            'phone' => $updated->phone,
            'has_nik' => true,
            'has_ktp_photo' => true,
        ]);
    }
}
