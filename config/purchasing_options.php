<?php

return [
    // draft = PO baru, menunggu approval (lihat permission purchasing.approve)
    'statuses' => ['draft', 'ordered', 'partial', 'received', 'rejected', 'cancelled'],

    'status_labels' => [
        'draft' => 'Pending Approval',
        'ordered' => 'Ordered',
        'partial' => 'Partially Received',
        'received' => 'Received',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ],

    // Warna label status: [latar, teks] - selaras sama status_colors di item_options.php
    'status_colors' => [
        'draft' => ['#FFF4DB', '#8A5A00'],
        'ordered' => ['#E6F0F7', '#004E7A'],
        'partial' => ['#FFF4DB', '#8A5A00'],
        'received' => ['#E7F6EC', '#1E7A3C'],
        'rejected' => ['#FCE8EF', '#B0104A'],
        'cancelled' => ['#FCE8EF', '#B0104A'],
        'default' => ['#EEF2F6', '#46586A'],
    ],

    // Status Purchase Request
    'pr_status_labels' => [
        'submitted' => 'Pending Approval',
        'open' => 'Pending Purchasing',
        'ordered' => 'Converted to PO',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ],
    'pr_status_colors' => [
        'submitted' => ['#FFF4DB', '#8A5A00'],
        'open' => ['#E6F0F7', '#004E7A'],
        'ordered' => ['#E7F6EC', '#1E7A3C'],
        'rejected' => ['#FCE8EF', '#B0104A'],
        'cancelled' => ['#EEF2F6', '#46586A'],
        'default' => ['#EEF2F6', '#46586A'],
    ],
];
