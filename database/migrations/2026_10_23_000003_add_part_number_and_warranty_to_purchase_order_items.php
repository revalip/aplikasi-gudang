<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->string('warranty')->nullable()->after('rack');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('inventory_id')->nullable()->after('purchase_order_id')->constrained('inventories')->nullOnDelete();
            $table->string('part_number')->nullable()->after('inventory_id');
            $table->string('warranty')->nullable()->after('merek');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_id');
            $table->dropColumn(['part_number', 'warranty']);
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('warranty');
        });
    }
};

