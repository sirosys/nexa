@php
    $ticket ??= null;

    $selectedServiceLabel = $ticket?->service
        ? "{$ticket->service->code} — {$ticket->service->user?->name} ({$ticket->service->user?->phone})"
        : '';
@endphp

<div
    class="space-y-4"
    x-data="{
        serviceQuery: {{ \Illuminate\Support\Js::from(old('service_label', $selectedServiceLabel)) }},
        serviceId: {{ \Illuminate\Support\Js::from(old('service_id', $ticket?->service_id)) }},
        serviceResults: [],
        serviceOpen: false,
        serviceDebounce: null,
        fetchServices(q) {
            fetch('{{ route('tickets.services.search') }}?q=' + encodeURIComponent(q))
                .then((res) => res.json())
                .then((data) => {
                    this.serviceResults = data;
                    this.serviceOpen = true;
                });
        },
        searchServices() {
            clearTimeout(this.serviceDebounce);
            this.serviceId = null;
            const length = this.serviceQuery.trim().length;
            if (length > 0 && length < 3) {
                this.serviceResults = [];
                this.serviceOpen = false;
                return;
            }
            this.serviceDebounce = setTimeout(() => this.fetchServices(this.serviceQuery.trim()), 300);
        },
        openServiceBrowse() {
            if (this.serviceId) {
                return;
            }
            if (this.serviceResults.length > 0) {
                this.serviceOpen = true;
                return;
            }
            this.fetchServices('');
        },
        selectService(item) {
            this.serviceId = item.id;
            this.serviceQuery = item.code + ' — ' + (item.customer_name ?? '') + ' (' + (item.customer_phone ?? '') + ')';
            this.serviceResults = [];
            this.serviceOpen = false;
        },
    }"
>
    <div class="relative">
        <label for="service_query" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Service</label>
        <input
            type="text"
            id="service_query"
            x-model="serviceQuery"
            @input="searchServices()"
            @focus="openServiceBrowse()"
            @click.outside="serviceOpen = false"
            autocomplete="off"
            placeholder="Klik untuk lihat daftar, atau ketik kode/alamat/nama pelanggan..."
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        <input type="hidden" name="service_id" :value="serviceId">

        <div
            x-show="serviceOpen"
            x-cloak
            class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
        >
            <template x-for="item in serviceResults" :key="item.id">
                <button
                    type="button"
                    @click="selectService(item)"
                    class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <span class="font-medium" x-text="item.code"></span>
                    <span class="text-gray-500 dark:text-gray-400" x-text="' — ' + (item.customer_name ?? '') + ' (' + (item.customer_phone ?? '') + ')'"></span>
                </button>
            </template>
            <p x-show="serviceResults.length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Service tidak ditemukan.</p>
        </div>
        @error('service_id')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="category" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Kategori</label>
        <select
            id="category"
            name="category"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
        >
            <option value="">Pilih kategori</option>
            @foreach (\App\Models\ServiceTicket::CATEGORY_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('category', $ticket?->category) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Kategori "Teknis" wajib ditugaskan/diklaim teknisi dulu sebelum bisa diselesaikan; kategori lain diselesaikan langsung oleh staff.</p>
        @error('category')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="subject" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Subjek</label>
        <input
            type="text"
            id="subject"
            name="subject"
            value="{{ old('subject', $ticket?->subject) }}"
            maxlength="150"
            placeholder="Ringkasan singkat keluhan/permintaan"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        @error('subject')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Deskripsi</label>
        <textarea
            id="description"
            name="description"
            rows="4"
            placeholder="Detail keluhan/permintaan pelanggan"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >{{ old('description', $ticket?->description) }}</textarea>
        @error('description')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>
</div>
