<?php

return [
    'statuses' => ['draft', 'submitted', 'approved', 'processing', 'delivered', 'rejected', 'cancelled'],

    'status_labels' => [
        'draft' => 'Draft',
        'submitted' => 'Pending Approval',
        'approved' => 'Approved',
        'processing' => 'Processing',
        'delivered' => 'Delivered',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ],

    // [latar, teks] - selaras sama purchasing_options.php
    'status_colors' => [
        'draft' => ['#EEF2F6', '#46586A'],
        'submitted' => ['#FFF4DB', '#8A5A00'],
        'approved' => ['#E7F6EC', '#1E7A3C'],
        'processing' => ['#E6F0F7', '#004E7A'],
        'delivered' => ['#DDF3E4', '#14612F'],
        'rejected' => ['#FCE8EF', '#B0104A'],
        'cancelled' => ['#EEF2F6', '#46586A'],
        'default' => ['#EEF2F6', '#46586A'],
    ],

    // Status Delivery Order
    'do_status_labels' => [
        'picking' => 'Picking (scan SN)',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ],
    'do_status_colors' => [
        'picking' => ['#FFF4DB', '#8A5A00'],
        'delivered' => ['#DDF3E4', '#14612F'],
        'cancelled' => ['#EEF2F6', '#46586A'],
        'default' => ['#EEF2F6', '#46586A'],
    ],
];
