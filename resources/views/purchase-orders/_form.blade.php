@php
    $purchaseOrder ??= null;

    $existingRows = $purchaseOrder
        ? $purchaseOrder->inventoryItems->map(fn ($item) => [
            'inventory_item_id' => $item->id,
            'quantity' => $item->pivot->quantity,
            'price' => (float) $item->pivot->price,
        ])->values()->all()
        : [];

    $initialRows = old('items', $existingRows);

    if (empty($initialRows)) {
        $initialRows = [['inventory_item_id' => '', 'quantity' => 1, 'price' => '']];
    }

    $itemOptions = $inventoryItems->map(fn ($item) => [
        'id' => $item->id,
        'name' => ($item->product?->name ?? '—') . ' (' . $item->code . ')',
    ])->values()->all();
@endphp

<div
    class="space-y-4"
    x-data="{
        rows: {{ \Illuminate\Support\Js::from($initialRows) }},
        items: {{ \Illuminate\Support\Js::from($itemOptions) }},
        addRow() {
            this.rows.push({ inventory_item_id: '', quantity: 1, price: '' });
        },
        removeRow(index) {
            if (this.rows.length > 1) {
                this.rows.splice(index, 1);
            }
        },
        get total() {
            return this.rows.reduce((sum, row) => sum + ((Number(row.quantity) || 0) * (Number(row.price) || 0)), 0);
        },
    }"
>
    <div>
        <label for="vendor_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Vendor</label>
        <select
            id="vendor_id"
            name="vendor_id"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
        >
            <option value="">Pilih vendor</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected((int) old('vendor_id', $purchaseOrder?->vendor_id) === $vendor->id)>{{ $vendor->name }} ({{ $vendor->code }})</option>
            @endforeach
        </select>
        @error('vendor_id')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
        <div class="mb-2 flex items-center justify-between">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Barang Dipesan</label>
            <button type="button" @click="addRow()" class="text-sm font-medium text-primary hover:underline">+ Tambah Barang</button>
        </div>

        @error('items')
            <p class="mb-2 text-sm text-danger">{{ $message }}</p>
        @enderror

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-2 py-2">Item Inventaris</th>
                        <th class="px-2 py-2">Qty</th>
                        <th class="px-2 py-2">Harga Beli</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in rows" :key="index">
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-700/50">
                            <td class="p-2">
                                <select
                                    :name="'items[' + index + '][inventory_item_id]'"
                                    x-model.number="row.inventory_item_id"
                                    class="block w-full rounded-lg border border-gray-300 bg-transparent px-2 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                                >
                                    <option value="">Pilih item</option>
                                    <template x-for="item in items" :key="item.id">
                                        <option :value="item.id" x-text="item.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="p-2">
                                <input
                                    type="number"
                                    min="1"
                                    :name="'items[' + index + '][quantity]'"
                                    x-model.number="row.quantity"
                                    class="w-20 rounded-lg border border-gray-300 bg-transparent px-2 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                                >
                            </td>
                            <td class="p-2">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    :name="'items[' + index + '][price]'"
                                    x-model.number="row.price"
                                    class="w-32 rounded-lg border border-gray-300 bg-transparent px-2 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
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

        @error('items.*.inventory_item_id')
            <p class="mt-2 text-sm text-danger">Pastikan semua baris item dipilih dengan benar.</p>
        @enderror
        @error('items.*.quantity')
            <p class="mt-2 text-sm text-danger">Kuantitas harus diisi angka minimal 1.</p>
        @enderror
        @error('items.*.price')
            <p class="mt-2 text-sm text-danger">Harga beli harus diisi.</p>
        @enderror
    </div>

    <div>
        <label for="notes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan</label>
        <textarea
            id="notes"
            name="notes"
            rows="2"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >{{ old('notes', $purchaseOrder?->notes) }}</textarea>
        @error('notes')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    {{-- Preview total dihitung live di browser untuk kenyamanan input saja —
    field ini SENGAJA tidak punya <input name="...">, jadi tidak pernah ikut
    ter-submit. Server selalu menghitung ulang penuh dari items[] yang
    dikirim (lihat PurchaseOrderService::syncItemsAndRecalculate()). --}}
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-700 dark:bg-gray-900/40">
        <div class="flex items-center justify-between py-0.5 font-semibold">
            <span class="text-gray-700 dark:text-gray-300">Total</span>
            <span class="text-gray-900 dark:text-white" x-text="total.toLocaleString('id-ID', { minimumFractionDigits: 2 })"></span>
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Preview saja — nilai final dihitung ulang di server saat disimpan.</p>
    </div>
</div>
