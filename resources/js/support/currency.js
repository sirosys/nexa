/**
 * Format Rupiah sisi client, dipakai komponen chart Vue di dashboard.
 * Mirror App\Support\Currency::rupiah() di backend (prefix "Rp", titik
 * sebagai pemisah ribuan) — tidak bisa reuse langsung karena beda runtime.
 */
export function formatRupiah(value) {
    return 'Rp' + Math.round(value).toLocaleString('id-ID');
}
