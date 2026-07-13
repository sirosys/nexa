<x-app-layout :title="'Tambah Item Inventaris — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Tambah Item Inventaris</h1>
    </div>

    <div class="max-w-xl rounded-2xl border border-gray-300 bg-white p-6 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <form method="POST" action="{{ route('inventory-items.store') }}">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="product_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Produk</label>
                    <select
                        id="product_id"
                        name="product_id"
                        class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                    >
                        <option value="">Pilih produk</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((int) old('product_id') === $product->id)>{{ $product->name }} ({{ $product->code }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Hanya produk bertipe "Perangkat" yang belum punya item inventaris ditampilkan di sini.</p>
                    @error('product_id')
                        <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Pelacakan Stok</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" name="is_serialized" value="0" @checked(old('is_serialized', '0') === '0')>
                            Kuantitas saja (mis. kabel, konektor)
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" name="is_serialized" value="1" @checked(old('is_serialized') === '1')>
                            Per serial number (mis. modem, ONT)
                        </label>
                    </div>
                    @error('is_serialized')
                        <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]"
                >
                    Simpan
                </button>
                <a href="{{ route('inventory-items.index') }}" class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-300">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
