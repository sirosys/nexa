@php
    $site ??= null;

    $selectedSubdistrictLabel = $site?->subdistrict
        ? "{$site->subdistrict->name}, {$site->subdistrict->district_name}, {$site->subdistrict->city_name}, {$site->subdistrict->province_name}"
        : '';
@endphp

<div
    class="space-y-4"
    x-data="{
        query: {{ \Illuminate\Support\Js::from(old('subdistrict_label', $selectedSubdistrictLabel)) }},
        subdistrictId: {{ \Illuminate\Support\Js::from(old('subdistrict_id', $site?->subdistrict_id)) }},
        results: [],
        open: false,
        debounceTimer: null,
        search() {
            clearTimeout(this.debounceTimer);
            this.subdistrictId = null;
            if (this.query.trim().length < 2) {
                this.results = [];
                this.open = false;
                return;
            }
            this.debounceTimer = setTimeout(() => {
                fetch('{{ route('subdistricts.search') }}?q=' + encodeURIComponent(this.query))
                    .then((res) => res.json())
                    .then((data) => {
                        this.results = data;
                        this.open = data.length > 0;
                    });
            }, 300);
        },
        select(item) {
            this.subdistrictId = item.id;
            this.query = item.name + ', ' + item.district_name + ', ' + item.city_name + ', ' + item.province_name;
            this.results = [];
            this.open = false;
        },
    }"
>
    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Site</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $site?->name) }}"
            required
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        @error('name')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div class="relative">
        <label for="subdistrict_query" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Wilayah (Kelurahan)</label>
        <input
            type="text"
            id="subdistrict_query"
            x-model="query"
            @input="search()"
            @focus="open = results.length > 0"
            @click.outside="open = false"
            autocomplete="off"
            placeholder="Ketik nama kelurahan..."
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        <input type="hidden" name="subdistrict_id" :value="subdistrictId">

        <div
            x-show="open"
            x-cloak
            class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
        >
            <template x-for="item in results" :key="item.id">
                <button
                    type="button"
                    @click="select(item)"
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
            <label for="serial" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Serial</label>
            <input
                type="text"
                id="serial"
                name="serial"
                value="{{ old('serial', $site?->serial) }}"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            @error('serial')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="model" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Model</label>
            <input
                type="text"
                id="model"
                name="model"
                value="{{ old('model', $site?->model) }}"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            @error('model')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="location" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Lokasi</label>
        <input
            type="text"
            id="location"
            name="location"
            placeholder="Alamat/deskripsi lokasi fisik"
            value="{{ old('location', $site?->location) }}"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        @error('location')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="token" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Token Perangkat (Password API)</label>
        <input
            type="text"
            id="token"
            name="token"
            placeholder="Kredensial akses perangkat (opsional)"
            value="{{ old('token') }}"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Tersimpan terenkripsi. Kosongkan kalau tidak ingin mengubah token yang sudah ada. Dipakai sebagai password Basic Auth REST API MikroTik.</p>
        @error('token')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
        <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Akses REST API MikroTik</p>
        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Belum wajib diisi — cuma dipakai kalau driver integrasi MikroTik sudah diaktifkan ke 'http' (lihat CLAUDE.md "Integrasi MikroTik").</p>

        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
                <label for="host" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Host/IP Router</label>
                <input
                    type="text"
                    id="host"
                    name="host"
                    placeholder="mis. 172.16.0.1 (IP WireGuard, bukan IP public)"
                    value="{{ old('host', $site?->host) }}"
                    class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                >
                @error('host')
                    <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="api_port" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Port REST API</label>
                <input
                    type="number"
                    id="api_port"
                    name="api_port"
                    min="1"
                    max="65535"
                    placeholder="443"
                    value="{{ old('api_port', $site?->api_port) }}"
                    class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                >
                @error('api_port')
                    <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-4">
            <label for="api_username" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username API</label>
            <input
                type="text"
                id="api_username"
                name="api_username"
                placeholder="mis. api"
                value="{{ old('api_username', $site?->api_username) }}"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            @error('api_username')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
