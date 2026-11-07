<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('role');
        });

        // Peran lama (paket) dikonversi ke centang modul supaya akses user yang sudah ada tidak berubah.
        DB::table('users')->where('role', 'staff_gudang')->update([
            'role' => 'user',
            'permissions' => json_encode(['items.manage', 'item-types.manage', 'serial-scans', 'purchasing', 'suppliers']),
        ]);
        DB::table('users')->where('role', 'sales')->update([
            'role' => 'user',
            'permissions' => json_encode([]),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('user')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('sales')->change();
        });
        DB::table('users')->where('role', 'user')->update(['role' => 'sales']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
