<?php

// Peta nama ikon NEXA (dipakai lewat <x-icon name="..."> di seluruh app) ke path
// relatif file SVG yang divendor dari Metronic v7.0.0 demo1
// (assets/media/svg/icons/**, "Stockholm Icons" — dibuat KeenThemes untuk template
// ini). File asli disalin apa adanya ke resources/icons/metronic/<path ini> —
// lihat CLAUDE.md "Referensi Desain UI" untuk keputusan vendoring ini (2026-07-23,
// permintaan eksplisit user, membalik keputusan lama yang menghindari aset ikon
// proprietary template — Stockholm Icons didistribusikan sebagai file SVG lepas
// dalam paket demo, bukan font berlisensi terpisah, dan user sudah eksplisit
// mengizinkan vendoring aset template untuk kloning tampilan ini).
//
// Beberapa mapping adalah "kecocokan terdekat" dari 640 ikon yang tersedia di
// set ini (bukan pencarian 1:1 sempurna per konsep) — pola sama seperti saat
// Heroicons pertama kali diadopsi. Kalau butuh ikon baru, cari nama filenya di
// assets/media/svg/icons/<Kategori>/ pada template asli, copy ke
// resources/icons/metronic/<Kategori>/<Nama>.svg, lalu tambah satu baris di sini.
return [
    'archive-box' => 'Communication/Archive.svg',
    'arrow-down-tray' => 'Files/Download.svg',
    'arrow-left' => 'Navigation/Arrow-left.svg',
    'arrow-right-on-rectangle' => 'Navigation/Sign-out.svg',
    'arrow-up-tray' => 'Files/Upload.svg',
    'banknotes' => 'Shopping/Money.svg',
    'bars-3' => 'Text/Menu.svg',
    'bell' => 'General/Notification2.svg',
    'bolt-slash' => 'Electric/Shutdown.svg',
    'building-office-2' => 'Home/Building.svg',
    'calendar' => 'Code/Time-schedule.svg',
    'chart-bar' => 'Shopping/Chart-bar1.svg',
    'check-circle' => 'Code/Done-circle.svg',
    'chevron-down' => 'Navigation/Angle-down.svg',
    'chevron-right' => 'Navigation/Angle-right.svg',
    'clipboard-document-list' => 'Communication/Clipboard-list.svg',
    'clipboard-document' => 'General/Clipboard.svg',
    'clock' => 'Home/Clock.svg',
    'cog-6-tooth' => 'General/Settings-1.svg',
    'credit-card' => 'Shopping/Credit-card.svg',
    'cube' => 'Shopping/Box1.svg',
    'document-text' => 'Files/File.svg',
    'envelope' => 'Communication/Mail.svg',
    'exclamation-triangle' => 'Code/Warning-2.svg',
    'eye' => 'General/Visible.svg',
    'gift' => 'Shopping/Gift.svg',
    'home' => 'Home/Home.svg',
    'identification' => 'Communication/Address-card.svg',
    'information-circle' => 'Code/Info-circle.svg',
    'magnifying-glass' => 'General/Search.svg',
    'map-pin' => 'Map/Marker1.svg',
    'map' => 'Map/Position.svg',
    'no-symbol' => 'Code/Warning-1-circle.svg',
    'pencil-square' => 'Design/Edit.svg',
    'phone' => 'Devices/Phone.svg',
    'photo' => 'Files/Pictures1.svg',
    'plus' => 'Navigation/Plus.svg',
    'qr-code' => 'Shopping/Barcode.svg',
    'server' => 'Devices/Server.svg',
    'shield-check' => 'General/Shield-check.svg',
    'shopping-cart' => 'Shopping/Cart3.svg',
    'signal' => 'Devices/LTE1.svg',
    'ticket' => 'Shopping/Ticket.svg',
    'trash' => 'General/Trash.svg',
    'truck' => 'Shopping/Box2.svg',
    'user-circle' => 'General/User.svg',
    'users' => 'Communication/Group.svg',
    'wifi' => 'Devices/Wi-fi.svg',
    'wrench-screwdriver' => 'Tools/Tools.svg',
    'x-circle' => 'Code/Error-circle.svg',
    'x-mark' => 'Navigation/Close.svg',
];
