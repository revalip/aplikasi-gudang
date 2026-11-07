<?php

// Type sekarang dikelola dari database (tabel item_types), lihat ItemTypeController.
// Sisanya masih fixed dulu sesuai requirement awal - kalau nanti perlu ditambah dari UI juga, tinggal
// bikin tabel + controller sama kayak pola ItemType.

return [
    // 7 Status resmi sesuai standar dokumen magang (Bab 3.5 & Bab 7)
    'statuses' => [
        'Available',
        'Reserved',
        'In Transit',
        'Rented',
        'Under Service',
        'Damaged',
        'Lost',
    ],

    // Pemetaan status lama untuk backward compatibility dan import Excel
    'legacy_statuses' => [
        'In Stock' => 'Available',
        'Borrow' => 'Rented',
        'Broken' => 'Damaged',
        'Instalation' => 'Reserved',
        'Has not been tested' => 'Under Service',
        'Retur device to supplier' => 'Under Service',
        'Sold Out' => 'Lost',
        'Temporarity unavailable' => 'Under Service',
        'Re-purchase needed' => 'Lost',
        'ATK' => 'Available',
    ],

    'conditions' => [
        'Port damage suspected', 'Power LED issue suspected', 'Total failure suspected',
        'Normal', 'New', 'Used', 'Damaged', 'Custom', 'Spare part damage suspected',
    ],

    // Old Indonesian condition words are still accepted in Excel imports.
    'legacy_conditions' => [
        'indikasi port rusak' => 'Port damage suspected', 'indikasi led power' => 'Power LED issue suspected',
        'indikasi mati total' => 'Total failure suspected', 'indikasi sparepart rusak' => 'Spare part damage suspected',
        'baru' => 'New', 'bekas' => 'Used', 'rusak' => 'Damaged',
    ],

    // Prefix Item ID per type (MJG-RTR-0001 dst). Type baru tanpa entri pakai 3 huruf pertama namanya.
    'id_prefixes' => [
        'Router' => 'RTR', 'Switch' => 'SWT', 'Access Point' => 'AP', 'Modem' => 'MDM',
        'Modul/SFP' => 'SFP', 'Part' => 'PRT', 'Radio' => 'RAD', 'Antena' => 'ANT',
        'CCTV' => 'CAM', 'Converter' => 'CVT', 'Kabel' => 'KBL', 'Laptop' => 'LPT',
        'Memory' => 'MEM', 'Controller' => 'CTL', 'Box' => 'BOX', 'Adaptor/POE/PSU' => 'PSU',
    ],

    // Nama ikon Lucide (https://lucide.dev/icons) per type; type baru tanpa entri pakai 'default'.
    'type_icons' => [
        'Router' => 'router',
        'Switch' => 'network',
        'Adaptor/POE/PSU' => 'plug',
        'Access Point' => 'wifi',
        'Modem' => 'hard-drive',
        'Modul/SFP' => 'cpu',
        'Part' => 'cog',
        'Radio' => 'radio-tower',
        'Antena' => 'radio',
        'CCTV' => 'cctv',
        'Converter' => 'arrow-left-right',
        'Kabel' => 'cable',
        'Laptop' => 'laptop',
        'Memory' => 'memory-stick',
        'Controller' => 'server',
        'Box' => 'box',
        'default' => 'package',
    ],

    // Warna label status: [latar, teks].
    'status_colors' => [
        // 7 Standar Dokumen Magang
        'Available' => ['#E7F6EC', '#1E7A3C'],      // Hijau terang & hijau tua
        'Reserved' => ['#EFF6FF', '#1D4ED8'],       // Biru muda & biru
        'In Transit' => ['#F5F3FF', '#6D28D9'],     // Ungu muda & ungu
        'Rented' => ['#FFF4DB', '#8A5A00'],         // Kuning hangat & cokelat/oranye
        'Under Service' => ['#FEF3C7', '#B45309'],  // Amber & amber gelap
        'Damaged' => ['#FCE8EF', '#B0104A'],        // Merah muda & merah tua
        'Lost' => ['#F1F5F9', '#475569'],           // Abu-abu slate

        // Legacy status fallbacks
        'In Stock' => ['#E7F6EC', '#1E7A3C'],
        'Borrow' => ['#FFF4DB', '#8A5A00'],
        'Instalation' => ['#EFF6FF', '#1D4ED8'],
        'Broken' => ['#FCE8EF', '#B0104A'],
        'Retur device to supplier' => ['#FEF3C7', '#B45309'],
        'Sold Out' => ['#E9ECEF', '#343A40'],
        'Re-purchase needed' => ['#FFF4DB', '#8A5A00'],
        'Has not been tested' => ['#FEF3C7', '#B45309'],
        'ATK' => ['#E7F6EC', '#1E7A3C'],
        'default' => ['#EEF2F6', '#46586A'],
    ],
];
