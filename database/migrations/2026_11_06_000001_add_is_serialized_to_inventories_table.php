<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventories') && ! Schema::hasColumn('inventories', 'is_serialized')) {
            Schema::table('inventories', function (Blueprint $table) {
                $table->boolean('is_serialized')->default(true)->after('type_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventories') && Schema::hasColumn('inventories', 'is_serialized')) {
            Schema::table('inventories', function (Blueprint $table) {
                $table->dropColumn('is_serialized');
            });
        }
    }
};
