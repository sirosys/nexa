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
        selectCustomer(item) {
            this.customerId = item.id;
            this.customerQuery = item.name + ' (' + item.phone + ')';
            this.customerResults = [];
            this.customerOpen = false;
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
                    class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                    x-text="item.name + ' (' + item.phone + ')'"
                ></button>
            </template>
            <p x-show="customerResults.length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Pelanggan tidak ditemukan.</p>
        </div>
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
</div>
