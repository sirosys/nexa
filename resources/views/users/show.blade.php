@php
    use App\Models\Service;
    use App\Models\ServiceTicket;
    use App\Support\Currency;
    use App\Support\SaleStatus;

    $roleBadges = [
        'superadmin' => ['label' => 'Superadmin', 'class' => 'bg-danger-light text-danger dark:bg-danger/10', 'avatar' => 'bg-danger'],
        'technician' => ['label' => 'Teknisi', 'class' => 'bg-warning-light text-warning dark:bg-warning/10', 'avatar' => 'bg-warning'],
        'finance' => ['label' => 'Admin/NOC', 'class' => 'bg-success-light text-success dark:bg-success/10', 'avatar' => 'bg-success'],
        'customer' => ['label' => 'Pelanggan', 'class' => 'bg-primary-light text-primary dark:bg-primary/10', 'avatar' => 'bg-primary'],
    ];
    $role = $user->getRoleNames()->first();
    $userDetails = $user->userDetails;

    $serviceStatusBadges = [
        Service::STATUS_PENDING_PAYMENT => 'bg-warning-light text-warning dark:bg-warning/10',
        Service::STATUS_PENDING_INSTALLATION => 'bg-info-light text-info dark:bg-info/10',
        Service::STATUS_INSTALLING => 'bg-info-light text-info dark:bg-info/10',
        Service::STATUS_ACTIVE => 'bg-success-light text-success dark:bg-success/10',
        Service::STATUS_SUSPENDED => 'bg-danger-light text-danger dark:bg-danger/10',
        Service::STATUS_CANCELED => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
        Service::STATUS_PENDING_DISMANTLE => 'bg-info-light text-info dark:bg-info/10',
        Service::STATUS_DISMANTLING => 'bg-info-light text-info dark:bg-info/10',
        Service::STATUS_DISMANTLED => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
    ];

    $ticketStatusBadges = [
        ServiceTicket::STATUS_OPEN => 'bg-warning-light text-warning dark:bg-warning/10',
        ServiceTicket::STATUS_IN_PROGRESS => 'bg-info-light text-info dark:bg-info/10',
        ServiceTicket::STATUS_RESOLVED => 'bg-success-light text-success dark:bg-success/10',
    ];

    // Status pembayaran Sale diturunkan dari kombinasi timestamp (tidak ada
    // kolom status eksplisit di tabel `sales`, lihat CLAUDE.md "Sales") —
    // logic-nya di App\Support\SaleStatus (reuse yang sama dipakai API
    // customer-facing, lihat CLAUDE.md "API Customer-Facing").
    $saleStatus = fn ($sale) => SaleStatus::resolve($sale);

    // Tab data-role yang relevan cuma ditentukan sekali di sini — null
    // berarti role ini tidak punya data "miliknya sendiri" untuk
    // ditampilkan (superadmin/finance), jadi cuma tab "Detail Akun".
    $tabSet = match (true) {
        $services !== null => 'customer',
        $installations !== null => 'technician',
        default => null,
    };
@endphp

