<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds shelf / bin location column to items table to fulfill Bab 3.1 & 3.5 hierarchy:
     * Warehouse -> Rack -> Shelf (Bin). Example format: WH-01 / RACK-A01 / SHELF-02.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('shelf')->nullable()->after('rack');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('shelf');
        });
    }
};

