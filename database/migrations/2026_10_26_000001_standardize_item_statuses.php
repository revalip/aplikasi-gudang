<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Standardizes item statuses in the database to align with the 7 statuses
     * specified in the Internship Project Assignment (Bab 3.5 & Bab 7):
     * Available, Reserved, In Transit, Rented, Under Service, Damaged, Lost.
     */
    public function up(): void
    {
        DB::table('items')->where('status', 'In Stock')->update(['status' => 'Available']);
        DB::table('items')->where('status', 'Borrow')->update(['status' => 'Rented']);
        DB::table('items')->where('status', 'Broken')->update(['status' => 'Damaged']);
        DB::table('items')->where('status', 'Instalation')->update(['status' => 'Reserved']);
        DB::table('items')->where('status', 'ATK')->update(['status' => 'Available']);
        DB::table('items')->whereIn('status', ['Has not been tested', 'Retur device to supplier', 'Temporarity unavailable'])->update(['status' => 'Under Service']);
        DB::table('items')->whereIn('status', ['Sold Out', 'Re-purchase needed'])->update(['status' => 'Lost']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('items')->where('status', 'Available')->update(['status' => 'In Stock']);
        DB::table('items')->where('status', 'Rented')->update(['status' => 'Borrow']);
        DB::table('items')->where('status', 'Damaged')->update(['status' => 'Broken']);
        DB::table('items')->where('status', 'Reserved')->update(['status' => 'Instalation']);
        DB::table('items')->where('status', 'Under Service')->update(['status' => 'Has not been tested']);
    }
};

