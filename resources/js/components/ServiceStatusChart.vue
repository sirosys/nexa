<script setup>
// Bar chart horizontal — distribusi Service per status. Satu seri (jumlah
// per status), jadi tidak butuh legend (lihat skill dataviz: "a single
// series needs no legend box"). Urutan baris mengikuti urutan yang sudah
// dikirim server (Service::STATUSES, urutan lifecycle) — jangan diurutkan
// ulang di sini berdasar count, supaya chart terbaca sebagai funnel.
import { computed, ref } from 'vue';

const props = defineProps({
    data: { type: Array, required: true },
});

const hovered = ref(null);
const max = computed(() => Math.max(1, ...props.data.map((row) => row.count)));
</script>

<template>
    <div>
        <div class="space-y-3">
            <div
                v-for="(row, index) in data"
                :key="row.status"
                class="relative flex items-center gap-3"
                tabindex="0"
                role="img"
                :aria-label="`${row.label}: ${row.count} layanan`"
                @mouseenter="hovered = index"
                @mouseleave="hovered = null"
                @focus="hovered = index"
                @blur="hovered = null"
            >
                <span class="w-36 shrink-0 truncate text-xs text-gray-500 dark:text-gray-400">{{ row.label }}</span>

                <div class="h-4 flex-1 bg-gray-100 dark:bg-gray-700 rounded-r-[4px]">
                    <div
                        class="h-4 rounded-r-[4px] transition-all duration-300"
                        :style="{ width: `${(row.count / max) * 100}%`, backgroundColor: 'var(--color-primary)' }"
                    ></div>
                </div>

                <span class="w-8 shrink-0 text-right text-xs font-semibold text-gray-900 dark:text-white">{{ row.count }}</span>

                <div
                    v-if="hovered === index"
                    class="absolute -top-9 left-32 z-10 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow-lg dark:bg-gray-700"
                >
                    <span class="font-semibold">{{ row.count }}</span> layanan — {{ row.label }}
                </div>
            </div>
        </div>

        <details class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            <summary class="cursor-pointer select-none">Lihat sebagai tabel</summary>
            <table class="mt-2 w-full text-left">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="py-1 font-medium">Status</th>
                        <th class="py-1 text-right font-medium">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in data" :key="row.status" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <td class="py-1">{{ row.label }}</td>
                        <td class="py-1 text-right">{{ row.count }}</td>
                    </tr>
                </tbody>
            </table>
        </details>
    </div>
</template>
