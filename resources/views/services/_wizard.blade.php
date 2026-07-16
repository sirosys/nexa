@php
    // Modal "Tambah Service" — wizard bertahap (bukan form satu halaman
    // seperti _form.blade.php yang tetap dipakai untuk /services/{id}/edit).
    // Lihat CLAUDE.md "Service" untuk alasan pemisahan dua partial ini.

    // Kalau redirect-back membawa error validasi, modal ini otomatis dibuka
    // lagi (lihat services/index.blade.php) — step awal & batas navigasi
    // dihitung supaya staff tidak perlu mengulang step yang sudah benar.
    $hasSubmittedBefore = $errors->any();

    $initialStep = 1;
    if ($errors->has('user_id')) {
        $initialStep = 1;
    } elseif ($errors->has('package_id')) {
        $initialStep = 2;
    } elseif ($errors->hasAny(['address', 'residential_name', 'subdistrict_id', 'rw', 'rt', 'coverage_id'])) {
        $initialStep = 3;
    }

    $initialMaxStep = $hasSubmittedBefore ? 4 : 1;

    $oldPackage = old('package_id') ? $packages->firstWhere('id', (int) old('package_id')) : null;
@endphp

<div
    x-data="{
        step: {{ $initialStep }},
        maxStepReached: {{ $initialMaxStep }},
        steps: [
            { n: 1, label: 'Pelanggan' },
            { n: 2, label: 'Paket' },
            { n: 3, label: 'Alamat & Lokasi' },
            { n: 4, label: 'Konfirmasi' },
        ],
        goToStep(n) {
            if (n <= this.maxStepReached) {
                this.step = n;
            }
        },
        canProceed(n) {
            if (n === 1) return this.customerId !== null;
            if (n === 2) return this.packageId !== null;
            if (n === 3) return this.address.trim() !== '' && this.subdistrictId !== null && this.coverageId !== null;
            return true;
        },
        next() {
            if (! this.canProceed(this.step)) return;
            this.step = Math.min(this.step + 1, 4);
            this.maxStepReached = Math.max(this.maxStepReached, this.step);
        },
        prev() {
            this.step = Math.max(this.step - 1, 1);
        },
        csrfToken() {
            return document.querySelector('meta[name=csrf-token]').getAttribute('content');
        },

        // Step 1 — Pelanggan. Logic identik services/_form.blade.php (search,
        // tambah pelanggan baru, gate lengkapi NIK & foto KTP), lihat CLAUDE.md
        // 'Service' untuk detail alur ini.
        customerQuery: {{ \Illuminate\Support\Js::from(old('customer_label', $oldCustomerLabel)) }},
        customerId: {{ \Illuminate\Support\Js::from(old('user_id')) }},
        customerResults: [],
        customerOpen: false,
        customerDebounce: null,
        fetchCustomers(q) {
            fetch('{{ route('services.customers.search') }}?q=' + encodeURIComponent(q))
                .then((res) => res.json())
                .then((data) => {
                    this.customerResults = data;
                    this.customerOpen = true;
                });
        },
        searchCustomers() {
            clearTimeout(this.customerDebounce);
            this.customerId = null;
            const length = this.customerQuery.trim().length;
            if (length > 0 && length < 3) {
                this.customerResults = [];
                this.customerOpen = false;
                return;
            }
            this.customerDebounce = setTimeout(() => this.fetchCustomers(this.customerQuery.trim()), 300);
        },
        openCustomerBrowse() {
            if (this.customerId) {
                return;
            }
            if (this.customerResults.length > 0) {
                this.customerOpen = true;
                return;
            }
            this.fetchCustomers('');
        },
        selectCustomer(item) {
            this.customerResults = [];
            this.customerOpen = false;

            if (item.has_nik && item.has_ktp_photo) {
                this.customerId = item.id;
                this.customerQuery = item.name + ' (' + item.phone + ')';
                return;
            }

            this.openKycModalFor(item);
        },
        clearCustomer() {
            this.customerId = null;
            this.customerQuery = '';
        },

        // Modal "Tambah Pelanggan Baru" — field-nya SAMA dengan form "Tambah
        // Pengguna" di /users (nama/telepon/email/NIK/foto KTP, lihat
        // CLAUDE.md "Service"), satu submission langsung lengkap. Multipart
        // (ada file upload) lewat FormData + fetch, pola sama submitKyc()
        // di bawah — bukan lagi JSON, supaya ktp_photo ikut terkirim.
        showAddCustomerModal: false,
        newCustomer: { name: '', phone: '', email: '', nik: '' },
        newCustomerPhotoFile: null,
        newCustomerErrors: {},
        newCustomerSubmitting: false,
        openAddCustomerModal() {
            this.newCustomer = { name: '', phone: '', email: '', nik: '' };
            this.newCustomerPhotoFile = null;
            this.newCustomerErrors = {};
            this.showAddCustomerModal = true;
        },
        submitNewCustomer() {
            this.newCustomerSubmitting = true;
            this.newCustomerErrors = {};
            const formData = new FormData();
            formData.append('name', this.newCustomer.name);
            formData.append('phone', this.newCustomer.phone);
            formData.append('email', this.newCustomer.email);
            formData.append('nik', this.newCustomer.nik);
            if (this.newCustomerPhotoFile) {
                formData.append('ktp_photo', this.newCustomerPhotoFile);
            }
            fetch('{{ route('services.customers.store') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                },
                body: formData,
            })
                .then(async (res) => {
                    const data = await res.json();
                    if (! res.ok) {
                        this.newCustomerErrors = data.errors || {};
                        return;
                    }
                    // Pelanggan baru sudah lengkap NIK & foto KTP sejak awal
                    // (tidak lagi disusul modal KYC terpisah) — langsung
                    // terpilih di step 1, nama langsung tercantum di form.
                    this.showAddCustomerModal = false;
                    this.customerId = data.id;
                    this.customerQuery = data.name + ' (' + data.phone + ')';
                })
                .finally(() => { this.newCustomerSubmitting = false; });
        },

        // Modal "Lengkapi NIK & Foto KTP" — sekarang HANYA untuk pelanggan
        // LAMA yang ketemu lewat pencarian tapi datanya belum lengkap
        // (dipicu dari selectCustomer() di atas). Pelanggan BARU dari modal
        // "Tambah Pelanggan Baru" tidak lagi lewat sini — NIK/foto KTP-nya
        // sudah diminta langsung di modal itu, lihat CLAUDE.md "Service".
        showKycModal: false,
        kycUser: { id: null, code: null, name: '' },
        kycNik: '',
        kycPhotoFile: null,
        kycErrors: {},
        kycSubmitting: false,
        openKycModalFor(item) {
            this.kycUser = { id: item.id, code: item.code, name: item.name };
            this.kycNik = '';
            this.kycPhotoFile = null;
            this.kycErrors = {};
            this.customerQuery = item.name + ' (' + item.phone + ')';
            this.showKycModal = true;
        },
        closeKycModal() {
            this.showKycModal = false;
        },
        submitKyc() {
            this.kycSubmitting = true;
            this.kycErrors = {};
            const formData = new FormData();
            formData.append('nik', this.kycNik);
            if (this.kycPhotoFile) {
                formData.append('ktp_photo', this.kycPhotoFile);
            }
            fetch('{{ route('users.complete-kyc', ['user' => '__USER_CODE__']) }}'.replace('__USER_CODE__', this.kycUser.code), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                },
                body: formData,
            })
                .then(async (res) => {
                    const data = await res.json();
                    if (! res.ok) {
                        this.kycErrors = data.errors || {};
                        return;
                    }
                    this.customerId = data.id;
                    this.customerQuery = data.name + ' (' + data.phone + ')';
                    this.closeKycModal();
                })
                .finally(() => { this.kycSubmitting = false; });
        },

        // Step 2 — Paket. Detail kartu diisi lewat data-* attribute saat
        // diklik (lihat selectPackage()), bukan lookup array JSON terpisah.
        packageId: {{ \Illuminate\Support\Js::from(old('package_id') ? (int) old('package_id') : null) }},
        packageName: {{ \Illuminate\Support\Js::from($oldPackage?->name ?? '') }},
        packagePrice: {{ \Illuminate\Support\Js::from($oldPackage ? \App\Support\Currency::rupiah($oldPackage->price) : '') }},
        packagePlan: {{ \Illuminate\Support\Js::from($oldPackage?->plan ? "{$oldPackage->plan->name} × {$oldPackage->plan_qty} bulan" : '') }},
        selectPackage(event) {
            const d = event.currentTarget.dataset;
            this.packageId = parseInt(d.id, 10);
            this.packageName = d.name;
            this.packagePrice = d.price;
            this.packagePlan = d.plan;
        },

        // Step 3 — Alamat & Lokasi.
        address: {{ \Illuminate\Support\Js::from(old('address', '')) }},
        residentialName: {{ \Illuminate\Support\Js::from(old('residential_name', '')) }},
        rw: {{ \Illuminate\Support\Js::from(old('rw', '')) }},
        rt: {{ \Illuminate\Support\Js::from(old('rt', '')) }},
        subdistrictQuery: {{ \Illuminate\Support\Js::from(old('subdistrict_label', $oldSubdistrictLabel)) }},
        subdistrictId: {{ \Illuminate\Support\Js::from(old('subdistrict_id') ? (int) old('subdistrict_id') : null) }},
        subdistrictResults: [],
        subdistrictOpen: false,
        subdistrictDebounce: null,
        searchSubdistricts() {
            clearTimeout(this.subdistrictDebounce);
            this.subdistrictId = null;
            if (this.subdistrictQuery.trim().length < 2) {
                this.subdistrictResults = [];
                this.subdistrictOpen = false;
                return;
            }
            this.subdistrictDebounce = setTimeout(() => {
                fetch('{{ route('subdistricts.search') }}?q=' + encodeURIComponent(this.subdistrictQuery))
                    .then((res) => res.json())
                    .then((data) => {
                        this.subdistrictResults = data;
                        this.subdistrictOpen = data.length > 0;
                    });
            }, 300);
        },
        selectSubdistrict(item) {
            this.subdistrictId = item.id;
            this.subdistrictQuery = item.name + ', ' + item.district_name + ', ' + item.city_name + ', ' + item.province_name;
            this.subdistrictResults = [];
            this.subdistrictOpen = false;
        },
        coverageId: {{ \Illuminate\Support\Js::from(old('coverage_id') ? (int) old('coverage_id') : null) }},
        coverageLabel: '',
    }"
