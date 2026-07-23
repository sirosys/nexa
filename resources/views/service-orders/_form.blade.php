@php
    $serviceOrder ??= null;

    $selectedServiceLabel = $serviceOrder?->service
        ? "{$serviceOrder->service->code} — {$serviceOrder->service->user?->name} ({$serviceOrder->service->user?->phone})"
        : '';

    $existingRows = $serviceOrder
        ? $serviceOrder->products->map(fn ($p) => [
            'product_id' => $p->id,
            'quantity' => $p->pivot->quantity,
            'price' => (float) $p->pivot->price,
            'discount' => (float) $p->pivot->discount,
            'unit' => $p->pivot->unit,
        ])->values()->all()
        : [];

    $initialRows = old('products', $existingRows);

    if (empty($initialRows)) {
        $initialRows = [['product_id' => '', 'quantity' => 1, 'price' => '', 'discount' => 0, 'unit' => '']];
    }

    $productOptions = $products->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'price' => (float) $p->price,
        'unit' => $p->unit,
    ])->values()->all();
@endphp

<div
    class="space-y-4"
    x-data="{
        serviceQuery: {{ \Illuminate\Support\Js::from(old('service_label', $selectedServiceLabel)) }},
        serviceId: {{ \Illuminate\Support\Js::from(old('service_id', $serviceOrder?->service_id)) }},
        serviceResults: [],
        serviceOpen: false,
        serviceDebounce: null,
        fetchServices(q) {
            fetch('{{ route('service-orders.services.search') }}?q=' + encodeURIComponent(q))
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
        rows: {{ \Illuminate\Support\Js::from($initialRows) }},
        products: {{ \Illuminate\Support\Js::from($productOptions) }},
        tax: {{ \Illuminate\Support\Js::from((float) old('tax', $serviceOrder?->tax ?? 0)) }},
        adminFee: {{ \Illuminate\Support\Js::from((float) old('admin_fee', $serviceOrder?->admin_fee ?? 0)) }},
        addRow() {
            this.rows.push({ product_id: '', quantity: 1, price: '', discount: 0, unit: '' });
        },
        removeRow(index) {
            if (this.rows.length > 1) {
                this.rows.splice(index, 1);
            }
        },
        applyDefaultPrice(row) {
            const product = this.products.find((p) => p.id === Number(row.product_id));
            if (!product) {
                return;
            }
            if (!row.price) {
                row.price = product.price;
            }
            if (!row.unit) {
                row.unit = product.unit;
            }
        },
        get total() {
            return this.rows.reduce((sum, row) => sum + ((Number(row.quantity) || 0) * (Number(row.price) || 0)), 0);
        },
        get discount() {
            return this.rows.reduce((sum, row) => sum + (Number(row.discount) || 0), 0);
        },
        get subtotal() {
            return this.total - this.discount;
        },
        get grandtotal() {
            return this.subtotal + (Number(this.tax) || 0) + (Number(this.adminFee) || 0);
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
        <label for="package_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Paket</label>
        <select
            id="package_id"
            name="package_id"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
        >
            <option value="">Pilih paket</option>
            @foreach ($packages as $package)
                <option value="{{ $package->id }}" @selected((int) old('package_id', $serviceOrder?->package_id) === $package->id)>
                    {{ $package->name }} ({{ $package->code }}){{ $package->is_starter ? ' — Starter' : '' }}
                </option>
            @endforeach
        </select>
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Semua paket bisa dipilih di sini (starter maupun bukan) — beda dari pendaftaran service baru yang cuma boleh paket starter.</p>
        @error('package_id')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
        <div class="mb-2 flex items-center justify-between">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Produk</label>
            <button type="button" @click="addRow()" class="text-sm font-medium text-primary hover:underline">+ Tambah Produk</button>
        </div>

        @error('products')
            <p class="mb-2 text-sm text-danger">{{ $message }}</p>
        @enderror

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-2 py-2">Produk</th>
                        <th class="px-2 py-2">Qty</th>
                        <th class="px-2 py-2">Harga</th>
                        <th class="px-2 py-2">Diskon</th>
                        <th class="px-2 py-2">Satuan</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in rows" :key="index">
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                            <td class="p-2">
                                <select
                                    :name="'products[' + index + '][product_id]'"
                                    x-model.number="row.product_id"
                                    @change="applyDefaultPrice(row)"
                                    class="block w-full rounded-lg border border-gray-300 bg-transparent px-2 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                                >
                                    <option value="">Pilih produk</option>
                                    <template x-for="product in products" :key="product.id">
                                        <option :value="product.id" x-text="product.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="p-2">
                                <input
                                    type="number"
                                    min="1"
                                    :name="'products[' + index + '][quantity]'"
                                    x-model.number="row.quantity"
                                    class="w-16 rounded-lg border border-gray-300 bg-transparent px-2 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                                >
                            </td>
                            <td class="p-2">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :name="'products[' + index + '][price]'"
                                    x-model.number="row.price"
                                    class="w-28 rounded-lg border border-gray-300 bg-transparent px-2 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                                >
                            </td>
                            <td class="p-2">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :name="'products[' + index + '][discount]'"
                                    x-model.number="row.discount"
                                    class="w-24 rounded-lg border border-gray-300 bg-transparent px-2 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                                >
                            </td>
                            <td class="p-2">
                                <input
                                    type="text"
                                    :name="'products[' + index + '][unit]'"
                                    x-model="row.unit"
                                    class="w-20 rounded-lg border border-gray-300 bg-transparent px-2 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                                >
                            </td>
                            <td class="p-2 text-right">
                                <button type="button" @click="removeRow(index)" class="text-sm font-medium text-danger hover:underline">Hapus</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        @error('products.*.product_id')
            <p class="mt-2 text-sm text-danger">Pastikan semua baris produk dipilih dengan benar.</p>
        @enderror
        @error('products.*.quantity')
            <p class="mt-2 text-sm text-danger">Kuantitas produk harus diisi angka minimal 1.</p>
        @enderror
        @error('products.*.price')
            <p class="mt-2 text-sm text-danger">Harga produk harus diisi.</p>
        @enderror
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="tax" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Pajak</label>
            <input
                type="number"
                id="tax"
                name="tax"
                min="0"
                step="0.01"
                x-model.number="tax"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
            >
            @error('tax')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="admin_fee" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Biaya Admin</label>
            <input
                type="number"
                id="admin_fee"
                name="admin_fee"
                min="0"
                step="0.01"
                x-model.number="adminFee"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
            >
            @error('admin_fee')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="notes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan</label>
        <textarea
            id="notes"
            name="notes"
            rows="2"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >{{ old('notes', $serviceOrder?->notes) }}</textarea>
        @error('notes')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    {{-- Preview total dihitung live di browser untuk kenyamanan input saja —
    field ini SENGAJA tidak punya <input name="...">, jadi tidak pernah ikut
    ter-submit. Server selalu menghitung ulang penuh dari products[] yang
    dikirim (lihat ServiceOrderService::syncProductsAndRecalculate()). --}}
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-700 dark:bg-gray-900/40">
        <div class="flex items-center justify-between py-0.5">
            <span class="text-gray-500 dark:text-gray-400">Total</span>
            <span class="text-gray-900 dark:text-white" x-text="total.toLocaleString('id-ID', { minimumFractionDigits: 2 })"></span>
        </div>
        <div class="flex items-center justify-between py-0.5">
            <span class="text-gray-500 dark:text-gray-400">Diskon</span>
            <span class="text-gray-900 dark:text-white" x-text="discount.toLocaleString('id-ID', { minimumFractionDigits: 2 })"></span>
        </div>
        <div class="flex items-center justify-between py-0.5">
            <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
            <span class="text-gray-900 dark:text-white" x-text="subtotal.toLocaleString('id-ID', { minimumFractionDigits: 2 })"></span>
        </div>
        <div class="mt-1 flex items-center justify-between border-t border-gray-200 pt-1.5 font-semibold dark:border-gray-700">
            <span class="text-gray-700 dark:text-gray-300">Grandtotal</span>
            <span class="text-gray-900 dark:text-white" x-text="grandtotal.toLocaleString('id-ID', { minimumFractionDigits: 2 })"></span>
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Preview saja — nilai final dihitung ulang di server saat disimpan.</p>
    </div>
</div>
