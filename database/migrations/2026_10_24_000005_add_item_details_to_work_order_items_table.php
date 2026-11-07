<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_items', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('part_number');
            $table->string('brand')->nullable()->after('item_name');
            $table->text('description')->nullable()->after('brand');
            $table->text('serial_numbers')->nullable()->after('qty'); // one SN per line, qty = number of lines
        });
    }

    public function down(): void
    {
        Schema::table('work_order_items', function (Blueprint $table) {
            $table->dropColumn(['item_name', 'brand', 'description', 'serial_numbers']);
        });
    }
};
