import Alpine from 'alpinejs';
import QRCode from 'qrcode';

window.Alpine = Alpine;

/**
 * Render QR string (dari actions Xendit, lihat resources/views/payment/show.blade.php)
 * ke <canvas> di browser + tombol download PNG - adaptasi dari
 * ~/Webapp/xnet/app/resources/js/app.js (referensi desain, lihat CLAUDE.md
 * "Billing / Invoice (Xendit)"). Tidak ada rendering QR di server sama
 * sekali, murni client-side.
 */
window.qrRenderer = function (qrString, filename) {
    return {
        init() {
            QRCode.toCanvas(this.$refs.canvas, qrString, {
                width: 220,
                margin: 2,
                color: { dark: '#000000', light: '#ffffff' },
            });
        },
        download() {
            const link = document.createElement('a');
            link.download = filename || 'qris-payment.png';
            link.href = this.$refs.canvas.toDataURL('image/png');
            link.click();
        },
    };
};

Alpine.start();
