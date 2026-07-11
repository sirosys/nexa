@php
    $service ??= null;

    $selectedCustomerLabel = $service?->user
        ? "{$service->user->name} ({$service->user->phone})"
        : '';

    $selectedSubdistrictLabel = $service?->subdistrict
        ? "{$service->subdistrict->name}, {$service->subdistrict->district_name}, {$service->subdistrict->city_name}, {$service->subdistrict->province_name}"
        : '';
@endphp

<div
    class="space-y-4"
    x-data="{
        customerQuery: {{ \Illuminate\Support\Js::from(old('customer_label', $selectedCustomerLabel)) }},
        customerId: {{ \Illuminate\Support\Js::from(old('user_id', $service?->user_id)) }},
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
            // Sudah ada pelanggan terpilih — jangan timpa dengan daftar
            // browse, biarkan user mengetik ulang dulu (yang me-reset
            // customerId lewat searchCustomers()) baru browse aktif lagi.
            if (this.customerId) {
                return;
            }
            if (this.customerResults.length > 0) {
                this.customerOpen = true;
                return;
            }
            this.fetchCustomers('');
        },
        // Pelanggan yang belum lengkap NIK/foto KTP tidak langsung
        // terpilih — staff digerbang lewat modal 'Lengkapi NIK & Foto
        // KTP' dulu (wajib, lihat CLAUDE.md Service) sebelum customerId
        // benar-benar terisi dan form bisa disubmit.
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
        csrfToken() {
            return document.querySelector('meta[name=csrf-token]').getAttribute('content');
        },

        // Modal 'Tambah Pelanggan Baru' — dipakai kalau pelanggan yang
        // dicari tidak ditemukan di typeahead. Cuma kumpulkan name/phone/
        // email (role selalu customer); begitu berhasil dibuat, langsung
        // disusul modal 'Lengkapi NIK & Foto KTP' lewat openKycModalFor(),
        // supaya tidak ada duplikasi form NIK/KTP.
        showAddCustomerModal: false,
        newCustomer: { name: '', phone: '', email: '' },
        newCustomerErrors: {},
        newCustomerSubmitting: false,
        openAddCustomerModal() {
            this.newCustomer = { name: '', phone: '', email: '' };
            this.newCustomerErrors = {};
            this.showAddCustomerModal = true;
        },
        submitNewCustomer() {
            this.newCustomerSubmitting = true;
            this.newCustomerErrors = {};
            fetch('{{ route('services.customers.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                },
                body: JSON.stringify(this.newCustomer),
            })
                .then(async (res) => {
                    const data = await res.json();
                    if (! res.ok) {
                        this.newCustomerErrors = data.errors || {};
                        return;
                    }
                    this.showAddCustomerModal = false;
                    this.openKycModalFor(data);
                })
                .finally(() => { this.newCustomerSubmitting = false; });
        },

        // Modal 'Lengkapi NIK & Foto KTP' — gate wajib sebelum Service baru
        // bisa didaftarkan (lihat CLAUDE.md 'Service'). Dipakai baik untuk
        // pelanggan lama yang belum lengkap maupun pelanggan baru dari
        // modal di atas.
        showKycModal: false,
        kycUser: { id: null, name: '' },
        kycNik: '',
        kycPhotoFile: null,
        kycErrors: {},
        kycSubmitting: false,
        openKycModalFor(item) {
            this.kycUser = { id: item.id, name: item.name };
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
            fetch('{{ route('users.complete-kyc', ['user' => '__USER_ID__']) }}'.replace('__USER_ID__', this.kycUser.id), {
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
        subdistrictQuery: {{ \Illuminate\Support\Js::from(old('subdistrict_label', $selectedSubdistrictLabel)) }},
        subdistrictId: {{ \Illuminate\Support\Js::from(old('subdistrict_id', $service?->subdistrict_id)) }},
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
    }"
>
    <div class="relative">
        <label for="customer_query" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Pelanggan</label>
        <input
            type="text"
            id="customer_query"
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

    <div>
        <label for="package_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Paket</label>
        <select
            id="package_id"
            name="package_id"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
        >
            <option value="">Pilih paket</option>
            @foreach ($packages as $package)
                <option value="{{ $package->id }}" @selected((int) old('package_id', $service?->package_id) === $package->id)>{{ $package->name }} ({{ $package->code }})</option>
            @endforeach
        </select>
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Hanya paket yang bisa dipilih saat pendaftaran baru (starter) yang muncul di sini.</p>
        @error('package_id')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="address" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alamat</label>
        <textarea
            id="address"
            name="address"
            rows="2"
            required
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >{{ old('address', $service?->address) }}</textarea>
        @error('address')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="residential_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Perumahan/Komplek</label>
        <input
            type="text"
            id="residential_name"
            name="residential_name"
            placeholder="Opsional"
            value="{{ old('residential_name', $service?->residential_name) }}"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        @error('residential_name')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div class="relative">
        <label for="subdistrict_query" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Wilayah (Kelurahan)</label>
        <input
            type="text"
            id="subdistrict_query"
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
            <label for="rw" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">RW</label>
            <input
                type="text"
                id="rw"
                name="rw"
                value="{{ old('rw', $service?->rw) }}"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            @error('rw')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="rt" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">RT</label>
            <input
                type="text"
                id="rt"
                name="rt"
                value="{{ old('rt', $service?->rt) }}"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            @error('rt')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="coverage_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Coverage</label>
        <select
            id="coverage_id"
            name="coverage_id"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
        >
            <option value="">Pilih coverage</option>
            @foreach ($coverages as $coverage)
                <option value="{{ $coverage->id }}" @selected((int) old('coverage_id', $service?->coverage_id) === $coverage->id)>{{ $coverage->name }} ({{ $coverage->code }})</option>
            @endforeach
        </select>
        @error('coverage_id')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    @if ($service)
        <div>
            <label for="pin" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">PIN PPPoE</label>
            <input
                type="text"
                id="pin"
                name="pin"
                inputmode="numeric"
                maxlength="6"
                value="{{ old('pin', $service->pin) }}"
                required
                class="block w-full max-w-xs rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Password PPPoE pelanggan (6 digit) — bisa direset manual di sini.</p>
            @error('pin')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
    @endif

    {{-- Modal 'Tambah Pelanggan Baru' — lihat CLAUDE.md 'Service'. --}}
    <div
        x-show="showAddCustomerModal"
        x-cloak
        class="fixed inset-0 z-40 flex items-center justify-center bg-gray-900/50 p-4"
        @keydown.escape.window="showAddCustomerModal = false"
    >
        <div
            x-show="showAddCustomerModal"
            @click.outside="showAddCustomerModal = false"
            class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800"
        >
            <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Tambah Pelanggan Baru</h3>

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
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="showAddCustomerModal = false" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Batal</button>
                <button
                    type="button"
                    @click="submitNewCustomer()"
                    :disabled="newCustomerSubmitting"
                    class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active disabled:opacity-60"
                >
                    <span x-show="! newCustomerSubmitting">Simpan &amp; Lanjutkan</span>
                    <span x-show="newCustomerSubmitting">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Modal 'Lengkapi NIK & Foto KTP' — gate wajib sebelum Service baru
        bisa didaftarkan, lihat CLAUDE.md 'Service'. --}}
    <div
        x-show="showKycModal"
        x-cloak
        class="fixed inset-0 z-40 flex items-center justify-center bg-gray-900/50 p-4"
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
                    class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active disabled:opacity-60"
                >
                    <span x-show="! kycSubmitting">Simpan</span>
                    <span x-show="kycSubmitting">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>
</div>