<x-app-layout :title="'Detail Pengguna — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="arrow-left" size="4" />Kembali ke Pengguna</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div x-data="{ tab: 'account' }">
        {{-- Kartu profil lebar, meniru account/overview.html Metronic:
            avatar besar + info kontak + aksi di kanan-atas, trio statistik,
            lalu tab nav underline di bagian bawah kartu yang sama. --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
            <div class="p-6 sm:p-8">
                <div class="flex flex-wrap items-start gap-6">
                    <span class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full {{ $roleBadges[$role]['avatar'] ?? 'bg-gray-400' }} text-3xl font-bold text-white sm:h-28 sm:w-28">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $user->name }}</h1>
                                    @if ($role)
                                        {{-- Role custom (dibuat lewat /roles) tidak ada di $roleBadges —
                                            tetap tampilkan badge netral abu-abu + label Str::headline(). --}}
                                        <span class="inline-flex items-center rounded-full {{ $roleBadges[$role]['class'] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }} px-3 py-1 text-[13px] font-semibold">
                                            {{ $roleBadges[$role]['label'] ?? \Illuminate\Support\Str::headline($role) }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="inline-flex items-center gap-1.5"><x-icon name="identification" size="4" />{{ $user->code ?? '—' }}</span>
                                    <span class="inline-flex items-center gap-1.5"><x-icon name="phone" size="4" />{{ $user->phone }}</span>
                                    <span class="inline-flex items-center gap-1.5"><x-icon name="envelope" size="4" />{{ $user->email }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <a
                                    href="{{ route('users.edit', $user) }}"
                                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
                                >
                                    <x-icon name="pencil-square" size="4" />
                                    Ubah
                                </a>

                                <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus pengguna ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="rounded-xl border border-danger/30 px-5 py-2.5 text-sm font-semibold text-danger shadow-sm transition hover:bg-danger-light hover:shadow-md dark:hover:bg-danger/10 inline-flex items-center gap-2"
                                    >
                                        <x-icon name="trash" size="4" />
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if ($tabSet === 'customer')
                            <div class="mt-5 flex flex-wrap gap-3">
                                <div class="min-w-32 rounded-lg border border-dashed border-gray-300 px-4 py-3 dark:border-gray-600">
                                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $services->whereIn('status', [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED])->count() }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Layanan Aktif</p>
                                </div>
                                <div class="min-w-32 rounded-lg border border-dashed border-gray-300 px-4 py-3 dark:border-gray-600">
                                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $sales->filter(fn ($sale) => $sale->invoiced_at && ! $sale->settled_at && ! $sale->canceled_at)->count() }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tagihan Belum Lunas</p>
                                </div>
                                <div class="min-w-32 rounded-lg border border-dashed border-gray-300 px-4 py-3 dark:border-gray-600">
                                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $tickets->where('status', '!=', ServiceTicket::STATUS_RESOLVED)->count() }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tiket Terbuka</p>
                                </div>
                            </div>
                        @elseif ($tabSet === 'technician')
                            <div class="mt-5 flex flex-wrap gap-3">
                                <div class="min-w-32 rounded-lg border border-dashed border-gray-300 px-4 py-3 dark:border-gray-600">
                                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $installations->whereNotNull('completed_at')->count() }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Instalasi Selesai</p>
                                </div>
                                <div class="min-w-32 rounded-lg border border-dashed border-gray-300 px-4 py-3 dark:border-gray-600">
                                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $dismantles->whereNotNull('completed_at')->count() }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Dismantle Selesai</p>
                                </div>
                                <div class="min-w-32 rounded-lg border border-dashed border-gray-300 px-4 py-3 dark:border-gray-600">
                                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $assignedTickets->where('status', '!=', ServiceTicket::STATUS_RESOLVED)->count() }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tiket Ditangani</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Tab nav underline, ala nav-line-tabs Metronic. --}}
                <div class="mt-6 flex gap-6 overflow-x-auto border-b border-gray-200 dark:border-gray-700">
                    <button @click="tab = 'account'" type="button" class="shrink-0 border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'account' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                        Detail Akun
                    </button>

                    @if ($tabSet === 'customer')
                        <button @click="tab = 'services'" type="button" class="shrink-0 border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'services' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Layanan
                            <span class="ms-1 text-xs text-gray-400">({{ $services->count() }})</span>
                        </button>
                        <button @click="tab = 'billing'" type="button" class="shrink-0 border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'billing' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Tagihan &amp; Pembayaran
                            <span class="ms-1 text-xs text-gray-400">({{ $sales->count() }})</span>
                        </button>
                        <button @click="tab = 'tickets'" type="button" class="shrink-0 border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'tickets' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Tiket
                            <span class="ms-1 text-xs text-gray-400">({{ $tickets->count() }})</span>
                        </button>
                    @elseif ($tabSet === 'technician')
                        <button @click="tab = 'installations'" type="button" class="shrink-0 border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'installations' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Instalasi
                            <span class="ms-1 text-xs text-gray-400">({{ $installations->count() }})</span>
                        </button>
                        <button @click="tab = 'dismantles'" type="button" class="shrink-0 border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'dismantles' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Dismantle
                            <span class="ms-1 text-xs text-gray-400">({{ $dismantles->count() }})</span>
                        </button>
                        <button @click="tab = 'assignedTickets'" type="button" class="shrink-0 border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'assignedTickets' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Tiket Ditangani
                            <span class="ms-1 text-xs text-gray-400">({{ $assignedTickets->count() }})</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Konten tab. --}}
        <div class="mt-6">
            {{-- Detail Akun --}}
            <div x-show="tab === 'account'" class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Detail Akun</h3>
                </div>
                <dl>
                    <x-detail-row label="Kode">{{ $user->code ?? '—' }}</x-detail-row>
                    <x-detail-row label="Email">{{ $user->email }}</x-detail-row>
                    <x-detail-row label="Telepon">{{ $user->phone }}</x-detail-row>
                    <x-detail-row label="NIK">{{ $userDetails?->nik ?? '—' }}</x-detail-row>
                    <x-detail-row label="Jenis Kelamin">
                        @if ($userDetails?->gender)
                            {{ $userDetails->gender === 'female' ? 'Perempuan' : 'Laki-laki' }}
                        @else
                            —
                        @endif
                    </x-detail-row>
                    <x-detail-row label="Tanggal Lahir">{{ $userDetails?->birth_date?->locale('id')->translatedFormat('d F Y') ?? '—' }}</x-detail-row>
                    @if ($userDetails?->ktp_photo)
                        <x-detail-row label="Foto KTP">
                            <img src="{{ route('secure.ktp', $user) }}" alt="Foto KTP" class="h-28 w-auto rounded-lg border border-gray-300 object-cover dark:border-gray-600">
                        </x-detail-row>
                    @endif
                    <x-detail-row label="Login Terakhir">{{ $user->last_login_at?->locale('id')->translatedFormat('d F Y, H:i') ?? 'Belum pernah login' }}</x-detail-row>
                    <x-detail-row label="Terdaftar Sejak">{{ $user->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
                </dl>
            </div>

            @if ($tabSet === 'customer')
                {{-- Layanan --}}
                <div x-show="tab === 'services'" style="display: none;" class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                    @if ($services->isEmpty())
                        <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Belum ada layanan yang terdaftar atas nama pelanggan ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">Kode</th>
                                        <th class="px-4 py-3">Paket</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Berlaku Sampai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($services as $service)
                                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                            <td class="px-6 py-3">
                                                <a href="{{ route('services.show', $service) }}" class="font-semibold text-primary hover:underline">{{ $service->code }}</a>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $service->package?->name ?? '—' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center rounded-full {{ $serviceStatusBadges[$service->status] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }} px-3 py-1 text-[13px] font-semibold">{{ Service::STATUS_LABELS[$service->status] ?? $service->status }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $service->expired_at?->locale('id')->translatedFormat('d M Y') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Tagihan & Pembayaran --}}
                <div x-show="tab === 'billing'" style="display: none;" class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                    @if ($sales->isEmpty())
                        <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Belum ada tagihan untuk pelanggan ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">Kode</th>
                                        <th class="px-4 py-3">Total</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Tanggal Tagihan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($sales as $sale)
                                        @php($status = $saleStatus($sale))
                                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                            <td class="px-6 py-3">
                                                <a href="{{ route('sales.show', $sale) }}" class="font-semibold text-primary hover:underline">{{ $sale->code ?? '—' }}</a>
                                                @if ($sale->is_renewal)
                                                    <span class="ms-1 inline-flex items-center rounded-full bg-info-light px-2 py-0.5 text-[11px] font-semibold text-info dark:bg-info/10">Perpanjangan</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ Currency::rupiah($sale->grandtotal) }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center rounded-full {{ $status['class'] }} px-3 py-1 text-[13px] font-semibold">{{ $status['label'] }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $sale->invoiced_at?->locale('id')->translatedFormat('d M Y') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Tiket --}}
                <div x-show="tab === 'tickets'" style="display: none;" class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                    @if ($tickets->isEmpty())
                        <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Belum ada tiket keluhan/permintaan dari pelanggan ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">Kode</th>
                                        <th class="px-4 py-3">Subjek</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($tickets as $ticket)
                                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                            <td class="px-6 py-3">
                                                <a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-primary hover:underline">{{ $ticket->code }}</a>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $ticket->subject }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center rounded-full {{ $ticketStatusBadges[$ticket->status] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }} px-3 py-1 text-[13px] font-semibold">{{ ServiceTicket::STATUS_LABELS[$ticket->status] ?? $ticket->status }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $ticket->created_at?->locale('id')->translatedFormat('d M Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @elseif ($tabSet === 'technician')
                {{-- Instalasi --}}
                <div x-show="tab === 'installations'" style="display: none;" class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                    @if ($installations->isEmpty())
                        <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Belum ada job instalasi yang ditangani teknisi ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">Layanan</th>
                                        <th class="px-4 py-3">Alamat</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Selesai Pada</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($installations as $activation)
                                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                            <td class="px-6 py-3">
                                                <a href="{{ route('installations.show', $activation->service) }}" class="font-semibold text-primary hover:underline">{{ $activation->service->code }}</a>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $activation->service->address }}</td>
                                            <td class="px-4 py-3">
                                                @if ($activation->completed_at)
                                                    <span class="inline-flex items-center rounded-full bg-success-light px-3 py-1 text-[13px] font-semibold text-success dark:bg-success/10">Selesai</span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-info-light px-3 py-1 text-[13px] font-semibold text-info dark:bg-info/10">Proses</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $activation->completed_at?->locale('id')->translatedFormat('d M Y') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Dismantle --}}
                <div x-show="tab === 'dismantles'" style="display: none;" class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                    @if ($dismantles->isEmpty())
                        <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Belum ada job dismantle yang ditangani teknisi ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">Layanan</th>
                                        <th class="px-4 py-3">Alamat</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Selesai Pada</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($dismantles as $dismantle)
                                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                            <td class="px-6 py-3">
                                                <a href="{{ route('dismantles.show', $dismantle->service) }}" class="font-semibold text-primary hover:underline">{{ $dismantle->service->code }}</a>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $dismantle->service->address }}</td>
                                            <td class="px-4 py-3">
                                                @if ($dismantle->completed_at)
                                                    <span class="inline-flex items-center rounded-full bg-success-light px-3 py-1 text-[13px] font-semibold text-success dark:bg-success/10">Selesai</span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-info-light px-3 py-1 text-[13px] font-semibold text-info dark:bg-info/10">Proses</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $dismantle->completed_at?->locale('id')->translatedFormat('d M Y') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Tiket Ditangani --}}
                <div x-show="tab === 'assignedTickets'" style="display: none;" class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                    @if ($assignedTickets->isEmpty())
                        <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Belum ada tiket teknis yang ditugaskan ke teknisi ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">Kode</th>
                                        <th class="px-4 py-3">Subjek</th>
                                        <th class="px-4 py-3">Layanan</th>
                                        <th class="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($assignedTickets as $ticket)
                                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                            <td class="px-6 py-3">
                                                <a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-primary hover:underline">{{ $ticket->code }}</a>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $ticket->subject }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $ticket->service?->code ?? '—' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center rounded-full {{ $ticketStatusBadges[$ticket->status] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }} px-3 py-1 text-[13px] font-semibold">{{ ServiceTicket::STATUS_LABELS[$ticket->status] ?? $ticket->status }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
