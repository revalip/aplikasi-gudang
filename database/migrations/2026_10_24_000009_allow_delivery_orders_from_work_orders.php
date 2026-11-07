<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery Orders could only be created from a Sales Order. The current flow is PO -> WO -> DO (no SO),
 * so a DO can now belong to a Work Order instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->change();
            $table->foreignId('work_order_id')->nullable()->after('sales_order_id')->constrained('work_orders')->nullOnDelete();
        });

        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->foreignId('sales_order_item_id')->nullable()->change();
            $table->foreignId('work_order_item_id')->nullable()->after('sales_order_item_id')->constrained('work_order_items')->nullOnDelete();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('reserved_work_order_item_id')->nullable()->index()->after('reserved_sales_order_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['reserved_work_order_item_id']);
            $table->dropColumn('reserved_work_order_item_id');
        });
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_item_id');
        });
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
        });
    }
};
