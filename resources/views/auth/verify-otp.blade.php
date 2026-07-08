<x-auth-layout :title="'Verifikasi OTP — ' . config('app.name', 'NEXA')">
    <div class="mb-8 text-center">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Verifikasi Kode OTP</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Masukkan 6 digit kode yang dikirim ke WhatsApp Anda.</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-danger/20 bg-danger-light px-4 py-3 text-sm text-danger dark:border-danger/30 dark:bg-danger/10">
            {{ $errors->first() }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('login.otp.verify') }}"
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
            class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active"
        >
            Verifikasi
        </button>

        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
            Tidak menerima kode?
            <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">Kirim ulang</a>
        </p>
    </form>
</x-auth-layout>
