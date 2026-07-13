import Alpine from 'alpinejs';
import QRCode from 'qrcode';
import { createApp } from 'vue';
import RevenueChart from './components/RevenueChart.vue';
import ServiceStatusChart from './components/ServiceStatusChart.vue';

window.Alpine = Alpine;

/**
 * Mount komponen Vue "island" ke elemen dengan id tertentu, kalau elemen itu
 * ada di halaman — dashboard.blade.php satu-satunya pemakai saat ini (chart
 * "grafik" adalah domain Vue per CLAUDE.md, bukan Alpine). Data dikirim
 * server lewat atribut data-chart berisi JSON (di-escape Blade {{ }} biasa,
 * bukan Js::from, supaya aman dipakai di dalam atribut HTML berkutip ganda).
 */
function mountChart(elementId, component) {
    const el = document.getElementById(elementId);
    if (!el) {
        return;
    }

    createApp(component, { data: JSON.parse(el.dataset.chart) }).mount(el);
}

mountChart('service-status-chart', ServiceStatusChart);
mountChart('revenue-chart', RevenueChart);

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
