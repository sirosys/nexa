@php
    $phone = (string) $receipt->sale->service->user->phone;
    $maskedPhone = substr($phone, 0, 4).str_repeat('*', max(strlen($phone) - 7, 0)).substr($phone, -3);
@endphp

<x-auth-layout :title="'Verifikasi Pembayaran — ' . config('app.name', 'NEXA')">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Verifikasi Pembayaran</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Masukkan 6 digit kode yang dikirim ke WhatsApp {{ $maskedPhone }} untuk melanjutkan ke tagihan {{ $receipt->sale->code }}.</p>
    </div>

    @if ($otpStatus)
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ $otpStatus }}
        </div>
    @endif

    @if ($otpError)
        <div class="mb-4 rounded-lg border border-danger/20 bg-danger-light px-4 py-3 text-sm text-danger dark:border-danger/30 dark:bg-danger/10">
            {{ $otpError }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ url()->full() }}"
        x-data="{
            digits: ['', '', '', '', '', ''],
            focusNext(index, event) {
                const value = event.target.value.replace(/[^0-9]/g, '');
                this.digits[index] = value;
                event.target.value = value;
                if (value && index < 5) {
                    this.$refs['digit' + (index + 1)].focus();
                }
            },
            focusPrev(index, event) {
                if (event.key === 'Backspace' && !this.digits[index] && index > 0) {
                    this.$refs['digit' + (index - 1)].focus();
                }
            },
        }"
        class="space-y-6"
    >
        @csrf

        <input type="hidden" name="code" :value="digits.join('')">

        <div class="flex justify-center gap-2">
            @for ($i = 0; $i < 6; $i++)
                <input
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    x-ref="digit{{ $i }}"
                    @input="focusNext({{ $i }}, $event)"
                    @keydown="focusPrev({{ $i }}, $event)"
                    class="h-12 w-11 rounded-lg border border-gray-300 text-center text-lg font-semibold text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
            @endfor
        </div>

        <button
            type="submit"
            class="w-full rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]"
        >
            Verifikasi
        </button>
    </form>

    <form method="POST" action="{{ url()->full() }}" class="mt-4 text-center">
        @csrf
        <input type="hidden" name="resend_otp" value="1">
        <button type="submit" class="text-sm font-medium text-primary hover:underline">
            Tidak menerima kode? Kirim ulang
        </button>
    </form>
</x-auth-layout>
