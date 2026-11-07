<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Location;
use App\Models\ItemType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

echo "=== CLEARING DATABASE FOR FRESH TEST FLOW ===\n";

Schema::disableForeignKeyConstraints();

$tablesToTruncate = [
    'delivery_order_items',
    'delivery_orders',
    'work_order_items',
    'work_orders',
    'sales_order_items',
    'sales_orders',
    'purchase_order_items',
    'purchase_orders',
    'purchase_request_items',
    'purchase_requests',
    'items',
    'inventories',
    'serial_scans',
    'activity_logs',
    'documents',
    'app_notifications',
];

foreach ($tablesToTruncate as $table) {
    if (Schema::hasTable($table)) {
        DB::table($table)->truncate();
        echo "- Truncated {$table}\n";
    }
}

// Keep only superadmin user
DB::table('users')->where('role', '!=', 'superadmin')->delete();
$superadmin = User::where('role', 'superadmin')->first();
if (!$superadmin) {
    $superadmin = User::create([
        'name' => 'Admin',
        'email' => 'admin@mjg.com',
        'password' => 'password',
        'role' => 'superadmin',
        'permissions' => [],
    ]);
    echo "- Created fresh Superadmin (admin@mjg.com / password)\n";
} else {
    // Ensure known password if needed
    $superadmin->update([
        'name' => 'Admin',
        'email' => 'admin@mjg.com',
        'password' => 'password',
        'role' => 'superadmin',
    ]);
    echo "- Retained Superadmin: {$superadmin->email} (Password: password)\n";
}

// Clear physical stored document files if any
try {
    Storage::disk('local')->deleteDirectory('documents');
    Storage::disk('local')->deleteDirectory('private/documents');
    echo "- Cleared local document storage\n";
} catch (\Throwable $e) {
    // ignore
}

// Ensure at least basic warehouse locations exist so warehouse views & add item work
if (Location::count() === 0) {
    Location::create(['name' => 'Lantai 1']);
    Location::create(['name' => 'Lantai 2']);
    Location::create(['name' => 'Lantai 3']);
    Location::create(['name' => 'Rak Barang Rusak']);
    echo "- Created default warehouse locations (Lantai 1-3, Rak Barang Rusak)\n";
} else {
    echo "- Preserved existing " . Location::count() . " warehouse locations\n";
}

// Ensure basic Item Types exist so inventory creation works
if (ItemType::count() === 0) {
    $types = ['Router', 'Switch', 'Access Point', 'Modul/SFP', 'Antena', 'Modem', 'Adaptor/POE/PSU', 'Kabel'];
    foreach ($types as $t) {
        ItemType::create(['name' => $t]);
    }
    echo "- Created default item types\n";
} else {
    echo "- Preserved existing " . ItemType::count() . " item types\n";
}

Schema::enableForeignKeyConstraints();

echo "\n=== DATABASE CLEANED SUCCESSFULLY ===\n";
echo "Superadmin Account:\n";
echo "Email: admin@mjg.com\n";
echo "Password: password\n";

