@php
    use App\Models\Service;
    use App\Models\ServiceTicket;
    use App\Support\Currency;

    $roleBadges = [
        'superadmin' => ['label' => 'Superadmin', 'class' => 'bg-danger-light text-danger dark:bg-danger/10', 'avatar' => 'bg-danger'],
        'technician' => ['label' => 'Teknisi', 'class' => 'bg-warning-light text-warning dark:bg-warning/10', 'avatar' => 'bg-warning'],
        'finance' => ['label' => 'Finance', 'class' => 'bg-success-light text-success dark:bg-success/10', 'avatar' => 'bg-success'],
        'sales' => ['label' => 'Sales', 'class' => 'bg-info-light text-info dark:bg-info/10', 'avatar' => 'bg-info'],
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
    // kolom status eksplisit di tabel `sales`, lihat CLAUDE.md "Sales").
    $saleStatus = function ($sale) {
        return match (true) {
            $sale->canceled_at !== null => ['label' => 'Dibatalkan', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
            $sale->settled_at !== null => ['label' => 'Lunas', 'class' => 'bg-success-light text-success dark:bg-success/10'],
            $sale->invoiced_at !== null => ['label' => 'Menunggu Pembayaran', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
            default => ['label' => 'Draft', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
        };
    };

    // Tab yang relevan cuma ditentukan sekali di sini, dipakai baik untuk
    // navigasi maupun x-data awal Alpine — null berarti role ini tidak
    // punya data "miliknya sendiri" untuk ditampilkan (superadmin/finance/sales).
    $tabSet = match (true) {
        $services !== null => 'customer',
        $installations !== null => 'technician',
        default => null,
    };
@endphp

<x-app-layout :title="'Detail Pengguna — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="arrow-left" size="4" />Kembali ke Pengguna</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $user->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $user->code ?? '—' }}</p>
        </div>

        <a
            href="{{ route('users.edit', $user) }}"
            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
        >
            <x-icon name="pencil-square" size="4" />
            Ubah
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        {{-- Kolom kiri: kartu ringkasan --}}
        <div class="w-full shrink-0 lg:w-80">
            <div class="rounded-2xl border border-gray-300 bg-white p-6 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                <div class="flex flex-col items-center text-center">
                    <span class="flex h-20 w-20 items-center justify-center rounded-full {{ $roleBadges[$role]['avatar'] ?? 'bg-gray-400' }} text-2xl font-bold text-white">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </span>
                    <p class="mt-4 text-lg font-bold text-gray-900 dark:text-white">{{ $user->name }}</p>
                    @if ($role && isset($roleBadges[$role]))
                        <span class="mt-2 inline-flex items-center rounded-full {{ $roleBadges[$role]['class'] }} px-3 py-1 text-[13px] font-semibold">{{ $roleBadges[$role]['label'] }}</span>
                    @endif
                </div>

                @if ($tabSet === 'customer')
                    <div class="mt-6 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg border border-dashed border-gray-300 px-2 py-3 dark:border-gray-600">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $services->whereIn('status', [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED])->count() }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Layanan Aktif</p>
                        </div>
                        <div class="rounded-lg border border-dashed border-gray-300 px-2 py-3 dark:border-gray-600">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $sales->filter(fn ($sale) => $sale->invoiced_at && ! $sale->settled_at && ! $sale->canceled_at)->count() }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tagihan Belum Lunas</p>
                        </div>
                        <div class="rounded-lg border border-dashed border-gray-300 px-2 py-3 dark:border-gray-600">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $tickets->where('status', '!=', ServiceTicket::STATUS_RESOLVED)->count() }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tiket Terbuka</p>
                        </div>
                    </div>
                @elseif ($tabSet === 'technician')
                    <div class="mt-6 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg border border-dashed border-gray-300 px-2 py-3 dark:border-gray-600">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $installations->whereNotNull('completed_at')->count() }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Instalasi Selesai</p>
                        </div>
                        <div class="rounded-lg border border-dashed border-gray-300 px-2 py-3 dark:border-gray-600">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $dismantles->whereNotNull('completed_at')->count() }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Dismantle Selesai</p>
                        </div>
                        <div class="rounded-lg border border-dashed border-gray-300 px-2 py-3 dark:border-gray-600">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $assignedTickets->where('status', '!=', ServiceTicket::STATUS_RESOLVED)->count() }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tiket Ditangani</p>
                        </div>
                    </div>
                @endif

                <div x-data="{ open: true }" class="mt-6">
                    <button @click="open = !open" type="button" class="flex w-full items-center justify-between border-t border-gray-100 pt-4 text-sm font-bold text-gray-900 dark:border-gray-700 dark:text-white">
                        Detail Akun
                        <x-icon name="chevron-down" size="4" class="transition" x-bind:class="open ? 'rotate-180' : ''" />
                    </button>
                    <dl x-show="open" x-transition class="mt-3 space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Kode</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">{{ $user->code ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Email</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">{{ $user->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Telepon</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">{{ $user->phone }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">NIK</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">{{ $userDetails?->nik ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Jenis Kelamin</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">
                                @if ($userDetails?->gender)
                                    {{ $userDetails->gender === 'female' ? 'Perempuan' : 'Laki-laki' }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Tanggal Lahir</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">{{ $userDetails?->birth_date?->locale('id')->translatedFormat('d F Y') ?? '—' }}</dd>
                        </div>
                        @if ($userDetails?->ktp_photo)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Foto KTP</dt>
                                <dd class="mt-1">
                                    <img src="{{ route('secure.ktp', $user) }}" alt="Foto KTP" class="h-24 rounded-lg border border-gray-300 object-cover dark:border-gray-600">
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Login Terakhir</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">{{ $user->last_login_at?->locale('id')->translatedFormat('d F Y, H:i') ?? 'Belum pernah login' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Terdaftar Sejak</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-white">{{ $user->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Kolom kanan: informasi terkait (berbeda per role) --}}
        <div class="min-w-0 flex-1">
            @if ($tabSet === 'customer')
                <div x-data="{ tab: 'services' }">
                    <div class="mb-4 flex gap-6 border-b border-gray-200 dark:border-gray-700">
                        <button @click="tab = 'services'" type="button" class="border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'services' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Layanan
                            <span class="ms-1 text-xs text-gray-400">({{ $services->count() }})</span>
                        </button>
                        <button @click="tab = 'billing'" type="button" class="border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'billing' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Tagihan &amp; Pembayaran
                            <span class="ms-1 text-xs text-gray-400">({{ $sales->count() }})</span>
                        </button>
                        <button @click="tab = 'tickets'" type="button" class="border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'tickets' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Tiket
                            <span class="ms-1 text-xs text-gray-400">({{ $tickets->count() }})</span>
                        </button>
                    </div>

                    {{-- Layanan --}}
                    <div x-show="tab === 'services'" class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                        @if ($services->isEmpty())
                            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Belum ada layanan yang terdaftar atas nama pelanggan ini.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th class="px-6 py-3">Kode</th>
                                            <th class="px-4 py-3">Alamat</th>
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
                                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $service->address }}</td>
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
                    <div x-show="tab === 'billing'" style="display: none;" class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                        @if ($sales->isEmpty())
                            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Belum ada tagihan untuk pelanggan ini.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th class="px-6 py-3">Kode</th>
                                            <th class="px-4 py-3">Paket</th>
                                            <th class="px-4 py-3">Total</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3">Tanggal Tagihan</th>
                                            <th class="px-4 py-3">Channel Bayar</th>
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
                                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $sale->package?->name ?? '—' }}</td>
                                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ Currency::rupiah($sale->grandtotal) }}</td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center rounded-full {{ $status['class'] }} px-3 py-1 text-[13px] font-semibold">{{ $status['label'] }}</span>
                                                </td>
                                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $sale->invoiced_at?->locale('id')->translatedFormat('d M Y') ?? '—' }}</td>
                                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $sale->receipt?->channel_code ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- Tiket --}}
                    <div x-show="tab === 'tickets'" style="display: none;" class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                        @if ($tickets->isEmpty())
                            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Belum ada tiket keluhan/permintaan dari pelanggan ini.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th class="px-6 py-3">Kode</th>
                                            <th class="px-4 py-3">Subjek</th>
                                            <th class="px-4 py-3">Kategori</th>
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
                                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ ServiceTicket::CATEGORY_LABELS[$ticket->category] ?? $ticket->category }}</td>
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
                </div>
            @elseif ($tabSet === 'technician')
                <div x-data="{ tab: 'installations' }">
                    <div class="mb-4 flex gap-6 border-b border-gray-200 dark:border-gray-700">
                        <button @click="tab = 'installations'" type="button" class="border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'installations' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Instalasi
                            <span class="ms-1 text-xs text-gray-400">({{ $installations->count() }})</span>
                        </button>
                        <button @click="tab = 'dismantles'" type="button" class="border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'dismantles' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Dismantle
                            <span class="ms-1 text-xs text-gray-400">({{ $dismantles->count() }})</span>
                        </button>
                        <button @click="tab = 'tickets'" type="button" class="border-b-2 pb-3 text-sm font-semibold transition" :class="tab === 'tickets' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'">
                            Tiket Ditangani
                            <span class="ms-1 text-xs text-gray-400">({{ $assignedTickets->count() }})</span>
                        </button>
                    </div>

                    {{-- Instalasi --}}
                    <div x-show="tab === 'installations'" class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
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
                    <div x-show="tab === 'dismantles'" style="display: none;" class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
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
                    <div x-show="tab === 'tickets'" style="display: none;" class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
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
                </div>
            @else
                <div class="flex h-full items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-sm dark:border-gray-600 dark:bg-gray-800">
                    <div>
                        <x-icon name="information-circle" size="8" class="mx-auto text-gray-300 dark:text-gray-600" />
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Tidak ada informasi tambahan (layanan/tagihan/tiket) untuk role ini.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