>
    {{-- Nav stepper — pola 'links' Metronic direproduksi Tailwind: lingkaran
        bernomor + garis penghubung, bukan library JS stepper eksternal. --}}
    <div class="mb-8 flex items-center">
        <template x-for="(s, index) in steps" :key="s.n">
            <div class="flex items-center" :class="index < steps.length - 1 ? 'flex-1' : ''">
                <button
                    type="button"
                    @click="goToStep(s.n)"
                    class="flex shrink-0 flex-col items-center gap-2"
                    :class="s.n <= maxStepReached ? '' : 'cursor-not-allowed opacity-50'"
                    :disabled="s.n > maxStepReached"
                >
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold transition"
                        :class="step === s.n
                            ? 'border-primary bg-primary text-white'
                            : (step > s.n ? 'border-primary bg-primary-light text-primary dark:bg-primary/10' : 'border-gray-300 bg-white text-gray-400 dark:border-gray-600 dark:bg-gray-800')"
                    >
                        <x-icon x-show="step > s.n" name="check-circle" size="5" />
                        <span x-show="step <= s.n" x-text="s.n"></span>
                    </span>
                    <span
                        class="hidden text-xs font-semibold sm:block"
                        :class="step === s.n ? 'text-primary' : 'text-gray-500 dark:text-gray-400'"
                        x-text="s.label"
                    ></span>
                </button>
                <div
                    class="mx-2 h-0.5 flex-1"
                    :class="step > s.n ? 'bg-primary' : 'bg-gray-200 dark:bg-gray-700'"
                    x-show="index < steps.length - 1"
                ></div>
            </div>
        </template>
    </div>

    {{-- Step 1: Pelanggan --}}
    <div x-show="step === 1" x-cloak>
        <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-white">Pilih Pelanggan</h3>
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Cari pelanggan yang sudah terdaftar, atau tambahkan baru kalau belum ada.</p>

        <div class="relative">
            <label for="wizard_customer_query" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Pelanggan</label>
            <input
                type="text"
                id="wizard_customer_query"
                x-model="customerQuery"
                @input="searchCustomers()"
                @focus="openCustomerBrowse()"
                @click.outside="customerOpen = false"
                autocomplete="off"
                placeholder="Klik untuk lihat daftar, atau ketik nama/nomor telepon pelanggan..."
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            <input type="hidden" name="user_id" :value="customerId">

            <div
                x-show="customerOpen"
                x-cloak
                class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
            >
                <template x-for="item in customerResults" :key="item.id">
                    <button
                        type="button"
                        @click="selectCustomer(item)"
                        class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <span x-text="item.name + ' (' + item.phone + ')'"></span>
                        <span
                            x-show="! (item.has_nik && item.has_ktp_photo)"
                            class="shrink-0 rounded-full bg-warning-light px-2 py-0.5 text-xs font-medium text-warning dark:bg-warning/10"
                        >Belum lengkap</span>
                    </button>
                </template>
                <p x-show="customerResults.length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Pelanggan tidak ditemukan.</p>
            </div>
            <button
                type="button"
                @click="openAddCustomerModal()"
                class="mt-1.5 text-sm font-medium text-primary hover:underline"
            >+ Tambah pelanggan baru</button>
            @error('user_id')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div
            x-show="customerId"
            x-cloak
            class="mt-4 flex items-center justify-between rounded-xl border border-success/20 bg-success-light px-4 py-3 dark:border-success/30 dark:bg-success/10"
        >
            <div class="flex items-center gap-2 text-sm font-medium text-success">
                <x-icon name="check-circle" size="5" />
                <span x-text="customerQuery"></span>
            </div>
            <button type="button" @click="clearCustomer()" class="text-xs font-semibold text-success hover:underline">Ganti</button>
        </div>
    </div>

    {{-- Step 2: Paket --}}
    <div x-show="step === 2" x-cloak>
        <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-white">Pilih Paket</h3>
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Hanya paket yang tersedia untuk pendaftaran baru (starter) yang ditampilkan.</p>

        <input type="hidden" name="package_id" :value="packageId">

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @forelse ($packages as $package)
                <button
                    type="button"
                    @click="selectPackage($event)"
                    data-id="{{ $package->id }}"
                    data-name="{{ $package->name }}"
                    data-price="{{ \App\Support\Currency::rupiah($package->price) }}"
                    data-plan="{{ $package->plan ? "{$package->plan->name} × {$package->plan_qty} bulan" : '' }}"
                    class="flex flex-col rounded-xl border-2 p-4 text-left transition"
                    :class="packageId === {{ $package->id }} ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-gray-200 hover:border-primary/50 dark:border-gray-700'"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $package->name }}</span>
                        @if ($package->valid_until)
                            <span class="shrink-0 rounded-full bg-warning-light px-2 py-0.5 text-xs font-medium text-warning dark:bg-warning/10">Promo</span>
                        @endif
                    </div>
                    <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $package->code }}</span>
                    @if ($package->plan)
                        <span class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $package->plan->name }} &times; {{ $package->plan_qty }} bulan</span>
                    @endif
                    <span class="mt-3 text-base font-bold text-primary">{{ \App\Support\Currency::rupiah($package->price) }}</span>
                </button>
            @empty
                <p class="col-span-2 text-sm text-gray-500 dark:text-gray-400">Belum ada paket starter yang tersedia untuk pendaftaran baru.</p>
            @endforelse
        </div>
        @error('package_id')
            <p class="mt-3 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    {{-- Step 3: Alamat & Lokasi --}}
    <div x-show="step === 3" x-cloak class="space-y-4">
        <div>
            <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-white">Alamat & Lokasi</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Alamat pemasangan dan area coverage yang menjangkaunya.</p>
        </div>

        <div>
            <label for="wizard_address" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alamat</label>
            <textarea
                id="wizard_address"
                name="address"
                rows="2"
                x-model="address"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            ></textarea>
            @error('address')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="wizard_residential_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Perumahan/Komplek</label>
            <input
                type="text"
                id="wizard_residential_name"
                name="residential_name"
                x-model="residentialName"
                placeholder="Opsional"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            @error('residential_name')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="relative">
            <label for="wizard_subdistrict_query" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Wilayah (Kelurahan)</label>
            <input
                type="text"
                id="wizard_subdistrict_query"
                x-model="subdistrictQuery"
                @input="searchSubdistricts()"
                @focus="subdistrictOpen = subdistrictResults.length > 0"
                @click.outside="subdistrictOpen = false"
                autocomplete="off"
                placeholder="Ketik nama kelurahan..."
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            <input type="hidden" name="subdistrict_id" :value="subdistrictId">

            <div
                x-show="subdistrictOpen"
                x-cloak
                class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
            >
                <template x-for="item in subdistrictResults" :key="item.id">
                    <button
                        type="button"
                        @click="selectSubdistrict(item)"
                        class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                        x-text="item.name + ', ' + item.district_name + ', ' + item.city_name + ', ' + item.province_name"
                    ></button>
                </template>
            </div>
            @error('subdistrict_id')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="wizard_rw" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">RW</label>
                <input
                    type="text"
                    id="wizard_rw"
                    name="rw"
                    x-model="rw"
                    class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                >
                @error('rw')
                    <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="wizard_rt" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">RT</label>
                <input
                    type="text"
                    id="wizard_rt"
                    name="rt"
                    x-model="rt"
                    class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                >
                @error('rt')
                    <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="wizard_coverage_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Coverage</label>
            <select
                id="wizard_coverage_id"
                name="coverage_id"
                x-model="coverageId"
                @change="coverageLabel = $el.selectedOptions[0]?.text ?? ''"
                x-init="coverageLabel = $el.selectedOptions[0]?.text ?? ''"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
            >
                <option value="">Pilih coverage</option>
                @foreach ($coverages as $coverage)
                    <option value="{{ $coverage->id }}" @selected((int) old('coverage_id') === $coverage->id)>{{ $coverage->name }} ({{ $coverage->code }})</option>
                @endforeach
            </select>
            @error('coverage_id')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Step 4: Konfirmasi --}}
    <div x-show="step === 4" x-cloak>
        <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-white">Konfirmasi Pendaftaran</h3>
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Periksa kembali data sebelum menyimpan.</p>

        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Pelanggan</h4>
                    <button type="button" @click="step = 1" class="text-xs font-semibold text-primary hover:underline">Ubah</button>
                </div>
                <p class="mt-1 font-medium text-gray-900 dark:text-white" x-text="customerQuery || '—'"></p>
            </div>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Paket</h4>
                    <button type="button" @click="step = 2" class="text-xs font-semibold text-primary hover:underline">Ubah</button>
                </div>
                <p class="mt-1 font-medium text-gray-900 dark:text-white" x-text="packageName || '—'"></p>
                <p class="text-sm text-gray-500 dark:text-gray-400" x-show="packagePlan" x-text="packagePlan"></p>
                <p class="text-sm font-semibold text-primary" x-text="packagePrice"></p>
            </div>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Alamat & Lokasi</h4>
                    <button type="button" @click="step = 3" class="text-xs font-semibold text-primary hover:underline">Ubah</button>
                </div>
                <p class="mt-1 text-gray-900 dark:text-white" x-text="address || '—'"></p>
                <p class="text-sm text-gray-500 dark:text-gray-400" x-show="residentialName" x-text="residentialName"></p>
                <p class="text-sm text-gray-500 dark:text-gray-400" x-text="subdistrictQuery"></p>
                <p class="text-sm text-gray-500 dark:text-gray-400" x-show="rw || rt">
                    <span x-show="rw">RW <span x-text="rw"></span></span>
                    <span x-show="rt"> / RT <span x-text="rt"></span></span>
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400" x-text="coverageLabel"></p>
            </div>
        </div>
    </div>

    {{-- Navigasi step. 'Kembali' pakai class invisible (bukan x-show) di
        step 1 supaya lebar flex tetap konsisten dan tombol kanan tidak
        ikut bergeser ke kiri. --}}
    <div class="mt-8 flex items-center justify-between border-t border-gray-200 pt-6 dark:border-gray-700">
        <button
            type="button"
            @click="prev()"
            :class="step === 1 ? 'invisible' : ''"
            class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
        >Kembali</button>

        <div class="flex items-center gap-2">
            <button
                type="button"
                x-show="step < 4"
                x-cloak
                @click="next()"
                :disabled="! canProceed(step)"
                class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
            >Lanjutkan</button>
            <button
                type="submit"
                x-show="step === 4"
                x-cloak
                class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]"
            >Simpan Service</button>
        </div>
    </div>

    {{-- Modal "Tambah Pelanggan Baru" — z-50 supaya tampil di atas modal
        wizard (z-40), lihat CLAUDE.md "Service". --}}
    <div
        x-show="showAddCustomerModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
        @keydown.escape.window="showAddCustomerModal = false"
    >
        <div
            x-show="showAddCustomerModal"
            @click.outside="showAddCustomerModal = false"
            class="my-8 w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800"
            style="max-height: calc(100vh - 4rem)"
        >
            <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-white">Tambah Pelanggan Baru</h3>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Data harus lengkap — sama seperti form "Tambah Pengguna" di halaman Pengguna.</p>

            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
                    <input
                        type="text"
                        x-model="newCustomer.name"
                        class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                    >
                    <p x-show="newCustomerErrors.name" x-text="newCustomerErrors.name?.[0]" class="mt-1.5 text-sm text-danger"></p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nomor Telepon</label>
                    <div class="flex items-center overflow-hidden rounded-lg border border-gray-300 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary dark:border-gray-600">
                        <span class="border-r border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">+62</span>
                        <input
                            type="tel"
                            x-model="newCustomer.phone"
                            inputmode="numeric"
                            placeholder="81234567890"
                            class="block w-full border-0 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-500"
                        >
                    </div>
                    <p x-show="newCustomerErrors.phone" x-text="newCustomerErrors.phone?.[0]" class="mt-1.5 text-sm text-danger"></p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                    <input
                        type="email"
                        x-model="newCustomer.email"
                        placeholder="nama@contoh.com"
                        class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                    >
                    <p x-show="newCustomerErrors.email" x-text="newCustomerErrors.email?.[0]" class="mt-1.5 text-sm text-danger"></p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">NIK</label>
                    <input
                        type="text"
                        x-model="newCustomer.nik"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="16"
                        class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                    >
                    <p x-show="newCustomerErrors.nik" x-text="newCustomerErrors.nik?.[0]" class="mt-1.5 text-sm text-danger"></p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Foto KTP</label>
                    <input
                        type="file"
                        accept="image/*"
                        @change="newCustomerPhotoFile = $event.target.files[0]"
                        class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-light file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary dark:text-gray-300"
                    >
                    <p x-show="newCustomerErrors.ktp_photo" x-text="newCustomerErrors.ktp_photo?.[0]" class="mt-1.5 text-sm text-danger"></p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="showAddCustomerModal = false" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Batal</button>
                <button
                    type="button"
                    @click="submitNewCustomer()"
                    :disabled="newCustomerSubmitting"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] disabled:opacity-60"
                >
                    <span x-show="! newCustomerSubmitting">Simpan Pelanggan</span>
                    <span x-show="newCustomerSubmitting">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Modal "Lengkapi NIK & Foto KTP" — z-50, sama alasan di atas. --}}
    <div
        x-show="showKycModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
        @keydown.escape.window="closeKycModal()"
    >
        <div
            x-show="showKycModal"
            @click.outside="closeKycModal()"
            class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800"
        >
            <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-white">Lengkapi NIK &amp; Foto KTP</h3>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400" x-text="kycUser.name"></p>

            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">NIK</label>
                    <input
                        type="text"
                        x-model="kycNik"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="16"
                        class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                    >
                    <p x-show="kycErrors.nik" x-text="kycErrors.nik?.[0]" class="mt-1.5 text-sm text-danger"></p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Foto KTP</label>
                    <input
                        type="file"
                        accept="image/*"
                        @change="kycPhotoFile = $event.target.files[0]"
                        class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-light file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary dark:text-gray-300"
                    >
                    <p x-show="kycErrors.ktp_photo" x-text="kycErrors.ktp_photo?.[0]" class="mt-1.5 text-sm text-danger"></p>
                </div>
            </div>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">NIK &amp; foto KTP wajib diisi sebelum pelanggan ini bisa didaftarkan layanan baru.</p>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="closeKycModal()" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Batal</button>
                <button
                    type="button"
                    @click="submitKyc()"
                    :disabled="kycSubmitting"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] disabled:opacity-60"
                >
                    <span x-show="! kycSubmitting">Simpan</span>
                    <span x-show="kycSubmitting">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>
</div>
