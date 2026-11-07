<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Unit yang "dikunci" untuk 1 baris Sales Order (belum keluar, tapi tidak boleh dipakai SO lain).
            $table->foreignId('reserved_sales_order_item_id')->nullable()->constrained('sales_order_items')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reserved_sales_order_item_id');
            $table->dropColumn('reserved_at');
        });
    }
};
