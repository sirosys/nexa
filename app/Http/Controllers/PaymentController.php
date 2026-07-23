<?php

namespace App\Http\Controllers;

use App\Exceptions\OtpThrottledException;
use App\Models\Receipt;
use App\Services\PaymentOtpService;
use App\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Halaman publik (tanpa login) tempat pelanggan memilih channel
 * pembayaran Xendit dan melihat hasilnya. Diakses lewat link di
 * InvoiceCreatedNotification, diamankan lewat middleware `signed`
 * (bukan auth) — lihat CLAUDE.md "Billing / Invoice (Xendit)".
 *
 * Seluruh aksi POST (kirim ulang OTP, verifikasi OTP, pilih channel)
 * SENGAJA disatukan ke satu route `POST /pay/{receipt}` (method update()),
 * bukan route terpisah per aksi — signature Laravel terikat ke path+query
 * persis yang di-generate `URL::temporarySignedRoute()` (ReceiptService),
 * jadi route POST terpisah butuh signature-nya sendiri yang tidak kita
 * punya (cuma satu `checkout_url` disimpan per Receipt). Form di view
 * selalu submit ke `url()->full()` (URL saat ini, termasuk signature),
 * dibedakan lewat field mana yang ada di payload.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly ReceiptService $receiptService,
        private readonly PaymentOtpService $otpService,
    ) {}

    public function show(Receipt $receipt): View
    {
        $receipt->load('serviceOrder.service.user');

        if (! $this->otpService->isVerified($receipt)) {
            if (! $this->otpService->hasPendingCode($receipt)) {
                $this->otpService->send($receipt);
            }

            return view('payment.verify-otp', [
                'receipt' => $receipt,
                'otpError' => session('otp_error'),
                'otpStatus' => session('otp_status'),
            ]);
        }

        return view('payment.show', [
            'receipt' => $receipt,
            'channelGroups' => config('billing.payment_channels'),
            'paymentError' => session('payment_error'),
        ]);
    }

    public function update(Request $request, Receipt $receipt): RedirectResponse
    {
        if ($request->has('resend_otp')) {
            return $this->resendOtp($receipt);
        }

        if ($request->has('code')) {
            return $this->verifyOtp($request, $receipt);
        }

        return $this->selectChannel($request, $receipt);
    }

    private function resendOtp(Receipt $receipt): RedirectResponse
    {
        try {
            $this->otpService->send($receipt);
        } catch (OtpThrottledException) {
            return redirect($receipt->checkout_url)->with('otp_error', 'Mohon tunggu sebentar sebelum meminta kode baru.');
        }

        return redirect($receipt->checkout_url)->with('otp_status', 'Kode baru sudah dikirim ke WhatsApp Anda.');
    }

    private function verifyOtp(Request $request, Receipt $receipt): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);

        if (! $this->otpService->verify($receipt, $validated['code'])) {
            return redirect($receipt->checkout_url)->with('otp_error', 'Kode salah atau sudah kadaluarsa.');
        }

        return redirect($receipt->checkout_url);
    }

    private function selectChannel(Request $request, Receipt $receipt): RedirectResponse
    {
        // Pertahanan berlapis kalau status verifikasi kadaluarsa di antara
        // GET (render picker) dan POST ini - redirect balik akan
        // menampilkan layar verifikasi OTP lagi lewat show().
        if (! $this->otpService->isVerified($receipt)) {
            return redirect($receipt->checkout_url);
        }

        $validated = $request->validate([
            'channel_code' => ['required', 'string', Rule::in($this->validChannelCodes())],
        ]);

        try {
            $this->receiptService->selectChannel($receipt, $validated['channel_code']);
        } catch (RuntimeException $exception) {
            return redirect($receipt->checkout_url)->with('payment_error', $exception->getMessage());
        }

        return redirect($receipt->checkout_url);
    }

    /**
     * @return array<int, string>
     */
    private function validChannelCodes(): array
    {
        return collect(config('billing.payment_channels'))
            ->flatMap(fn (array $category) => collect($category['channels'])->pluck('code'))
            ->all();
    }
}
