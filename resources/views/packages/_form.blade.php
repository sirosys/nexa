@php
    $package ??= null;

    $existingRows = $package
        ? $package->products->map(fn ($p) => [
            'product_id' => $p->id,
            'quantity' => $p->pivot->quantity,
            'price' => (float) $p->pivot->price,
        ])->values()->all()
        : [];

    $initialRows = old('products', $existingRows);

    if (empty($initialRows)) {
        $initialRows = [['product_id' => '', 'quantity' => 1, 'price' => '']];
    }

    $productOptions = $products->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'price' => (float) $p->price,
    ])->values()->all();
@endphp

<div
    class="space-y-4"
    x-data="{
        rows: {{ \Illuminate\Support\Js::from($initialRows) }},
        products: {{ \Illuminate\Support\Js::from($productOptions) }},
        addRow() {
            this.rows.push({ product_id: '', quantity: 1, price: '' });
        },
        removeRow(index) {
            if (this.rows.length > 1) {
                this.rows.splice(index, 1);
            }
        },
        applyDefaultPrice(row) {
            const product = this.products.find((p) => p.id === Number(row.product_id));
            if (product && !row.price) {
                row.price = product.price;
            }
        },
    }"
>
    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $package?->name) }}"
            required
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        @error('name')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Deskripsi</label>
        <textarea
            id="description"
            name="description"
            rows="3"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >{{ old('description', $package?->description) }}</textarea>
        @error('description')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="price" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Harga Paket</label>
        <input
            type="number"
            id="price"
            name="price"
            step="0.01"
            min="0"
            value="{{ old('price', $package?->price) }}"
            required
            class="block w-full max-w-xs rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        @error('price')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        <input
            type="checkbox"
            name="is_starter"
            value="1"
            @checked(old('is_starter', $package?->is_starter))
            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600"
        >
        Bisa dipilih saat pendaftaran baru (starter)
    </label>
    @error('is_starter')
        <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
    @enderror
    <p class="text-xs text-gray-500 dark:text-gray-400">Kalau tidak dicentang, paket ini hanya bisa dipilih oleh pelanggan yang sudah aktif berlangganan (mis. paket upgrade/add-on).</p>

    <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
        <div class="mb-2 flex items-center justify-between">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Produk dalam Paket</label>
            <button type="button" @click="addRow()" class="text-sm font-medium text-primary hover:underline">+ Tambah Produk</button>
        </div>
        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Kalau ada produk bertipe "Langganan", quantity-nya menentukan durasi masa aktif paket (1 = 1 bulan) — kalau lebih dari satu produk langganan ditambahkan, quantity-nya harus sama.</p>

        @error('products')
            <p class="mb-2 text-sm text-danger">{{ $message }}</p>
        @enderror

        <div class="space-y-2">
            <template x-for="(row, index) in rows" :key="index">
                <div class="flex items-start gap-2">
                    <select
                        :name="'products[' + index + '][product_id]'"
                        x-model.number="row.product_id"
                        @change="applyDefaultPrice(row)"
                        class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                    >
                        <option value="">Pilih produk</option>
                        <template x-for="product in products" :key="product.id">
                            <option :value="product.id" x-text="product.name"></option>
                        </template>
                    </select>
                    <input
                        type="number"
                        min="1"
                        :name="'products[' + index + '][quantity]'"
                        x-model.number="row.quantity"
                        placeholder="Qty"
                        class="w-20 rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                    >
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        :name="'products[' + index + '][price]'"
                        x-model.number="row.price"
                        placeholder="Harga"
                        class="w-32 rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                    >
                    <button type="button" @click="removeRow(index)" class="mt-2.5 text-sm font-medium text-danger hover:underline">Hapus</button>
                </div>
            </template>
        </div>

        @error('products.*.product_id')
            <p class="mt-2 text-sm text-danger">Pastikan semua baris produk dipilih dengan benar.</p>
        @enderror
        @error('products.*.quantity')
            <p class="mt-2 text-sm text-danger">Kuantitas produk harus diisi angka minimal 1.</p>
        @enderror
        @error('products.*.price')
            <p class="mt-2 text-sm text-danger">Harga produk dalam paket harus diisi.</p>
        @enderror
    </div>
</div>
