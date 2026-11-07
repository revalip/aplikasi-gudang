<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('inventory_id')->nullable()->after('id')->constrained('inventories')->nullOnDelete();
            $table->string('part_number')->nullable()->after('type_id');
            $table->string('rack')->nullable()->after('location_id');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_id');
            $table->dropColumn(['part_number', 'rack']);
        });
    }
};

