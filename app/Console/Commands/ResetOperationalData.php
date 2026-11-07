<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Empties the operational data (stock, PO/PR/SO/WO/DO, scans, documents, notifications ...) so real data can be loaded,
 * WITHOUT touching users, roles, sessions or the migrations table. Safer than migrate:fresh, which would delete the
 * superadmin account too.
 */
class ResetOperationalData extends Command
{
    protected $signature = 'wms:reset-data
        {--master : Also empty master data (suppliers, customers, item types, warehouses)}
        {--logs : Also empty the activity log}
        {--force : Do not ask for confirmation}';

    protected $description = 'Empty stock and transaction data but keep users, roles and permissions';

    /** Child tables first. */
    private const OPERATIONAL = [
        'delivery_order_items', 'delivery_orders', 'work_order_items', 'work_orders',
        'sales_order_items', 'sales_orders', 'purchase_request_items', 'purchase_requests',
        'purchase_order_items', 'purchase_orders', 'serial_scans', 'items', 'racks', 'inventories',
        'documents', 'app_notifications',
    ];

    private const MASTER = ['suppliers', 'customers', 'item_types', 'locations'];

    public function handle(): int
    {
        $tables = self::OPERATIONAL;
        if ($this->option('master')) {
            $tables = array_merge($tables, self::MASTER);
        }
        if ($this->option('logs')) {
            $tables[] = 'activity_logs';
        }
        $tables = array_values(array_filter($tables, fn ($t) => Schema::hasTable($t)));

        $this->warn('This will EMPTY these tables: ' . implode(', ', $tables));
        $this->info('Kept: users, roles' . ($this->option('master') ? '' : ', suppliers, customers, item types, warehouses') . ($this->option('logs') ? '' : ', activity log'));

        if (! $this->option('force') && ! $this->confirm('Continue? This cannot be undone. Make a database backup first.')) {
            $this->line('Cancelled.');

            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                DB::table($table)->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if (in_array('documents', $tables, true)) {
            Storage::disk('local')->deleteDirectory('documents');
        }

        $this->info('Done. ' . count($tables) . ' tables emptied. Users and roles were not touched.');

        return self::SUCCESS;
    }
}
