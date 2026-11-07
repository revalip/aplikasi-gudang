<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('unit', 20)->nullable()->default('pcs')->after('qty_ordered');
        });

        Schema::table('work_order_items', function (Blueprint $table) {
            $table->string('unit', 20)->nullable()->default('pcs')->after('qty');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('unit', 20)->nullable()->default('pcs')->after('condition');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });

        Schema::table('work_order_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
