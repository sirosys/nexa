<script setup>
// Bar chart vertikal (kolom) — pendapatan settled per bulan, 6 bulan
// terakhir. Satu seri, tanpa legend. Warna sukses (hijau) dipakai sengaja
// untuk merepresentasikan pendapatan/uang, beda dari primary yang dipakai
// ServiceStatusChart.
import { computed, ref } from 'vue';
import { formatRupiah } from '../support/currency';

const props = defineProps({
    data: { type: Array, required: true },
});

const hovered = ref(null);
const max = computed(() => Math.max(1, ...props.data.map((col) => col.total)));
</script>

<template>
    <div>
        <div class="flex h-40 items-end gap-3">
            <div
                v-for="(col, index) in data"
                :key="col.label"
                class="relative flex flex-1 flex-col items-center gap-2"
                tabindex="0"
                role="img"
                :aria-label="`${col.label}: ${formatRupiah(col.total)}`"
                @mouseenter="hovered = index"
                @mouseleave="hovered = null"
                @focus="hovered = index"
                @blur="hovered = null"
            >
                <div
                    v-if="hovered === index"
                    class="absolute -top-10 z-10 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow-lg dark:bg-gray-700"
                >
                    {{ formatRupiah(col.total) }}
                </div>

                <div class="flex h-32 w-full max-w-6 items-end justify-center">
                    <div
                        class="w-full rounded-t-[4px] transition-all duration-300"
                        :style="{ height: `${Math.max(2, (col.total / max) * 100)}%`, backgroundColor: 'var(--color-success)' }"
                    ></div>
                </div>

                <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ col.label }}</span>
            </div>
        </div>

        <details class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            <summary class="cursor-pointer select-none">Lihat sebagai tabel</summary>
            <table class="mt-2 w-full text-left">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="py-1 font-medium">Bulan</th>
                        <th class="py-1 text-right font-medium">Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="col in data" :key="col.label" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <td class="py-1">{{ col.label }}</td>
                        <td class="py-1 text-right">{{ formatRupiah(col.total) }}</td>
                    </tr>
                </tbody>
            </table>
        </details>
    </div>
</template>
