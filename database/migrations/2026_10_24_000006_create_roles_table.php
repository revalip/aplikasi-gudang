<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();   // slug stored in users.role
            $table->string('label');
            $table->string('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['superadmin', 'Superadmin', 'Full access to all modules, including User Management.', [], true],
            ['user', 'User', 'Can only access assigned modules.', [], true],
            ['warehouse-admin', 'Warehouse Admin', 'Manage warehouse items, item types, serial scans and deliveries.', ['items.manage', 'item-types.manage', 'serial-scans', 'delivery'], false],
            ['purchasing', 'Purchasing', 'Purchase orders and suppliers.', ['purchasing', 'suppliers'], false],
            ['sales', 'Sales', 'Sales orders, work orders and customers.', ['sales', 'work-orders', 'customers'], false],
        ];

        foreach ($rows as [$name, $label, $description, $permissions, $system]) {
            DB::table('roles')->insert([
                'name' => $name, 'label' => $label, 'description' => $description,
                'permissions' => json_encode($permissions), 'is_system' => $system,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
