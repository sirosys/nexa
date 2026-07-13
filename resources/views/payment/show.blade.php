@php
    $sale = $receipt->sale;
    $customerName = $sale->service->user->name;
    $actions = $receipt->raw_response['actions'] ?? [];
    $qrAction = collect($actions)->firstWhere('descriptor', 'QR_STRING');
@endphp

<x-auth-layout :title="'Tagihan '.$sale->code">
    <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Tagihan {{ $sale->code }}</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Halo {{ $customerName }}, berikut detail tagihan pendaftaran Anda.</p>

    <dl class="mt-6 divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
        <x-detail-row label="Jumlah Tagihan">Rp{{ number_format((float) $receipt->amount, 0, ',', '.') }}</x-detail-row>
        <x-detail-row label="Berlaku Sampai">{{ optional($sale->expired_at)->translatedFormat('d F Y H:i') }}</x-detail-row>
    </dl>

    @if ($sale->canceled_at)
        <div class="mt-6 rounded-lg bg-danger-light px-4 py-3 text-sm text-danger dark:bg-danger/10">
            Tagihan ini sudah <strong>dibatalkan</strong> karena melewati batas waktu pembayaran. Silakan hubungi kami kalau Anda masih ingin berlangganan.
        </div>
    @elseif ($sale->settled_at)
        <div class="mt-6 rounded-lg bg-success-light px-4 py-3 text-sm text-success dark:bg-success/10">
            Pembayaran sudah <strong>kami terima</strong>. Terima kasih! Tim kami akan segera menghubungi Anda untuk proses instalasi.
        </div>
    @elseif ($receipt->xendit_payment_request_id)
        <div class="mt-6 space-y-3 rounded-xl border border-gray-200 bg-white px-4 py-5 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
            <p class="text-sm font-medium text-gray-900 dark:text-white">Selesaikan pembayaran Anda ({{ $receipt->channel_code }})</p>

            @if ($qrAction)
                <div
                    class="flex flex-col items-center gap-3 py-2"
                    x-data="qrRenderer('{{ addslashes($qrAction['value']) }}', 'QRIS-{{ $sale->code }}.png')"
                    x-init="init()"
                >
                    <canvas x-ref="canvas" class="rounded-lg border border-gray-200 dark:border-gray-700"></canvas>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Scan kode QR di atas lewat aplikasi e-wallet/mobile banking Anda.</p>
                    <button
                        type="button"
                        @click="download()"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Download QR Code
                    </button>
                </div>
            @else
                @forelse ($actions as $action)
                    @if (($action['descriptor'] ?? null) === 'WEB_URL')
                        <a href="{{ $action['value'] }}" target="_blank" rel="noopener" class="inline-block rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-active">
                            Lanjutkan Pembayaran &rarr;
                        </a>
                    @elseif (($action['descriptor'] ?? null) === 'VIRTUAL_ACCOUNT_NUMBER')
                        <p class="text-sm text-gray-600 dark:text-gray-300">Nomor Virtual Account:</p>
                        <p class="font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ $action['value'] }}</p>
                    @elseif (($action['descriptor'] ?? null) === 'PAYMENT_CODE')
                        <p class="text-sm text-gray-600 dark:text-gray-300">Kode Pembayaran (tunjukkan ke kasir):</p>
                        <p class="font-mono text-lg font-semibold text-gray-900 dark:text-white">{{ $action['value'] }}</p>
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $action['type'] ?? 'Instruksi' }}: {{ $action['value'] ?? '-' }}</p>
                    @endif
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Status: {{ $receipt->status }} — menunggu update dari Xendit.</p>
                @endforelse
            @endif
        </div>
    @else
        @if ($paymentError)
            <div class="mt-6 rounded-lg bg-danger-light px-4 py-3 text-sm text-danger dark:bg-danger/10">
                {{ $paymentError }} Silakan coba pilih channel lain.
            </div>
        @endif

        <form
            method="POST"
            action="{{ url()->full() }}"
            x-data="{ selected: '' }"
            class="mt-6 space-y-5"
        >
            @csrf

            <p class="text-sm font-medium text-gray-900 dark:text-white">Pilih metode pembayaran</p>

            <input type="hidden" name="channel_code" :value="selected">

            @foreach ($channelGroups as $group)
                @php
                    $count = count($group['channels']);
                    $cols = $count >= 3 ? 3 : ($count === 2 ? 2 : 1);
                @endphp
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $group['label'] }}</p>
                    <div class="grid gap-2" style="grid-template-columns: repeat({{ $cols }}, minmax(0, 1fr));">
                        @foreach ($group['channels'] as $channel)
                            <button
                                type="button"
                                @click="selected = '{{ $channel['code'] }}'"
                                :class="selected === '{{ $channel['code'] }}'
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary text-primary'
                                    : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:border-primary/40'"
                                class="rounded-lg border px-3 py-3 text-center text-sm font-medium transition"
                            >
                                {{ $channel['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <button
                type="submit"
                :disabled="!selected"
                :class="selected ? 'bg-primary hover:bg-primary-active' : 'bg-gray-300 dark:bg-gray-600 cursor-not-allowed'"
                class="w-full rounded-lg px-4 py-2.5 text-sm font-medium text-white transition"
            >
                Buat Tagihan
            </button>
        </form>
    @endif
</x-auth-layout>
